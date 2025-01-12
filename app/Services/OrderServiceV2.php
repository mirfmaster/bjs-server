<?php

namespace App\Services;

use App\Consts\OrderConst;
use App\Models\Order;

class OrderServiceV2
{
    public function __construct(public Order $order) {}

    public function canProcessFollowOrder(string $username): bool
    {
        $todayOrders = $this->order->query()
            ->where('username', $username)
            ->where('kind', 'follow')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return $todayOrders < OrderConst::MAX_FOLLOW;
    }

    public function canProcessLikeOrder(string $mediaId): bool
    {
        $todayOrders = $this->order->query()
            ->where('media_id', $mediaId)
            ->where('kind', 'like')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return $todayOrders < OrderConst::MAX_LIKE;
    }
}
