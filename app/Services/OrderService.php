<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class OrderService
{
    private $id;

    public function __construct(
        private Order $order
    ) {}

    public function createAndUpdateCache(array $data)
    {
        $created = $this->order->create($data);
        $this->createRedisKey($created->id, $data['requested']);
        $this->updateCache();

        return $created;
    }

    public function findBJSID($id)
    {
        return $this->order->query()->where('bjs_id', $id)->limit(1)->first();
    }

    private function createRedisKey($id, $requested): void
    {
        Redis::set("order:$id:status", 'inprogress');
        Redis::set("order:$id:processing", 0);
        Redis::set("order:$id:processed", 0);
        Redis::set("order:$id:failed", 0);
        Redis::set("order:$id:duplicate_interaction", 0);
        Redis::set("order:$id:requested", $requested);
    }

    /**
     * Get all Redis keys associated with an order
     *
     * @param  int  $id  Order ID
     * @return array Associative array of all order Redis data
     */
    public function getOrderRedisKeys(): array
    {
        $keys = [
            "order:$this->id:status",
            "order:$this->id:processing",
            "order:$this->id:processed",
            "order:$this->id:failed",
            "order:$this->id:duplicate_interaction",
            "order:$this->id:requested",
        ];

        $values = Redis::pipeline(function ($pipe) use ($keys) {
            foreach ($keys as $key) {
                $pipe->get($key);
            }
        });

        return [
            'status' => $values[0],
            'processing' => (int) $values[1],
            'processed' => (int) $values[2],
            'failed' => (int) $values[3],
            'duplicate_interaction' => (int) $values[4],
            'requested' => (int) $values[5],
        ];
    }

    public function getCachedState(): array
    {
        $tags = ["order:{$this->id}"];

        // list out the exact cache‐keys you want
        $cacheKeys = [
            "order:{$this->id}:status",
            "order:{$this->id}:processing",
            "order:{$this->id}:processed",
            "order:{$this->id}:failed",
            "order:{$this->id}:duplicate_interaction",
            "order:{$this->id}:requested",
        ];

        // batch‐pull them
        $raw = Cache::tags($tags)->many($cacheKeys);

        // map them into your nice array, casting ints where needed
        return [
            'status' => $raw[$cacheKeys[0]] ?? null,
            'processing' => (int) ($raw[$cacheKeys[1]] ?? 0),
            'processed' => (int) ($raw[$cacheKeys[2]] ?? 0),
            'failed' => (int) ($raw[$cacheKeys[3]] ?? 0),
            'duplicate_interaction' => (int) ($raw[$cacheKeys[4]] ?? 0),
            'requested' => (int) ($raw[$cacheKeys[5]] ?? 0),
        ];
    }

    /**
     * Delete all Redis keys associated with an order
     *
     * @param  int  $id  Order ID
     * @return int Number of keys deleted
     */
    public function deleteOrderRedisKeys(int $id): int
    {
        $keys = [
            "order:$id:status",
            "order:$id:processing",
            "order:$id:processed",
            "order:$id:duplicate_interaction",
            "order:$id:requested",
            "order:$id:failed",
            "order:$id:first_interaction",
        ];

        return Redis::del($keys);
    }

    public function getOrders()
    {
        return $this->order
            ->whereIn('status', ['inprogress', 'processing'])
            ->orderBy('priority', 'desc')
            ->orderByRaw("array_position(ARRAY['like', 'comment', 'follow'], kind)")
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get();
    }

    public function getOrdersV2()
    {
        $likeOrders = $this->order
            ->whereIn('status', ['inprogress', 'processing'])
            ->where('kind', 'like')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit(8)
            ->get();

        $storyOrders = $this->order
            ->whereIn('status', ['inprogress', 'processing'])
            ->where('kind', 'story')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit(4)
            ->get();

        $commentOrders = $this->order
            ->whereIn('status', ['inprogress', 'processing'])
            ->where('kind', 'comment')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit(3)
            ->get();

        $followOrders = $this->order
            ->whereIn('status', ['inprogress', 'processing'])
            ->where('kind', 'follow')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit(1)
            ->get();

        // Merge all collections
        return $likeOrders->concat($storyOrders)
            ->concat($commentOrders)
            ->concat($followOrders);
    }

    public function updateCache()
    {
        $orders = $this->getOrdersV2();

        Redis::set('order:lists', serialize($orders));
    }

    public function getCachedOrders()
    {
        $lists = Redis::get('order:lists');

        return $lists ? unserialize($lists) : collect([]);
    }

    public function isBlacklisted($pkID)
    {
        return Redis::sismember('system:order:follow-blacklist', $pkID);
    }

    public function getCurrentProccessed()
    {
        return $this->order->query()
            ->whereNotIn('status', ['cancel', 'finished', 'partial'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function setOrderID($id)
    {
        $this->id = $id;
    }

    public function getRemains(): int
    {
        $processed = Redis::get("order:$this->id:processed") ?? 0;
        $requested = Redis::get("order:$this->id:requested") ?? 0;

        return $requested - $processed;
    }

    public function evaluateOrderStatus(): string
    {
        $processed = Redis::get("order:$this->id:processed") ?? 0;
        $requested = Redis::get("order:$this->id:requested") ?? 0;
        if ($processed >= $requested) {
            return 'completed';
        }

        return $processed > 0 ? 'partial' : 'cancel';
    }

    public function setStatusRedis($status)
    {
        Redis::set("order:$this->id:status", $status);
    }

    public function getOutOfSyncOrders()
    {
        return Order::query()
            ->whereColumn('status', '!=', 'status_bjs')
            ->where('status', '!=', 'pending')
            ->where('status', '==', 'bjs')
            ->get();
    }
}
