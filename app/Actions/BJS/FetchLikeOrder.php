<?php

namespace App\Actions\BJS;

use App\Models\Order;
use App\Services\BJSService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchLikeOrder
{
    private const BONUS_PERCENTAGE = 10;

    private const DAILY_MAX_ORDER = 3;

    public function handle(BJSService $service, $serviceID, $status = 0)
    {
        Log::info('Getting orders BJS', ['serviceID' => $serviceID, 'status' => $status]);
        $orders = $service->getOrdersData($serviceID, $status);
        $orders = $orders->sortBy('created');
        Log::info('Processing orders: '.count($orders));

        foreach ($orders as $order) {
            Log::info('processing order like: ', [
                'id' => $order->id,
                'link' => $order->link,
                'requested' => $order->count,
            ]);
            $exist = Order::query()->where('bjs_id', $order->id)->limit(1)->first();
            if ($exist) {
                Log::warning('Order already exist, skipping...');

                continue;
            }

            try {
                $shortcode = $service->extractIdentifier($order->link);
                if ($shortcode === null) {
                    Log::warning('Shortcode is not valid, skipping...', ['link' => $order->link]);

                    $service->bjs->cancelOrder($order->id);
                    $service->bjs->addCancelReason($order->id, 'Link is not valid');

                    continue;
                }

                $getInfo = $this->getMediaData($shortcode);
                Log::debug('getInfo', ['info' => $getInfo]);
                $info = $getInfo;
                unset($info->data);

                if (! $info->found || $info->owner_is_private) {
                    Log::warning('Unable to fetch target data, skipping...', [
                        'shortcode' => $shortcode,
                        'info' => $info,
                    ]);

                    $service->bjs->cancelOrder($order->id);
                    $service->bjs->addCancelReason($order->id, $info->owner_is_private ? 'Account is private mode' : 'Media is not found');

                    continue;
                }

                // Add daily limit check
                if (! $this->canProcessLikeOrder($getInfo->media_id)) {
                    Log::warning('Daily limit reached for media_id: '.$getInfo->media_id.' | limit: '.self::DAILY_MAX_ORDER);
                    $service->bjs->cancelOrder($order->id);
                    $service->bjs->addCancelReason($order->id, 'Daily limit reached');

                    continue;
                }

                $ctx['info'] = $info;
                Log::info('Succesfully fetching info, setting start count and changing to inprogress', [
                    'start_count' => $info->like_count,
                ]);

                $service->auth();
                sleep(1);
                $service->bjs->setStartCount($order->id, $info->like_count);
                sleep(1);
                $service->bjs->changeStatus($order->id, 'inprogress');

                $requested = $order->count;
                $data = [
                    'bjs_id' => $order->id,
                    'kind' => 'like',
                    'username' => $info->owner_username,
                    'instagram_user_id' => $info->owner_id,
                    'target' => $order->link,
                    'reseller_name' => $order->user,
                    'price' => $order->charge,
                    'media_id' => $info->media_id,
                    'start_count' => $info->like_count,
                    'requested' => $requested,
                    'margin_request' => round($requested + max(10, min(100, $requested * (self::BONUS_PERCENTAGE / 100)))),
                    'status' => 'inprogress',
                    'status_bjs' => 'inprogress',
                    'source' => 'bjs',
                ];
                Order::query()->create($data);

                Log::info('Order fetch info media success, processing next...');
            } catch (\Throwable $th) {
                Log::error('Failed FetchLikeOrder', ['message' => $th->getMessage(), 'line' => $th->getLine()]);

                continue;
            }
        }
    }

    public function canProcessLikeOrder(string $mediaId): bool
    {
        $todayOrders = Order::query()
            ->where('media_id', $mediaId)
            ->where('kind', 'like')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return $todayOrders < self::DAILY_MAX_ORDER;
    }

    public function getMediaData(string $code)
    {
        $auth = config('app.redispo_auth');
        throw_if(! $auth, new \Exception('Redispo auth configuration is missing'));

        try {
            $response = Http::withHeaders([
                'authorization' => $auth,
            ])->get(
                'http://172.104.183.180:12091/v2/proxy-ig/media-info-proxyv2',
                [
                    'media_shortcode' => $code,
                    'source' => 'belanjasosmed',
                ]
            );

            if (! $response->successful() || $response->json('error')) {
                throw new \Exception(
                    $response->json('message') ?? 'Error response from server'
                );
            }

            $data = $response->json('data');

            throw_if(empty($data['media']), new \Exception('Media data not found'));

            $media = $data['media'];
            $owner = $media['user'] ?? $media['owner'];
            $result = [
                'error' => false,
                'found' => true,
                'code' => $code,
                'media_id' => $media['pk'],
                'owner_id' => $owner['id'],
                'owner_username' => $owner['username'],
                'owner_pk_id' => $owner['pk_id'],
                'owner_is_private' => $owner['is_private'],
                'like_and_view_counts_disabled' => $media['like_and_view_counts_disabled'],
                'comment_count' => $media['comment_count'],
                'like_count' => $media['like_count'],
            ];

            Log::info('Media data fetched successfully');

            return (object) $result;
        } catch (\Exception $e) {
            Log::error('Failed to fetch media data', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'error' => true,
                'found' => false,
                'code' => $code,
                'message' => $e->getMessage(),
            ];
        }
    }
}
