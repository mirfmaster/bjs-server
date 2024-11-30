<?php

namespace App\Wrapper;

use App\Client\BJSClient;
use App\Services\BJSService;
use App\Client\UtilClient;
use App\Services\OrderService;
use App\Traits\LoggerTrait;
use Illuminate\Support\Facades\Log;

class BJSWrapper
{
    use LoggerTrait;

    private BJSClient $bjsCli;
    public function __construct(
        public BJSService $bjsService,
        public OrderService $order,
        public UtilClient $util,
    ) {
        $this->bjsCli = $bjsService->bjs;
    }

    public function fetchLikeOrder($watchlists)
    {
        $context = ['process' => 'like'];
        foreach ($watchlists as $id) {
            $context['processID'] = $id;

            $orders = $this->bjsService->getOrdersData($id, 0);

            Log::info("Processing orders: " . count($orders), $context);
            foreach ($orders as $order) {
                $ctx = $context;
                $ctx['orderData'] = [
                    'id' => $order->id,
                    'link' => $order->link,
                    'requested' => $order->count,
                ];
                $exist = $this->order->findBJSID($order->id);
                if ($exist) {
                    Log::warning("Order already exist, skipping...", $ctx);
                    continue;
                }

                try {
                    $shortcode = $this->util->getMediaCode($order->link);
                    if ($shortcode === null) {
                        Log::warning("Shortcode is not valid, skipping...", $ctx);
                        $this->bjsCli->cancelOrder($order->id);
                        continue;
                    }

                    $getInfo = $this->util->__IGGetInfo($shortcode);
                    $info = $getInfo;
                    unset($info->data);

                    if (! $info->found || $info->owner_is_private) {
                        Log::warning("Unable to fetch target data, skipping...", $ctx);
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    $ctx['info'] = $info;
                    Log::info("Succesfully fetching info, setting start count and changing to inprogress", $ctx);

                    $this->bjsCli->setStartCount($order->id, $info->like_count);
                    sleep(1);
                    $this->bjsCli->changeStatus($order->id, 'inprogress');

                    $data = [
                        'bjs_id' => $order->id,
                        'kind' => 'like',
                        'username' => $info->owner_username,
                        'instagram_user_id' => $info->owner_id,
                        'target' => $order->link,
                        'media_id' => $info->media_id,
                        'start_count' => $info->like_count,
                        'requested' => $order->count,
                        'margin_request' => UtilClient::withOrderMargin($order->count),
                        'status' => 'inprogress',
                        'status_bjs' => 'inprogress',
                        "source" => "bjs"
                    ];
                    $this->order->createAndUpdateCache($data);

                    Log::info('Order fetch info media success, processing next...');
                } catch (\Throwable $th) {
                    $this->logError($th, $ctx);
                    continue;
                }
            }
        }
    }

    public function fetchFollowOrder($watchlists)
    {
        $context = ['process' => 'follow'];
        foreach ($watchlists as $id) {
            $context['processID'] = $id;

            $orders = $this->bjsService->getOrdersData($id, 0);

            Log::info("Processing orders: " . count($orders), $context);
            foreach ($orders as $order) {
                $ctx = $context;
                $ctx['orderData'] = [
                    'id' => $order->id,
                    'link' => $order->link,
                    'requested' => $order->count,
                ];
                $exist = $this->order->findBJSID($order->id);
                if ($exist) {
                    Log::warning("Order already exist, skipping...", $ctx);
                    continue;
                }

                try {
                    $username = $this->bjsService->getUsername($order->link);
                    if ($username == '') {
                        Log::warning("Username is not valid, skipping...", $ctx);
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    $info = $this->util->__IGGetInfo($username);

                    if (! $info->found) {
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    if ($this->order->isBlacklisted($info->pk)) {
                        Log::info('Fetch Follow Orders, ID: ' . $order->id . ' user is blacklisted');
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    if ($info->is_private) {
                        Log::info('Fetch Follow Orders, ID: ' . $order->id . ' user is private');
                        $this->bjsCli->cancelOrder($order->id);
                        continue;
                    }

                    $start = $info->follower_count;
                    $this->bjsCli->setStartCount($order->id, $start);
                    $this->bjsCli->changeStatus($order->id, 'inprogress');

                    $requested = $order->count;
                    $data = [
                        'bjs_id' => $order->id,
                        'kind' => 'follow',
                        'username' => $username,
                        'instagram_user_id' => $info->pk,
                        'target' => $order->link,
                        'start_count' => $start,
                        'requested' => $requested,
                        'margin_request' => UtilClient::withOrderMargin($requested),
                        'status' => 'inprogress',
                        'status_bjs' => 'inprogress',
                        "source" => "bjs"
                    ];
                    $this->order->createAndUpdateCache($data);
                    Log::info('Order fetch info success, processing next...');
                } catch (\Throwable $th) {
                    $this->logError($th, $ctx);
                    continue;
                }
            }
        }
    }
}
