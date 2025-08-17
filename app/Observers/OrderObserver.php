<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCache;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Prime the cache with sensible defaults
        OrderCache::setStatus($order, OrderStatus::INPROGRESS->value);
        OrderCache::setFirstInteraction($order, now()->timestamp);

        // Counters start at 0
        // (Redis will auto-initialise to 1 on the first increment,
        //  but we explicitly set 0 so the state object is complete.)
        cache()->putMany([
            OrderCache::key($order, 'processing') => 0,
            OrderCache::key($order, 'processed') => 0,
            OrderCache::key($order, 'failed') => 0,
            OrderCache::key($order, 'duplicate_interaction') => 0,
            OrderCache::key($order, 'requested') => $order->quantity ?? 0,
            OrderCache::key($order, 'fail_reason') => null,
        ]);
    }

    /**
     * Handle the Order "updated" event.
     *
     * @return void
     */
    public function updated(Order $order)
    {
        //
    }

    /**
     * Handle the Order "deleted" event.
     *
     * @return void
     */
    public function deleted(Order $order)
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     *
     * @return void
     */
    public function restored(Order $order)
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Order $order)
    {
        //
    }
}
