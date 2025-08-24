<?php

namespace App\Console\Commands\BJS;

use App\DTOs\BJS\OrderDTO;
use App\Models\Order;
use App\Services\BJSService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
                if ($status->isCompleted() && ! $force) {
                    $this->warn("Order with BJS ID {$bjsId} is completed in BJS. Use --force to fetch anyway.");
                    $skippedCount++;

                    continue;
                }

                $this->info("Processing order: {$orderDTO->id} - {$orderDTO->serviceName}");

                // Handle different order types
                if ($orderDTO->isLikeOrder()) {
                    $order = $this->handleLikeOrder($orderDTO, $service);
                } elseif ($orderDTO->isFollowOrder()) {
                    $order = $this->handleFollowOrder($orderDTO, $service);
                } else {
                    $this->error("Unsupported order type for BJS ID {$bjsId}");
                    $failureCount++;

                    continue;
                }

                if ($order) {
                    $this->info("Successfully created order with BJS ID {$bjsId} (ID: {$order->id})");
                    $this->info(json_encode($order));
                    $successCount++;
                } else {
                    $this->error("Failed to create order for BJS ID {$bjsId}");
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->error("Failed to fetch order with BJS ID {$bjsId}: " . $e->getMessage());
                Log::error("Fetch failed for BJS ID {$bjsId}", ['error' => $e->getMessage()]);
                $failureCount++;
            }
        }

        $this->info("Fetch completed: {$successCount} succeeded, {$failureCount} failed, {$skippedCount} skipped");

        $this->call('order:cache');

        return $failureCount > 0 ? 1 : 0;
    }

    private function handleLikeOrder(OrderDTO $orderDTO, BJSService $service): ?Order
    {
        // Prepare base order data
        $requested = $orderDTO->count;
        $orderData = [
            'bjs_id' => $orderDTO->id,
            'kind' => 'like',
            'target' => $orderDTO->link,
            'reseller_name' => $orderDTO->user,
            'price' => $orderDTO->charge,
            'requested' => $requested,
            'margin_request' => round($requested + max(10, min(100, $requested * (10 / 100)))),
            'status' => 'inprogress',
            'status_bjs' => 'inprogress',
            'source' => 'bjs',
        ];

        // Get media data
        $shortcode = $service->extractIdentifier($orderDTO->link);
        if (! $shortcode) {
            $this->warn("Could not extract shortcode from link: {$orderDTO->link}");

            return null;
        }

        $mediaInfo = $this->getMediaData($shortcode);
        if (! $mediaInfo->found) {
            $this->warn("Failed to get media info for order {$orderDTO->id}: {$mediaInfo->message}");

            return null;
        }

        // Add media-specific data
        $orderData = array_merge($orderData, [
            'username' => $mediaInfo->owner_username,
            'instagram_user_id' => $mediaInfo->owner_id,
            'media_id' => $mediaInfo->media_id,
            'start_count' => $mediaInfo->like_count,
        ]);

        return Order::create($orderData);
    }

    private function handleFollowOrder(OrderDTO $orderDTO, BJSService $service): ?Order
    {
        // Extract username from link
        $username = $service->extractIdentifier($orderDTO->link);
        if (empty($username)) {
            Log::warning('Username is not valid, skipping...', ['link' => $orderDTO->link]);

            return null;
        }

        /** @var \App\Client\InstagramClient::class * */
        $igCli = app(\App\Client\InstagramClient::class);

        // Fetch profile data
        $info = $igCli->fetchProfile($username);
        Log::debug('Instagram profile data', ['info' => $info]);

        // Check if profile was found
        if (! $info->found) {
            Log::info('User info not found for username: ' . $username);

            return null;
        }

        // Check if user is blacklisted
        if (Redis::sismember('system:order:follow-blacklist', $info->pk)) {
            Log::info('User is blacklisted, skipping follow order', [
                'order_id' => $orderDTO->id,
                'user_id' => $info->pk,
                'username' => $username,
            ]);

            return null;
        }

        // Check if account is private
        if ($info->is_private) {
            Log::info('User account is private, skipping follow order', [
                'order_id' => $orderDTO->id,
                'username' => $username,
            ]);

            return null;
        }

        // Get follower count as start count
        $startCount = $info->follower_count;
        Log::info('Successfully fetched profile data', [
            'username' => $username,
            'follower_count' => $startCount,
        ]);

        // Prepare order data
        $requested = $orderDTO->count;
        $orderData = [
            'bjs_id' => $orderDTO->id,
            'kind' => 'follow',
            'username' => $username,
            'instagram_user_id' => $info->pk,
            'target' => $orderDTO->link,
            'reseller_name' => $orderDTO->user,
            'price' => $orderDTO->charge,
            'start_count' => $startCount,
            'requested' => $requested,
            'margin_request' => round($requested + max(10, min(100, $requested * (10 / 100)))),
            'status' => 'inprogress',
            'status_bjs' => 'inprogress',
            'source' => 'bjs',
        ];

        return Order::create($orderData);
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
