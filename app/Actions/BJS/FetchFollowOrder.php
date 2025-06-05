<?php

namespace App\Actions\BJS;

use App\Client\InstagramClient;
use App\Models\Order;
use App\Services\BJSService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FetchFollowOrder
{
    private const BONUS_PERCENTAGE = 10;

    private const DAILY_LIMIT_ORDER = 100_000;

    public function handle(BJSService $service, InstagramClient $igCli, $serviceID, $status = 0)
    {
        Log::info('======================');
        if ($this->getDailyTotalOrder()->total_requested > self::DAILY_LIMIT_ORDER) {
            Log::info('Max follow process ' . self::DAILY_LIMIT_ORDER . ' per day exceeded, skipping getting follow order');

            return;
        }

        Log::info('Getting follow orders BJS', ['serviceID' => $serviceID, 'status' => $status]);
        $orders = $service->getOrdersData($serviceID, $status);
        $orders = $orders->sortBy('created');
        Log::info('Processing orders: ' . count($orders));

        foreach ($orders as $order) {
            Log::info('processing order follow: ', [
                'id' => $order->id,
                'link' => $order->link,
                'requested' => $order->count,
            ]);
            $exist = Order::query()->where('bjs_id', $order->id)->limit(1)->first();
            if ($exist) {
                Log::warning('Order already exist, skipping...');

                continue;
            }

            if ($this->getDailyTotalOrder()->total_requested > self::DAILY_LIMIT_ORDER) {
                Log::info('Max follow process ' . self::DAILY_LIMIT_ORDER . ' per day exceeded, skipping getting follow order');
                break;
            }

            try {
                $username = $service->extractIdentifier($order->link);
                if ($username == '') {
                    Log::warning('Username is not valid, skipping...');
                    $service->bjs->cancelOrder($order->id);

                    continue;
                }

                $info = $igCli->fetchProfile($username);
                Log::debug('ingfonya', ['info' => $info]);

                if (! $info->found) {
                    Log::info('Userinfo is not found cancelling');
                    $service->bjs->cancelOrder($order->id);
                    $service->bjs->addCancelReason($order->id, 'Cannot find user info');

                    continue;
                }

                if (Redis::sismember('system:order:follow-blacklist', $info->pk)) {
                    Log::info('Fetch Follow Orders, ID: ' . $order->id . ' user is blacklisted');
                    $service->bjs->cancelOrder($order->id);

                    continue;
                }

                if ($info->is_private) {
                    Log::info('Fetch Follow Orders, ID: ' . $order->id . ' user is private');
                    $service->bjs->cancelOrder($order->id);
                    $service->bjs->addCancelReason($order->id, 'Account is private mode');

                    continue;
                }

                $start = $info->follower_count;
                Log::info('Succesfully fetching info, setting start count and changing to inprogress', ['start_count' => $start]);
                $service->auth();
                sleep(1);
                $service->bjs->setStartCount($order->id, $start);
                sleep(1);
                $service->bjs->changeStatus($order->id, 'inprogress');

                $requested = $order->count;
                $data = [
                    'bjs_id' => $order->id,
                    'kind' => 'follow',
                    'username' => $username,
                    'instagram_user_id' => $info->pk,
                    'target' => $order->link,
                    'reseller_name' => $order->user,
                    'price' => $order->charge,
                    'start_count' => $start,
                    'requested' => $requested,
                    'margin_request' => round($requested + max(10, min(100, $requested * (self::BONUS_PERCENTAGE / 100)))),
                    'status' => 'inprogress',
                    'status_bjs' => 'inprogress',
                    'source' => 'bjs',
                ];
                Order::query()->create($data);
                sleep(2);
                Log::info('Order fetch info success, processing next...');
            } catch (\Throwable $th) {
                Log::error('Failed FetchFollowOrder', ['message' => $th->getMessage(), 'line' => $th->getLine()]);

                continue;
            }
        }
    }

    private function getDailyTotalOrder()
    {
        return Order::query()
            ->selectRaw("
                    'follow' as kind,
                    COALESCE(SUM(requested), 0) as total_requested,
                    COALESCE(SUM(margin_request), 0) as total_margin_requested
                ")
            ->whereDate('created_at', now()->toDateString())
            ->where('kind', 'follow')
            ->first();
    }
}
