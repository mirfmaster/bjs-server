<?php

namespace Database\Seeders;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Redis;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing Redis keys related to orders
        $this->clearExistingRedisKeys();

        // Sample data for different types of orders
        $orders = [
            [
                'username' => 'user1',
                'kind' => 'like',
                'instagram_user_id' => '123456789',
                'media_id' => 'abc123',
                'target' => 'https://www.instagram.com/p/abc123',
                'requested' => 1000,
                'margin_request' => 1100,
                'start_count' => 500,
                'processed' => 800,
                'partial_count' => 0,
                'bjs_id' => 1001,
                'priority' => 1,
                'status' => 'processing',
                'status_bjs' => 'processing',
                'started_at' => Carbon::now()->subHours(2),
                'source' => 'bjs',
            ],
            [
                'username' => 'user2',
                'kind' => 'follow',
                'instagram_user_id' => '987654321',
                'target' => 'https://www.instagram.com/user2',
                'requested' => 500,
                'margin_request' => 550,
                'start_count' => 1000,
                'processed' => 500,
                'partial_count' => 0,
                'bjs_id' => 1002,
                'priority' => 2,
                'status' => 'completed',
                'status_bjs' => 'completed',
                'started_at' => Carbon::now()->subDay(),
                'end_at' => Carbon::now()->subHours(12),
                'source' => 'bjs',
            ],
            [
                'username' => 'user3',
                'kind' => 'like',
                'instagram_user_id' => '456789123',
                'media_id' => 'def456',
                'target' => 'https://www.instagram.com/p/def456',
                'requested' => 2000,
                'margin_request' => 2200,
                'start_count' => 800,
                'processed' => 1500,
                'partial_count' => 500,
                'bjs_id' => 1003,
                'priority' => 0,
                'status' => 'partial',
                'status_bjs' => 'partial',
                'started_at' => Carbon::now()->subHours(6),
                'end_at' => Carbon::now()->subHour(),
                'source' => 'bjs',
            ],
            [
                'username' => 'user4',
                'kind' => 'follow',
                'instagram_user_id' => '741852963',
                'target' => 'https://www.instagram.com/user4',
                'requested' => 1500,
                'margin_request' => 1650,
                'start_count' => 2000,
                'processed' => 0,
                'partial_count' => 0,
                'bjs_id' => 1004,
                'priority' => 1,
                'status' => 'inprogress',
                'status_bjs' => 'inprogress',
                'source' => 'bjs',
            ],
        ];

        foreach ($orders as $orderData) {
            // Create order in database
            $order = Order::create($orderData);

            // Create corresponding Redis keys
            $this->createRedisKeys($order);
        }
    }

    /**
     * Clear existing Redis keys related to orders
     */
    private function clearExistingRedisKeys(): void
    {
        $keys = Redis::keys('order:*');
        if (! empty($keys)) {
            Redis::del($keys);
        }
    }

    /**
     * Create Redis keys for an order
     */
    private function createRedisKeys(Order $order): void
    {
        // Set Redis keys with 24 hour expiry
        $expiry = 86400; // 24 hours in seconds

        Redis::setex("order:{$order->id}:status", $expiry, $order->status);
        Redis::setex("order:{$order->id}:processing", $expiry, 0);
        Redis::setex("order:{$order->id}:processed", $expiry, $order->processed);
        Redis::setex("order:{$order->id}:failed", $expiry, 0);
        Redis::setex("order:{$order->id}:duplicate_interaction", $expiry, 0);
        Redis::setex("order:{$order->id}:requested", $expiry, $order->requested);

        // Update the order list in Redis
        $orders = Order::whereIn('status', ['inprogress', 'processing'])
            ->orderBy('priority', 'desc')
            ->orderByRaw("array_position(ARRAY['like', 'comment', 'follow'], kind)")
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get();

        Redis::setex('order:lists', $expiry, serialize($orders));
    }
}

