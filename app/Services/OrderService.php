<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Redis;

class OrderService
{
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
