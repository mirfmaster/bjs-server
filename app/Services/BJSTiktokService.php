<?php

namespace App\Services;

use App\Client\BJSClient;
use App\Consts\BJSConst;
use App\Dto\BJSTiktokOrderDto;
use App\Repository\RedisTiktokRepository;
use App\Traits\LoggerTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BJSTiktokService
{
    use LoggerTrait;

    public $services = [147];

    public function __construct(
        public BJSClient $cli,
        protected RedisTiktokRepository $repository
    ) {}

    public function fetch()
    {
        Log::info('======================');
        $context = ['process' => 'fetch-tiktok'];
        $auth = $this->cli->authenticate();
        if (! $auth) {
            Log::warning('Failed to authenticate BJS', $context);

            return;
        }

        Log::info('Fetching order', $context);
        $totalOrders = 0;
        $newOrders = 0;

        foreach ($this->services as $serviceID) {
            $processingResult = $this->fetchOrders($serviceID);
            $totalOrders += $processingResult['total'];
            $newOrders += $processingResult['new'];
        }

        Log::info('Fetching completed', [
            'total_orders' => $totalOrders,
            'new_orders' => $newOrders,
        ]);

        return [
            'total' => $totalOrders,
            'new' => $newOrders,
        ];
    }

    private function fetchOrders($serviceID)
    {
        $result = [
            'total' => 0,
            'new' => 0,
            'resolved' => 0,
        ];

        Log::info('Start processing', ['serviceID' => $serviceID]);
        $orders = $this->cli->getOrders($serviceID, BJSConst::PROCESSING);

        $result['total'] = count($orders);

        foreach ($orders as $order) {
            try {
                // First try to extract video ID
                $videoData = $this->getVideoData($order->link);

                if (! $videoData) {
                    Log::warning('Unable to resolve video ID', [
                        'order_id' => $order->id,
                        'link' => $order->link,
                    ]);

                    continue;
                }

                // Add video ID to order data
                $orderData = (array) $order;
                $orderData['video_id'] = $videoData['video_id'];

                if ($videoData['resolved']) {
                    $orderData['resolved_url'] = $videoData['resolved_url'];
                    $result['resolved']++;
                }

                // Convert to DTO
                $orderDto = BJSTiktokOrderDto::fromArray($orderData);

                // Store in Redis - returns false if already exists
                $stored = $this->repository->storePendingOrder($orderDto);

                if ($stored) {
                    $result['new']++;
                    Log::info('New order stored', [
                        'order_id' => $orderDto->id,
                        'user' => $orderDto->user,
                        'link' => $orderDto->link,
                        'video_id' => $orderDto->video_id,
                    ]);
                }
            } catch (\Exception $e) {
                $this->logError($e, [
                    'order_id' => $order->id ?? 'unknown',
                    'service_id' => $serviceID,
                    'link' => $order->link ?? 'unknown',
                ]);
            }
        }

        return $result;
    }

    /**
     * Process completed orders - move them from processed to completed and update BJS
     *
     * @return array Information about processed orders
     */
    public function processCompletedOrders(): array
    {
        $context = ['process' => 'complete-tiktok-orders'];
        Log::info('Starting to process completed TikTok orders', $context);

        // Authenticate with BJS
        $auth = $this->cli->authenticate();
        if (! $auth) {
            Log::warning('Failed to authenticate BJS when processing completed orders', $context);

            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'auth_failed' => true,
            ];
        }

        // Get all processed orders
        $processedOrders = $this->repository->getProcessedOrderDtos();

        if (empty($processedOrders)) {
            Log::info('No processed orders found to complete', $context);

            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
            ];
        }

        Log::info('Found ' . count($processedOrders) . ' processed orders to complete', $context);

        $result = [
            'total' => count($processedOrders),
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($processedOrders as $order) {
            try {
                // $success = $this->completeOrder($order);
                $success = true;

                if ($success) {
                    $result['success']++;
                } else {
                    $result['failed']++;
                }
            } catch (\Exception $e) {
                $result['failed']++;
                $this->logError($e, [
                    'order_id' => $order->id,
                    'link' => $order->link,
                ]);
            }
        }

        Log::info('Completed processing TikTok orders', [
            'total' => $result['total'],
            'success' => $result['success'],
            'failed' => $result['failed'],
        ]);

        return $result;
    }

    /**
     * Complete a single order in BJS and move it to completed list
     *
     * @param  BJSTiktokOrderDto  $order  The order to complete
     * @return bool Whether the operation was successful
     */
    private function completeOrder(BJSTiktokOrderDto $order): bool
    {
        $context = [
            'order_id' => $order->id,
            'link' => $order->link,
            'video_id' => $order->video_id,
        ];

        Log::info('Processing order for completion', $context);

        // Skip if not actually completed
        if (! $order->isCompleted()) {
            Log::info('Order not marked as completed yet, skipping', $context);

            return false;
        }

        // Skip if already completed in BJS
        if ($order->isCompletedInBJS()) {
            Log::info('Order already completed in BJS, moving to completed list', $context);
            $this->repository->moveToCompleted($order->id);

            return true;
        }

        // 1. Set remains to 0 (fully completed)
        $remainsSuccess = $this->cli->setRemains($order->id, 0);
        if (! $remainsSuccess) {
            Log::warning('Failed to set remains for order', $context);

            return false;
        }

        // 2. Change status to completed in BJS
        $statusSuccess = $this->cli->changeStatus($order->id, 'completed');
        if (! $statusSuccess) {
            Log::warning('Failed to change status to completed for order', $context);

            return false;
        }

        // 3. Mark as completed in our system
        $order->markCompletedInBJS();

        // 4. Move to completed list
        $movedOrder = $this->repository->moveToCompleted($order->id);

        if (! $movedOrder) {
            Log::warning('Failed to move order to completed list', $context);

            return false;
        }

        Log::info('Successfully completed order in BJS and moved to completed list', [
            'order_id' => $order->id,
            'bjs_completed_at' => $order->bjs_completed_at,
        ]);

        return true;
    }

    public function getPendingOrders()
    {
        return $this->repository->getPendingOrderDtos();
    }

    public function getVideoData($url)
    {
        // Already a direct video URL with ID
        if (preg_match('/tiktok\.com\/@[\w\.]+\/video\/(\d+)/', $url, $matches)) {
            return [
                'video_id' => $matches[1],
                'resolved' => false,
            ];
        }

        // Direct video ID (if someone just puts the number)
        if (preg_match('/^(\d{15,20})$/', $url)) {
            return [
                'video_id' => $url,
                'resolved' => false,
            ];
        }

        // Need to resolve short URLs (vm.tiktok.com, vt.tiktok.com)
        try {
            $response = Http::timeout(5)->get('https://og.metadata.vision/' . $url);

            if (! $response->successful()) {
                Log::warning('Failed to resolve TikTok URL', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $json = $response->json();

            if (! isset($json['data']['url'])) {
                Log::warning('Failed to extract resolved URL from metadata response', [
                    'url' => $url,
                ]);

                return null;
            }

            $resolvedUrl = $json['data']['url'];

            // Extract video ID from the resolved URL
            if (preg_match('/tiktok\.com\/@[\w\.]+\/video\/(\d+)/', $resolvedUrl, $matches)) {
                return [
                    'video_id' => $matches[1],
                    'resolved' => true,
                    'resolved_url' => $resolvedUrl,
                    'author' => $json['data']['author'] ?? null,
                    'title' => $json['data']['title'] ?? null,
                ];
            }

            Log::warning('Could not extract video ID from resolved URL', [
                'url' => $url,
                'resolved_url' => $resolvedUrl,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error resolving TikTok URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

