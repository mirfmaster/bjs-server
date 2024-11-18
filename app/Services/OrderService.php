<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Redis;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class OrderService
{
    private $redis;

    public function __construct(
        private Order $order,
        RedisFactory $redis
    ) {
        $this->redis = $redis;
    }

    public function create(array $data)
    {
        $created = $this->order->create($data);
        $this->createRedisKey($created->id, $data['requested']);
        $this->updateCache();

        return $created;
    }

    private function createRedisKey($id, $requested): void
    {
        $this->redis->set("order:$id:status", "inprogress");
        $this->redis->set("order:$id:processing", 0);
        $this->redis->set("order:$id:processed", 0);
        $this->redis->set("order:$id:duplicate_interaction", 0);
        $this->redis->set("order:$id:requested", $requested);
    }

    public function updateCache()
    {
        $orders = $this->order
            ->whereIn('status', ['pending', 'inprogress', 'processing'])
            ->orderBy('priority', 'desc')
            ->orderByRaw("array_position(ARRAY['like', 'comment', 'follow'], kind)")
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get();

        $this->redis->set("order:lists", serialize($orders));
    }
}
