<?php

namespace App\Wrapper;

use App\Client\BJSClient;
use App\Client\InstagramClient;
use App\Client\UtilClient;
use App\Consts\OrderConst;
use App\Models\Order;
use App\Notifications\TelegramNotification;
use App\Services\BJSService;
use App\Services\OrderService;
use App\Services\OrderServiceV2;
use App\Traits\LoggerTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;

class BJSWrapper
{
    use LoggerTrait;

    private BJSClient $bjsCli;

    public OrderServiceV2 $orderV2;

    public function __construct(
        public BJSService $bjsService,
        public OrderService $order,
        public UtilClient $util,
        public InstagramClient $igCli,
    ) {
        $this->bjsCli = $bjsService->bjs;
        $this->orderV2 = new OrderServiceV2(new Order);
    }

    // NOTE: its better to sync orders from BJS to server, instead server to BJS
    public function fetchLikeOrder($watchlists)
    {
        Log::info('======================');
        $context = ['process' => 'fetch-like'];
        Log::info('Fetching order', $context);
        foreach ($watchlists as $id) {
            $context['processID'] = $id;

            Log::info('Getting orders with status pending', $context);
            $orders = $this->bjsService->getOrdersData($id, 0);
            $orders = $orders->sortBy('created');

            Log::info('Processing orders: ' . count($orders), $context);
            foreach ($orders as $order) {
                // Random delay between 100ms (100,000 microseconds) and 1s (1,000,000 microseconds)
                usleep(mt_rand(100000, 1000000));
                $ctx = $context;
                $ctx['orderData'] = [
                    'id' => $order->id,
                    'link' => $order->link,
                    'requested' => $order->count,
                ];
                Log::info('processing order like: ', $ctx);

                $exist = $this->order->findBJSID($order->id);
                if ($exist) {
                    Log::warning('Order already exist, skipping...', $ctx);

                    continue;
                }

                try {
                    $shortcode = $this->bjsService->extractIdentifier($order->link);
                    if ($shortcode === null) {
                        Log::warning('Shortcode is not valid, skipping...', $ctx);

                        $this->bjsCli->cancelOrder($order->id);
                        $this->bjsCli->addCancelReason($order->id, 'Link is not valid');

                        continue;
                    }

                    $getInfo = $this->util->BJSGetMediaData($shortcode);
                    $info = $getInfo;
                    unset($info->data);

                    if (! $info->found || $info->owner_is_private) {
                        Log::warning('Unable to fetch target data, skipping...', [
                            'shortcode' => $shortcode,
                            'info' => $info,
                        ]);

                        $this->bjsCli->cancelOrder($order->id);
                        $this->bjsCli->addCancelReason($order->id, $info->owner_is_private ? 'Account is private mode' : 'Media is not found');

                        continue;
                    }

                    // Add daily limit check
                    if (! $this->orderV2->canProcessLikeOrder($getInfo->media_id)) {
                        Log::warning('Daily limit reached for media_id: ' . $getInfo->media_id, $ctx);
                        $this->bjsCli->cancelOrder($order->id);
                        $this->bjsCli->addCancelReason($order->id, 'Daily limit reached');

                        continue;
                    }

                    $ctx['info'] = $info;
                    Log::info('Succesfully fetching info, setting start count and changing to inprogress', [
                        'start_count' => $info->like_count,
                    ]);

                    $this->bjsService->auth();
                    sleep(1);
                    $this->bjsCli->setStartCount($order->id, $info->like_count);
                    sleep(1);
                    $this->bjsCli->changeStatus($order->id, 'inprogress');

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
                        'requested' => $order->count,
                        'margin_request' => UtilClient::withOrderMargin($order->count),
                        'status' => 'inprogress',
                        'status_bjs' => 'inprogress',
                        'source' => 'bjs',
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

    public function fetchFollowOrder($watchlists, &$totalFollowOrder)
    {
        Log::info('======================');

        if ($totalFollowOrder > OrderConst::MAX_PROCESS_FOLLOW_PER_DAY) {
            Log::info('Max follow process per day exceeded, skipping getting follow order');

            return;
        }

        $context = ['process' => 'fetch-follow'];
        Log::info('Fetching order', $context);
        foreach ($watchlists as $id) {
            $context['processID'] = $id;

            Log::info('Getting orders with status pending', $context);
            $orders = $this->bjsService->getOrdersData($id, 0);
            $orders = $orders->sortBy('created');

            Log::info('Processing orders: ' . count($orders), $context);
            foreach ($orders as $order) {
                usleep(mt_rand(100_000, 1_000_000));
                $ctx = $context;
                $ctx['orderData'] = [
                    'id' => $order->id,
                    'link' => $order->link,
                    'requested' => $order->count,
                ];
                Log::info('processing order follow: ', $ctx);
                $exist = $this->order->findBJSID($order->id);
                if ($exist) {
                    Log::warning('Order already exist, skipping...', $ctx);

                    continue;
                }

                if ($totalFollowOrder > OrderConst::MAX_PROCESS_FOLLOW_PER_DAY) {
                    Log::info('Max follow process per day exceeded, skipping getting order');
                    break;
                }

                try {
                    $username = $this->bjsService->extractIdentifier($order->link);
                    if ($username == '') {
                        Log::warning('Username is not valid, skipping...', $ctx);
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    // Add daily limit check
                    if (! $this->orderV2->canProcessFollowOrder($username)) {
                        Log::warning('Daily limit reached for username: ' . $username, $ctx);
                        $this->bjsCli->cancelOrder($order->id);
                        $this->bjsCli->addCancelReason($order->id, 'Daily limit reached');

                        continue;
                    }

                    $info = $this->igCli->fetchProfile($username);
                    Log::debug('ingfonya', ['info' => $info]);

                    if (! $info->found) {
                        Log::info('Userinfo is not found cancelling');
                        $this->bjsCli->cancelOrder($order->id);
                        $this->bjsCli->addCancelReason($order->id, 'Cannot find user info');

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
                        $this->bjsCli->addCancelReason($order->id, 'Account is private mode');

                        continue;
                    }

                    $start = $info->follower_count;
                    Log::info('Succesfully fetching info, setting start count and changing to inprogress', ['start_count' => $start]);
                    $this->bjsService->auth();
                    sleep(1);
                    $this->bjsCli->setStartCount($order->id, $start);
                    sleep(1);
                    $this->bjsCli->changeStatus($order->id, 'inprogress');

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
                        'margin_request' => UtilClient::withOrderMargin($requested),
                        'status' => 'inprogress',
                        'status_bjs' => 'inprogress',
                        'source' => 'bjs',
                    ];
                    $this->order->createAndUpdateCache($data);
                    $totalFollowOrder += $requested;
                    sleep(2);
                    Log::info('Order fetch info success, processing next...');
                } catch (\Throwable $th) {
                    $this->logError($th, $ctx);

                    continue;
                }
            }
        }
    }

    // public function processCachedOrders()
    // {
    //     Log::info('======================');
    //     $context = ['process' => 'check-cached-order'];
    //     $orders = $this->order->getCachedOrders();
    //
    //     $orderCount = $orders->count();
    //     Log::info('Cached order count: '.$orderCount, $context);
    //
    //     if ($orderCount == 0) {
    //         return;
    //     }
    //
    //     foreach ($orders as $o) {
    //         $this->processCachedOrder($o, $context);
    //         Log::info('======================'.PHP_EOL.PHP_EOL);
    //     }
    // }
    //
    // /**
    //  * Process individual order and update its status
    //  */
    // private function processCachedOrder($order, array $baseContext): void
    // {
    //     // TODO: instead handling based on status, what about sync it from redis status -> db status -> bjs_status
    //     // STATUS MOVER SHOULD USE RASPI AS THE MOVER
    //     $this->order->setOrderID($order->id);
    //     $redisData = $this->order->getCachedState();
    //
    //     $context = array_merge($baseContext, [
    //         'order_id' => $order->id,
    //         'bjs_id' => $order->bjs_id,
    //         'redis_data' => $redisData,
    //     ]);
    //
    //     Log::info("Processing order #{$order->bjs_id}", $context);
    //
    //     $remainingCount = $redisData['requested'] - $redisData['processed'];
    //
    //     switch ($redisData['status']) {
    //         case 'inprogress':
    //             $this->handleInProgressCache($remainingCount, $context);
    //             break;
    //
    //         case 'processing':
    //             $this->handleProcessingCache($remainingCount, $redisData, $context);
    //             break;
    //
    //         case 'completed':
    //             $this->handleProcessingCache($remainingCount, $redisData, $context);
    //             break;
    //
    //         default:
    //             Log::error('Status redis is not supported yet!', $context);
    //             break;
    //     }
    // }

    /**
     * NOTE:
     * Handle orders in 'inprogress' status
     * Status changes: inprogress -> completed, processing
     */
    private function handleInProgressCache(int $remainingCount, array $context): void
    {
        if ($remainingCount <= 0) {
            Log::info('Order completed - all requested items processed', $context);
            $this->order->setStatusRedis('completed');
        } else {
            Log::info("Order continuing - {$remainingCount} items remaining to process", $context);
            $this->order->setStatusRedis('processing');
        }
    }

    /**
     * TODO: add validation of timelimit 6 hours
     * Handle orders in 'processing' status
     * Status changes: processing -> completed, partial, cancel
     */
    private function handleProcessingCache(int $remainingCount, array $redisData, array $context): void
    {
        $processingGap = $redisData['processing'] - $redisData['processed'];
        $maxAllowedGap = 250;
        $anomalyThreshold = 50;

        $anomalyContext = array_merge($context, [
            'failed_count' => $redisData['failed'],
            'max_allowed_gap' => $maxAllowedGap,
            'current_processing_gap' => $processingGap,
        ]);

        if ($remainingCount <= 0) {
            Log::info('Order completed - all requested items processed', $context);
            $this->order->setStatusRedis('completed');

            return;
        }

        $hasAnomaly = $processingGap >= $anomalyThreshold || $redisData['failed'] >= $anomalyThreshold;
        if ($hasAnomaly) {
            if ($redisData['processed'] > 0) {
                Log::warning("Order marked as partial - anomaly detected with {$redisData['processed']} items processed", $anomalyContext);
                $this->order->setStatusRedis('partial');
            } else {
                Log::warning('Order cancelled - anomaly detected before any processing', $anomalyContext);
                $this->order->setStatusRedis('cancel');
            }
        }
    }

    /**
     * Process orders and sync their status between Redis and BJS service
     * Status flow: inprogress -> processing, completed, partial, cancel
     */
    public function processOrders(): void
    {
        Log::info('======================');
        $stateLogin = (bool) Redis::get('system:allow-login-bjs');
        $baseContext = [
            'process' => 'check-order',
            'allow_login_bjs' => $stateLogin,
        ];
        $orders = $this->order->getOrders();

        Log::info("Found {$orders->count()} orders to process", $baseContext);

        foreach ($orders as $order) {
            $stateLogin = (bool) Redis::get('system:allow-login-bjs');
            $baseContext['allow_login_bjs'] = $stateLogin;
            if ($stateLogin == false) {
                Log::info('State login is false, skipping task');

                break;
            }

            if ($order->source != 'bjs') {
                Log::info("Skipping order: $order->id source: $order->source");

                continue;
            }
            $this->processOrderStatus($order, $baseContext);
            Log::info('======================' . PHP_EOL . PHP_EOL);
        }
    }

    public function processDirectOrders(): void
    {
        Log::info('======================');
        $baseContext = [
            'process' => 'process-direct-order',
        ];
        $orders = $this->order->getOrders();

        Log::info("Found {$orders->count()} orders to process", $baseContext);

        if ($orders->count() == 0) {
            return;
        }

        foreach ($orders as $order) {
            if (! in_array($order->source, ['direct', 'refill'])) {
                Log::info("Skipping order: $order->id source: $order->source");

                continue;
            }

            $this->order->setOrderID($order->id);
            $redisData = $this->order->getCachedState();
            $ctx = array_merge($baseContext, [
                'orders' => $orders->only([
                    'id',
                    'kind',
                    'username',
                    'target',
                ]),
                'redis_data' => $redisData,

            ]);

            Log::info('Processing direct order', $ctx);
            $redisStatus = $redisData['status'];
            if (in_array($redisStatus, ['inprogress', 'processing'])) {
                Log::info("Skipping order due to status: $redisStatus");

                continue;
            }

            $order->processed = $redisData['processed'];
            $order->status = $redisStatus;
            $order->status_bjs = $redisStatus;

            if ($redisStatus == 'partial') {
                $order->partial_count = $order->requested - $order->processed;
            }

            $order->end_at = now();

            Log::info('Updating data', [
                'redis_data' => $redisData,
                'update_model' => $order->save(),
                'processed' => $order->processed,
                'status' => $order->status,
            ]);

            // NOTE: THIS ONLY for not processing case;
            $this->order->deleteOrderRedisKeys($order->id);

            $this->order->updateCache();
            Log::info('======================' . PHP_EOL . PHP_EOL);
        }
    }

    /**
     * Process individual order status updates
     */
    private function processOrderStatus($order, array $baseContext): void
    {
        $this->order->setOrderID($order->id);
        $redisData = $this->order->getCachedState();

        $context = array_merge($baseContext, [
            'order_id' => $order->id,
            'bjs_id' => $order->bjs_id,
            'redis_data' => $redisData,
        ]);

        Log::info("Processing order #{$order->bjs_id}", $context);

        $remainingCount = max(0, $redisData['requested'] - $redisData['processed']);

        switch ($order->status) {
            case 'inprogress':
            case 'processing':
                $this->handleInProgressOrder($order, $redisData['status'], $remainingCount, $context);
                break;

                // case 'processing':
                //     $this->handleProcessingOrder($order, $remainingCount, $context);
                //     break;

            case 'completed':
                $this->handleCompletedOrder($order, $remainingCount, $context);
                break;

            case 'partial':
                $this->handlePartialOrder($order, $remainingCount, $context);
                break;

            case 'cancel':
            case 'canceled':
                $this->handleCancelOrder($order, $remainingCount, $context);
                break;

            default:
                Log::error('Unsupported Redis status encountered', $context);
                break;
        }
        $this->order->updateCache();
    }

    /**
     * Handle orders in 'inprogress' status with the redis status is other than the processing, completed, partial, cancel
     * Actor who change the redis status is worker
     */
    private function handleInProgressOrder($order, string $redisStatus, int $remainingCount, array $context): void
    {
        $updateResult = [
            'model_updated' => false,
            'bjs_status_updated' => false,
            'remaining_updated' => false,
        ];

        switch ($redisStatus) {
            case 'processing':
                $updateResult = $this->updateToProcessing($order, $remainingCount);
                break;

            case 'completed':
                $updateResult = $this->updateToCompleted($order, $remainingCount);
                break;

            case 'partial':
                $updateResult = $this->updateToPartial($order, $remainingCount);
                break;

            case 'cancel':
            case 'canceled':
                $updateResult = $this->updateToCancelled($order);
                break;

            default:
                Log::error("Unsupported Redis status encountered: $redisStatus", $context);
                break;
        }

        Log::info("Updated order status from {$order->status} to {$redisStatus}", array_merge($context, [
            'update_model' => $updateResult['model_updated'],
            'update_status_bjs' => $updateResult['bjs_status_updated'],
            'set_remaining' => $updateResult['remaining_updated'],
        ]));
    }

    /**
     * Handle orders in 'processing' status
     */
    private function handleProcessingOrder($order, int $remainingCount, array $context): void
    {
        $status = 'processing';
        $remainingUpdated = $this->bjsCli->setRemains($order->bjs_id, $remainingCount);
        $updateStatus = $this->bjsCli->changeStatus($order->bjs_id, $status);

        $updated = $order->update([
            'status' => $status,
            'status_bjs' => $status,
        ]);
        Log::info('Updating BJS', array_merge($context, [
            'updateStatusTo' => $status,
            'update_remains' => $remainingUpdated,
            'updated_order' => $updated,
            'updateStatus' => $updateStatus,
        ]));
    }

    /**
     * Handle orders in 'completed' status
     */
    private function handleCompletedOrder($order, int $remainingCount, array $context): void
    {
        $status = 'completed';
        $remainingUpdated = $this->bjsCli->setRemains($order->bjs_id, $remainingCount);
        $updateStatus = $this->bjsCli->changeStatus($order->bjs_id, $status);

        $updated = $order->update([
            'status' => $status,
            'status_bjs' => $status,
            'end_at' => now(),
        ]);
        Log::info('Updating BJS', array_merge($context, [
            'updateStatusTo' => $status,
            'update_remains' => $remainingUpdated,
            'updated_order' => $updated,
            'updateStatus' => $updateStatus,
        ]));
    }

    /**
     * Handle orders in 'partial' status
     */
    private function handlePartialOrder($order, int $remainingCount, array $context): void
    {
        $status = 'partial';
        $reqBJS = $this->bjsCli->setPartial($order->bjs_id, $remainingCount);

        $updated = $order->update([
            'status' => $status,
            'status_bjs' => $status,
            'partial_count' => $remainingCount,
            'end_at' => now(),
        ]);
        Log::info('Updating BJS', array_merge($context, [
            'updateStatusTo' => $status,
            'updated_order' => $updated,
            'update_bjs' => $reqBJS,
        ]));
    }

    /**
     * Handle orders in 'cancel' status
     */
    private function handleCancelOrder($order, int $remainingCount, array $context): void
    {
        $status = 'cancel';
        $reqBJS = $this->bjsCli->cancelOrder($order->bjs_id);

        $updated = $order->update([
            'status' => $status,
            'status_bjs' => $status,
            'partial_count' => $remainingCount,
            'end_at' => now(),
        ]);
        Log::info('Updating BJS', array_merge($context, [
            'updateStatusTo' => $status,
            'updated_order' => $updated,
            'update_bjs' => $reqBJS,
        ]));
    }

    /**
     * Update order to processing status
     */
    private function updateToProcessing($order, int $remainingCount): array
    {
        $this->order->setOrderID($order->id);
        $redisData = $this->order->getCachedState();

        $order->processed = $redisData['processed'];
        $order->status = 'processing';
        $order->started_at = now();

        $remainingUpdated = $this->bjsCli->setRemains($order->bjs_id, $remainingCount);
        $bjsStatus = $this->bjsCli->changeStatus($order->bjs_id, 'processing');
        if ($bjsStatus) {
            $order->status_bjs = 'processing';
        }

        return [
            'model_updated' => $order->save(),
            'bjs_status_updated' => $bjsStatus,
            'remaining_updated' => $remainingUpdated,
        ];
    }

    /**
     * Update order to completed status
     */
    private function updateToCompleted($order, int $remainingCount): array
    {
        $this->order->setOrderID($order->id);
        $redisData = $this->order->getCachedState();

        $order->processed = $redisData['processed'];
        $order->status = 'completed';
        $order->started_at = $order->started_at ?? now();
        $order->end_at = now();

        $remainingUpdated = $this->bjsCli->setRemains($order->bjs_id, $remainingCount);
        $bjsStatus = $this->bjsCli->changeStatus($order->bjs_id, 'completed');
        if ($bjsStatus) {
            $order->status_bjs = 'completed';
        }

        $this->order->deleteOrderRedisKeys($order->id);

        return [
            'model_updated' => $order->save(),
            'bjs_status_updated' => $bjsStatus,
            'remaining_updated' => $remainingUpdated,
        ];
    }

    /**
     * Update order to partial status
     */
    private function updateToPartial($order, int $remainingCount): array
    {
        $order->status = 'partial';
        $order->started_at = $order->started_at ?? now();
        $order->end_at = now();
        $order->partial_count = $remainingCount;

        $bjsStatus = $this->bjsCli->setPartial($order->bjs_id, $remainingCount);
        if ($bjsStatus) {
            $order->status_bjs = 'partial';
        }
        $this->order->deleteOrderRedisKeys($order->id);

        return [
            'model_updated' => $order->save(),
            'bjs_status_updated' => $bjsStatus,
            'remaining_updated' => null,
        ];
    }

    /**
     * Update order to cancelled status
     */
    private function updateToCancelled($order): array
    {
        $order->status = 'cancel';
        $order->started_at = $order->started_at ?? now();
        $order->end_at = now();

        $bjsStatus = $this->bjsCli->cancelOrder($order->bjs_id);
        if ($bjsStatus) {
            $order->status_bjs = 'cancel';
        }
        $this->order->deleteOrderRedisKeys($order->id);

        return [
            'model_updated' => $order->save(),
            'bjs_status_updated' => $bjsStatus,
            'remaining_updated' => null,
        ];
    }

    public function resyncOrders()
    {
        $baseContext = ['process' => 'resync-orders'];
        $orders = $this->order->getOutOfSyncOrders();

        Log::info("Found {$orders->count()} orders out of sync", $baseContext);

        if ($orders->count() == 0) {
            return;
        }

        foreach ($orders as $order) {
            $this->order->setOrderID($order->id);
            $redisData = $this->order->getCachedState();

            $context = array_merge($baseContext, [
                'order_id' => $order->id,
                'bjs_id' => $order->bjs_id,
                'redis_data' => $redisData,
                'local_status' => $order->status,
                'bjs_status' => $order->status_bjs,
            ]);

            try {
                Log::info('Attempting to resync order status', $context);

                // If Redis status doesn't match local status, prioritize Redis
                if ($redisData['status'] !== $order->status) {
                    $targetStatus = $redisData['status'];
                    Log::info('Redis status differs from local status - using Redis status', array_merge($context, [
                        'redis_status' => $redisData['status'],
                    ]));
                } else {
                    $targetStatus = $order->status;
                }

                $remainingCount = $redisData['requested'] - $redisData['processed'];
                $updateResult = $this->resyncOrderStatus($order, $targetStatus, $remainingCount);

                Log::info('Resync attempt completed', array_merge($context, [
                    'target_status' => $targetStatus,
                    'update_result' => $updateResult,
                ]));
            } catch (\Throwable $th) {
                $this->logError($th, $context);

                continue;
            }
        }
    }

    private function resyncOrderStatus($order, string $targetStatus, int $remainingCount): array
    {
        $updateResult = [
            'model_updated' => false,
            'bjs_status_updated' => false,
            'remaining_updated' => false,
        ];

        switch ($targetStatus) {
            case 'processing':
                $updateResult = $this->updateToProcessing($order, $remainingCount);
                break;

            case 'completed':
                $updateResult = $this->updateToCompleted($order, $remainingCount);
                break;

            case 'partial':
                $updateResult = $this->updateToPartial($order, $remainingCount);
                break;

            case 'cancel':
                $updateResult = $this->updateToCancelled($order);
                break;

            default:
                Log::warning("Unsupported target status for resync: {$targetStatus}", [
                    'order_id' => $order->id,
                    'bjs_id' => $order->bjs_id,
                ]);
                break;
        }

        return $updateResult;
    }

    public function handleServicesAvailability(): void
    {
        Log::info('======================');
        $baseContext = [
            'process' => 'service-availabilty',
        ];
        $services = [
            'follow' => OrderConst::FOLLOW_SERVICES,
            'like' => OrderConst::LIKE_SERVICES,
        ];
        Log::info('Handling service availability');

        $stats = Order::query()
            ->whereDate('created_at', now()->toDateString())
            ->select([
                'kind',
                DB::raw('COALESCE(SUM(requested), 0) as total_requested'),
                DB::raw('COALESCE(SUM(margin_request), 0) as total_margin_requested'),
            ])
            ->groupBy('kind')
            ->get();

        foreach ($stats as $stat) {
            Log::info("Processing order type: $stat->kind", array_merge($baseContext, ['stat' => $stat]));

            if ($stat->total_requested >= OrderConst::MAX_PROCESS_FOLLOW_PER_DAY) {
                $codes = $services[$stat->kind];
                foreach ($codes as $code) {
                    $this->bjsService->auth();
                    Log::info("Disabling service $code");
                    $req = $this->bjsCli->changeStatusServices($code, false);
                    Redis::set("system:bjs:disabled-service:$code", true);
                    Log::info('result', array_merge($baseContext, ['result' => $req]));
                }
            }
            Log::info('======================' . PHP_EOL . PHP_EOL);
        }
    }

    /**
     * Synchronizes order statuses between Redis, local database, and BJS service.
     *
     * This function processes orders in the following ways:
     * 1. Checks BJS login state before processing
     * 2. Filters for BJS-sourced orders only
     * 3. Verifies and updates start counts if missing
     * 4. Syncs status changes from BJS to local system
     */
    public function syncOrdersBJS(): void
    {
        Log::info('======================');
        $stateLogin = (bool) Redis::get('system:allow-login-bjs');
        $baseContext = [
            'process' => 'sync-state',
            'allow_login_bjs' => $stateLogin,
        ];

        $orders = $this->order->getOrders();
        Log::info("Found {$orders->count()} orders to process", $baseContext);

        foreach ($orders as $order) {
            // Recheck login state for each iteration
            $stateLogin = (bool) Redis::get('system:allow-login-bjs');
            $baseContext['allow_login_bjs'] = $stateLogin;

            if (! $stateLogin) {
                Log::info('State login is false, skipping task');
                break;
            }

            if ($order->source !== 'bjs') {
                Log::info("Skipping order: $order->id source: $order->source");

                continue;
            }

            // Set up order context and fetch current state
            $this->order->setOrderID($order->id);
            $state = $this->order->getCachedState();
            $ctx = array_merge($baseContext, [
                'order' => $order->only(['id', 'bjs_id', 'status']),
                'state' => $state,
            ]);

            // Fetch and validate BJS order data
            $info = $this->bjsCli->getOrderDetail($order->bjs_id);
            if (! $info) {
                Log::warning("Cannot fetch data: $order->bjs_id");

                continue;
            }

            // Handle missing start count
            if ($info->start_count === null) {
                Log::info('Start count is null but already processed, updating ...');
                $this->bjsCli->setStartCount($order->bjs_id, $order->start_count);
            }

            // Skip if statuses are already synchronized
            if (OrderConst::TO_BJS_STATUS[$order->status] === $info->status) {
                Log::info('Order status in sync, skipping');

                continue;
            }

            // Process status changes for terminal states (completed, partial, cancel)
            $terminalStates = [
                OrderConst::TO_BJS_STATUS['cancel'],
                OrderConst::TO_BJS_STATUS['partial'],
                OrderConst::TO_BJS_STATUS['completed'],
            ];

            if (in_array($info->status, $terminalStates)) {
                $tokoStatus = OrderConst::FROM_BJS_STATUS[$info->status];
                Log::info('Order status mismatch syncing', array_merge($ctx, [
                    'status_transition' => [
                        'from' => $order->status,
                        'to' => $tokoStatus,
                    ],
                ]));

                $this->order->setStatusRedis($tokoStatus);
                $order->update([
                    'status' => $tokoStatus,
                    'status_bjs' => $tokoStatus,
                ]);
            }

            Log::info('======================' . PHP_EOL . PHP_EOL);
        }
    }

    /**
     * Fetch TikTok orders and send them through Telegram notification
     *
     * @param array $watchlists Service IDs to watch
     * @return int Number of orders found
     */
    public function fetchTiktokOrder($watchlists)
    {
        Log::info('======================');

        $context = ['process' => 'fetch-tiktok'];
        Log::info('Fetching order', $context);

        // Get the last processed order IDs from cache
        $lastProcessedOrders = Cache::get('tiktok:last_processed_orders', []);
        $newProcessedOrders = []; // Track newly processed orders to update cache
        $allOrders = collect(); // Collect all orders across watchlists

        foreach ($watchlists as $id) {
            $context['processID'] = $id;

            Log::info('Getting orders with status pending', $context);
            $orders = $this->bjsService->getOrdersData($id, 0);
            $orders = $orders->sortBy('created');

            // Filter out already processed orders
            $newOrders = $orders->filter(function ($order) use ($lastProcessedOrders) {
                return !in_array($order->id, $lastProcessedOrders);
            });

            // Collect new order IDs for updating the cache
            $newOrderIds = $newOrders->pluck('id')->toArray();
            $newProcessedOrders = array_merge($newProcessedOrders, $newOrderIds);

            // Log how many new orders were found
            $filteredCount = $newOrders->count();
            Log::info("Found {$filteredCount} new orders out of {$orders->count()} total pending orders", $context);

            $allOrders = $allOrders->merge($newOrders);
        }

        Log::info('Processing new orders: ' . count($allOrders), $context);

        if ($allOrders->isEmpty()) {
            Log::info('No new pending orders found');
            return 0;
        }

        // Chunk orders into groups of 5 for Telegram notifications
        $orderChunks = $allOrders->chunk(5);

        $chatId = config('services.telegram.chat_id');
        foreach ($orderChunks as $index => $chunk) {
            $messages = ["<b>📋 New Pending TikTok Orders</b>\n"];

            foreach ($chunk as $order) {
                // Format created timestamp (DD/MM HH:mm)
                $formattedCreated = $order->created
                    ? \Carbon\Carbon::parse($order->created)->format('d/m H:i')
                    : 'N/A';

                // Properly create copyable text using Telegram's HTML format
                $orderMessage = sprintf(
                    "ID: <code>%s</code>\n" .
                        "Request: %s for %s\n" .
                        "Created: %s %s\n" .
                        "Quick Start: <code>/start %s 0</code>\n" .
                        "-------------------\n",
                    $order->id,
                    number_format($order->count),
                    $order->link,
                    $order->user,
                    $formattedCreated,
                    $order->id
                );

                $messages[] = $orderMessage;
            }

            // Add pagination info
            $messages[] = sprintf(
                "\n📋 <b>Page %d/%d | Total New Orders: %d</b>",
                $index + 1,
                $orderChunks->count(),
                $allOrders->count()
            );

            // Send Telegram notification with HTML parse mode for proper code formatting
            $notification = new TelegramNotification($messages, $chatId);
            $notification->formatAs('HTML'); // Ensure HTML parse mode for code blocks
            Notification::sendNow([$chatId], $notification);

            // Add a small delay between sending multiple notifications
            if ($index < $orderChunks->count() - 1) {
                usleep(500000); // 0.5 seconds
            }
        }

        // Update the cache with newly processed orders
        // Keep a reasonable history (last 1000 orders) to prevent cache from growing too large
        $updatedProcessedOrders = array_merge($lastProcessedOrders, $newProcessedOrders);
        if (count($updatedProcessedOrders) > 1000) {
            $updatedProcessedOrders = array_slice($updatedProcessedOrders, -1000);
        }

        // Store the processed order IDs for 24 hours
        Cache::put('tiktok:last_processed_orders', $updatedProcessedOrders, now()->addDay());

        return $allOrders->count();
    }

    /**
     * Send a notification using TelegramNotification
     *
     * @param array $messages Array of message strings
     * @param string $chatId Telegram chat ID
     * @return void
     */
    private function sendTelegramNotification(array $messages, string $chatId): void
    {
        $notification = new TelegramNotification($messages, $chatId);
        $notification->formatAs('HTML'); // Use HTML for proper code formatting
        Notification::sendNow([$chatId], $notification);
        Log::info("Telegram notification sent to {$chatId}");
    }
}
