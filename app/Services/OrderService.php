<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Redis;

class OrderService
{
    private $id;
    public function __construct(
        private Order $order,
        private Redis $redis
    ) {
        $this->redis = $redis;
    }

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
        $this->redis->set("order:$id:status", "inprogress");
        $this->redis->set("order:$id:processing", 0);
        $this->redis->set("order:$id:processed", 0);
        $this->redis->set("order:$id:failed", 0);
        $this->redis->set("order:$id:duplicate_interaction", 0);
        $this->redis->set("order:$id:requested", $requested);
    }


    /**
     * Get all Redis keys associated with an order
     *
     * @param int $id Order ID
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
            "order:$this->id:requested"
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
            'requested' => (int) $values[5]
        ];
    }
    /**
     * Delete all Redis keys associated with an order
     *
     * @param int $id Order ID
     * @return int Number of keys deleted
     */
    public function deleteOrderRedisKeys(int $id): int
    {
        $keys = [
            "order:$id:status",
            "order:$id:processing",
            "order:$id:processed",
            "order:$id:duplicate_interaction",
            "order:$id:requested"
        ];

        return $this->redis->del($keys);
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

    public function updateCache()
    {
        $orders = $this->getOrders();

        $this->redis->set("order:lists", serialize($orders));
    }

    public function getCachedOrders()
    {
        return $this->redis->set("order:lists") ?? [];
    }

    public function isBlacklisted($pkID)
    {
        return $this->redis->sismember("system:follow-blacklist", $pkID);
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

    public function setStatusRedis($status)
    {
        $this->redis->set("order:$this->id:status", $status);
    }
}
