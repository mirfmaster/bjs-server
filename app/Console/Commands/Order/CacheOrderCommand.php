<?php

namespace App\Console\Commands\Order;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CacheOrderCommand extends Command
{
    protected $signature = 'order:cache';

    protected $description = 'Cache pending orders, let worker take based on their config';

    private const LIKE_LIMIT = 15;

    private const FOLLOW_LIMIT = 3;

    private const CACHE_KEY_LIKE = 'order:list:like';

    private const CACHE_KEY_FOLLOW = 'order:list:follow';

    private Order $order;

    private Carbon $ttl;

    public function __construct(Order $order)
    {
        parent::__construct();

        $this->order = $order;
        // two months from now
        $this->ttl = Carbon::now()->addMonths(2);
    }

    public function handle(): int
    {
        $likeOrders = $this->cacheLikeOrders();
        $followOrders = $this->cacheFollowOrders();

        // Merge and ensure detail keys for each order
        $all = $likeOrders->concat($followOrders);
        $this->ensureDetailCache($all);

        $this->info('Cached like & follow lists + per-order detail keys (2-month TTL).');
        $this->info('Total orders: ' . count($all));

        return Command::SUCCESS;
    }

    protected function cacheLikeOrders(): Collection
    {
        $orders = $this->order
            ->whereIn('status', ['inprogress', 'processing'])
            ->where('kind', 'like')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit(self::LIKE_LIMIT)
            ->get();

        Cache::tags(['orders', 'list'])
            ->put(self::CACHE_KEY_LIKE, $orders, $this->ttl);

        return $orders;
    }

    // TODO: make the inprogress as a way to manage search detail of orders (user / media)
    protected function cacheFollowOrders(): Collection
    {
        $orders = $this->order
            ->whereIn('status', ['inprogress', 'processing'])
            ->where('kind', 'follow')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit(self::FOLLOW_LIMIT)
            ->get();

        Cache::tags(['orders', 'list'])
            ->put(self::CACHE_KEY_FOLLOW, $orders, $this->ttl);

        return $orders;
    }

    private function ensureDetailCache(Collection $orders): void
    {
        foreach ($orders as $order) {
            $id = $order->id;
            $tags = ['orders', "order:{$id}"];
            $key = "order:{$id}:status";

            // if status key already exists, assume detail is cached
            if (Cache::tags($tags)->has($key)) {
                continue;
            }

            $this->createDetailCache($order, $tags);
        }
    }

    private function createDetailCache(Order $order, array $tags): void
    {
        $id = $order->id;
        $requested = $order->requested;

        Cache::tags($tags)
            ->put("order:{$id}:status", $order->status, $this->ttl);
        Cache::tags($tags)
            ->put("order:{$id}:processing", 0, $this->ttl);
        Cache::tags($tags)
            ->put("order:{$id}:processed", 0, $this->ttl);
        Cache::tags($tags)
            ->put("order:{$id}:failed", 0, $this->ttl);
        Cache::tags($tags)
            ->put("order:{$id}:duplicate_interaction", 0, $this->ttl);
        Cache::tags($tags)
            ->put("order:{$id}:requested", $requested, $this->ttl);
        Cache::tags($tags)
            ->put("order:{$id}:fail_reason", '', $this->ttl);
    }
}
