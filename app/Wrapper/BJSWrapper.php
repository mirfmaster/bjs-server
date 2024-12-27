<?php

namespace App\Wrapper;

use App\Client\BJSClient;
use App\Client\UtilClient;
use App\Services\BJSService;
use App\Services\OrderService;
use App\Traits\LoggerTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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

            Log::info('Processing orders: '.count($orders), $context);
            foreach ($orders as $order) {
                $ctx = $context;
                $ctx['orderData'] = [
                    'id' => $order->id,
                    'link' => $order->link,
                    'requested' => $order->count,
                ];
                $exist = $this->order->findBJSID($order->id);
                if ($exist) {
                    Log::warning('Order already exist, skipping...', $ctx);

                    continue;
                }

                try {
                    $shortcode = $this->util->getMediaCode($order->link);
                    if ($shortcode === null) {
                        Log::warning('Shortcode is not valid, skipping...', $ctx);
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    $getInfo = $this->util->__IGGetInfo($shortcode);
                    $info = $getInfo;
                    unset($info->data);

                    if (! $info->found || $info->owner_is_private) {
                        Log::warning('Unable to fetch target data, skipping...', $ctx);
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    $ctx['info'] = $info;
                    Log::info('Succesfully fetching info, setting start count and changing to inprogress', $ctx);

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

    public function fetchFollowOrder($watchlists)
    {
        $context = ['process' => 'follow'];
        foreach ($watchlists as $id) {
            $context['processID'] = $id;

            $orders = $this->bjsService->getOrdersData($id, 0);

            Log::info('Processing orders: '.count($orders), $context);
            foreach ($orders as $order) {
                $ctx = $context;
                $ctx['orderData'] = [
                    'id' => $order->id,
                    'link' => $order->link,
                    'requested' => $order->count,
                ];
                $exist = $this->order->findBJSID($order->id);
                if ($exist) {
                    Log::warning('Order already exist, skipping...', $ctx);

                    continue;
                }

                try {
                    $username = $this->bjsService->getUsername($order->link);
                    if ($username == '') {
                        Log::warning('Username is not valid, skipping...', $ctx);
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    $info = $this->util->__IGGetInfo($username);

                    if (! $info->found) {
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    if ($this->order->isBlacklisted($info->pk)) {
                        Log::info('Fetch Follow Orders, ID: '.$order->id.' user is blacklisted');
                        $this->bjsCli->cancelOrder($order->id);

                        continue;
                    }

                    if ($info->is_private) {
                        Log::info('Fetch Follow Orders, ID: '.$order->id.' user is private');
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
                        'source' => 'bjs',
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

    public function processCachedOrders()
    {
        $context = ['process' => 'check-cached-order'];
        $orders = $this->order->getCachedOrders();

        $orderCount = $orders->count();
        Log::info('Cached order count: '.$orderCount, $context);

        if ($orderCount == 0) {
            return;
        }

        foreach ($orders as $o) {
            $this->processCachedOrder($o, $context);
        }
    }

    /**
     * Process individual order and update its status
     */
    private function processCachedOrder($order, array $baseContext): void
    {
        $this->order->setOrderID($order->id);
        $redisData = $this->order->getOrderRedisKeys();

        $context = array_merge($baseContext, [
            'order_id' => $order->id,
            'bjs_id' => $order->bjs_id,
            'redis_data' => $redisData,
        ]);

        Log::info("Processing order #{$order->bjs_id}", $context);

        $remainingCount = $redisData['requested'] - $redisData['processed'];

        switch ($redisData['status']) {
            case 'inprogress':
                $this->handleInProgressCache($remainingCount, $context);
                break;

            case 'processing':
                $this->handleProcessingCache($remainingCount, $redisData, $context);
                break;

            default:
                Log::error('Status redis is not supported yet!', $context);
                break;
        }
    }

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
        $stateLogin = (bool) Redis::get('system:allow-login-bjs');
        $baseContext = [
            'process' => 'check-order',
            'allow_login_bjs' => $stateLogin,
        ];
        $orders = $this->order->getOrders();

        Log::info("Found {$orders->count()} orders to process", $baseContext);

        if ($orders->count() == 0 || $stateLogin == false) {
            Log::info('Skip the process due to not meet requirements');

            return;
        }

        foreach ($orders as $order) {
            $stateLogin = (bool) Redis::get('system:allow-login-bjs');
            $baseContext['allow_login_bjs'] = $stateLogin;
            if ($stateLogin == false) {
                Log::info('State login is false, skipping task');

                return;
            }
            $this->processOrderStatus($order, $baseContext);
        }
    }

    /**
     * Process individual order status updates
     */
    private function processOrderStatus($order, array $baseContext): void
    {
        $this->order->setOrderID($order->id);
        $redisData = $this->order->getOrderRedisKeys();

        $context = array_merge($baseContext, [
            'order_id' => $order->id,
            'bjs_id' => $order->bjs_id,
            'redis_data' => $redisData,
        ]);

        Log::info("Processing order #{$order->bjs_id}", $context);

        $remainingCount = $redisData['requested'] - $redisData['processed'];

        switch ($order->status) {
            case 'inprogress':
                $this->handleInProgressOrder($order, $redisData['status'], $remainingCount, $context);
                break;

            case 'processing':
                $this->handleProcessingOrder($order, $redisData['status'], $remainingCount, $context);
                break;

            default:
                Log::error('Unsupported Redis status encountered', $context);
                break;
        }
    }

    /**
     * Handle orders in 'inprogress' status
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
                $updateResult = $this->updateToCancelled($order);
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
    private function handleProcessingOrder($order, string $redisStatus, int $remainingCount, array $context): void
    {
        if ($redisStatus === 'processing') {
            $remainingUpdated = $this->bjsCli->setRemains($order->bjs_id, $remainingCount);
            Log::info('Updated remaining count for processing order', array_merge($context, [
                'update_status_bjs' => $remainingUpdated,
            ]));
        }
    }

    /**
     * Update order to processing status
     */
    private function updateToProcessing($order, int $remainingCount): array
    {
        $order->processed = $order->getOrderRedisKeys()['processed'];
        $order->status = 'processing';
        $order->start_at = now();

        $bjsStatus = $this->bjsCli->changeStatus($order->bjs_id, 'processing');
        if ($bjsStatus) {
            $order->status_bjs = 'processing';
        }

        $remainingUpdated = $this->bjsCli->setRemains($order->bjs_id, $remainingCount);

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
        $order->processed = $order->getOrderRedisKeys()['processed'];
        $order->status = 'completed';
        $order->start_at = $order->start_at ?? now();
        $order->end_at = now();

        $bjsStatus = $this->bjsCli->changeStatus($order->bjs_id, 'completed');
        if ($bjsStatus) {
            $order->status_bjs = 'completed';
        }

        $remainingUpdated = $this->bjsCli->setRemains($order->bjs_id, $remainingCount);

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
        $order->start_at = $order->start_at ?? now();
        $order->end_at = now();
        $order->partial_count = $remainingCount;

        $bjsStatus = $this->bjsCli->setPartial($order->bjs_id, $remainingCount);
        if ($bjsStatus) {
            $order->status_bjs = 'partial';
        }

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
        $order->start_at = $order->start_at ?? now();
        $order->end_at = now();

        $bjsStatus = $this->bjsCli->cancelOrder($order->bjs_id);
        if ($bjsStatus) {
            $order->status_bjs = 'cancel';
        }

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
            $redisData = $this->order->getOrderRedisKeys();

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
}
