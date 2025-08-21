<?php

namespace App\Console\Commands\BJS;

use App\DTOs\BJS\OrderDTO;
use App\Models\Order;
use App\Services\BJSService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetOrderCommand extends Command
{
    protected $signature = 'bjs:get {bjs_ids* : List of BJS IDs to fetch} {--force : Force fetch even if order is completed}';

    protected $description = 'Fetch orders from BJS and recreate them in the database without updating BJS';

    public function handle(BJSService $service)
    {
        $bjsClient = $service->bjs;
        $bjsIds = $this->argument('bjs_ids');
        $force = $this->option('force');
        $this->info('Starting fetch for ' . count($bjsIds) . ' orders');

        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;

        foreach ($bjsIds as $bjsId) {
            try {
                // Check if order already exists
                if (Order::where('bjs_id', $bjsId)->exists()) {
                    $this->warn("Order with BJS ID {$bjsId} already exists, skipping");
                    $skippedCount++;

                    continue;
                }

                // Get order details from BJS
                $bjsOrder = $bjsClient->getOrderDetail($bjsId);
                if (! $bjsOrder) {
                    $this->error("Order with BJS ID {$bjsId} not found in BJS");
                    $failureCount++;

                    continue;
                }

                // Get order info (current state) from BJS
                $bjsInfo = $bjsClient->getInfo($bjsId);
                if (! isset($bjsInfo->success) || ! $bjsInfo->success) {
                    $this->error("Failed to get order info for BJS ID {$bjsId}");
                    $failureCount++;

                    continue;
                }

                // Create DTO
                $orderDTO = OrderDTO::fromBJSOrder($bjsOrder);
                $this->info(json_encode($orderDTO));

                $status = $orderDTO->getStatusEnum();
                if ($status->isCompleted()) {
                    $this->warn("Order with BJS ID {$bjsId} is completed in BJS. Use --force to fetch anyway.");
                    $skippedCount++;

                    continue;
                }

                $this->info("Processing order: {$orderDTO->id} - {$orderDTO->serviceName}");

                // Prepare base order data
                $orderData = [
                    'bjs_id' => $bjsId,
                    'kind' => $this->determineOrderKind($orderDTO),
                    'target' => $orderDTO->link,
                    'reseller_name' => $orderDTO->user,
                    'price' => $orderDTO->charge,
                    'requested' => $orderDTO->count,
                    'status' => $status->label(),
                    'status_bjs' => $status->label(),
                    'processed' => $orderDTO->getProcessed(),
                    'partial_count' => 0,
                    'start_count' => $orderDTO->startCount,
                    'source' => 'bjs',
                ];

                // If the order is completed, set end_at
                if ($status === 'completed') {
                    $orderData['end_at'] = now();
                }

                // If it's a like order, get media data and add to order data
                if ($orderDTO->isLikeOrder()) {
                    $shortcode = $service->extractIdentifier($orderDTO->link);
                    if ($shortcode) {
                        $mediaInfo = $this->getMediaData($shortcode);
                        if ($mediaInfo->found) {
                            $orderData = array_merge($orderData, [
                                'username' => $mediaInfo->owner_username,
                                'instagram_user_id' => $mediaInfo->owner_id,
                                'media_id' => $mediaInfo->media_id,
                                'start_count' => $mediaInfo->like_count,
                            ]);
                        } else {
                            $this->warn("Failed to get media info for order {$bjsId}: {$mediaInfo->message}");
                        }
                    } else {
                        $this->warn("Could not extract shortcode from link: {$orderDTO->link}");
                    }
                } else {
                    $this->error('Order Follwo not handled yet');

                    continue;
                }

                // Create the order
                $order = Order::create($orderData);

                $this->info("Successfully created order with BJS ID {$bjsId} (ID: {$order->id})");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("Failed to fetch order with BJS ID {$bjsId}: " . $e->getMessage());
                Log::error("Fetch failed for BJS ID {$bjsId}", ['error' => $e->getMessage()]);
                $failureCount++;
            }
        }

        $this->info("Fetch completed: {$successCount} succeeded, {$failureCount} failed, {$skippedCount} skipped");

        return $failureCount > 0 ? 1 : 0;
    }

    // TODO: dont use this, just use dto helper, create dto handler for check whether its handled service
    private function determineOrderKind(OrderDTO $orderDTO): string
    {
        if ($orderDTO->isLikeOrder()) {
            return 'like';
        }

        if ($orderDTO->isFollowOrder()) {
            return 'follow';
        }

        // Fallback based on service ID
        $serviceId = $orderDTO->serviceId;
        if ($serviceId >= 1 && $serviceId <= 10) {
            return 'like';
        } elseif ($serviceId >= 11 && $serviceId <= 20) {
            return 'follow';
        }

        return 'unknown';
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
