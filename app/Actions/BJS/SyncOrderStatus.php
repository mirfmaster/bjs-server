<?php

namespace App\Actions\BJS;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\OrderCacheRepository;
use App\Repositories\OrderState;
use App\Services\BJSService;
use Illuminate\Support\Facades\Log;

class SyncOrderStatus
{
    // TODO:
    // - Check based on cached status
    // - Update only if the BJS update success (consider: update bjs -> model -> cache)
    // - Fetch all orders(even direct)
    //  - first update based on the status
    //   - If direct order && its done => update status to completed
    //   - If BJS order
    //
    //
    //   - Consider that order is updated by the worker
    public function __construct(
        public Order $order,
        public OrderCacheRepository $cache,
        public readonly BJSService $bjsService,
    ) {}

    public function handle()
    {
        Log::info('Syncing order status');
        // TODO: add handler where status != status_bjs
        $orders = $this->order->query()
            ->whereIn('status', ['inprogress', 'processing'])
            ->orderBy('priority', 'desc')
            // ->orderByRaw("array_position(ARRAY['like', 'comment', 'follow'], kind)")
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        foreach ($orders as $order) {
            $state = $this->cache->getState($order->id);
            Log::info('Order processed', [
                'order' => $order->only([
                    'id',
                    'kind',
                    'requested',
                    'bjs_id',
                    'source',
                    'status',
                    'status_bjs',
                ]),
                'state' => $state,
                'remains' => $state->getRemains(),
            ]);

            if (! $state) {
                Log::warn("State is not exist $order->id");

                continue;
            }

            return match ($state->status) {
                OrderStatus::PARTIAL => $this->handlePartial($order, $state),
                OrderStatus::CANCEL => $this->handleCancel($order, $state),
                    // TODO: check INPROGRESS on worker affect the status or not
                    // self::PROCESSING => true,
                default => false,
            };
            Log::info('=================================');
        }
    }

    // handlePartialOrder
    // - Update BJS with try catch
    // - Update model
    private function handlePartial(Order $order, OrderState $state)
    {
        $resultOk = $this->bjsService->bjs->setPartial($order->id, $state->getRemains());
        if (! $resultOk) {
            Log::warning('Failed to update status BJS as Partial');

            return;
        }

        $order->update([
            'status' => OrderStatus::PARTIAL->value,
            'status_bjs' => OrderStatus::PARTIAL->value,
            'partial_count' => $state->getRemains(),
            'end_at' => now(),
        ]);

        Log::info('Succesfully updating status BJS');
    }

    private function handleCancel(Order $order, OrderState $state)
    {
        $resultOk = $this->bjsService->bjs->cancelOrder($order->id);
        if (! $resultOk) {
            Log::warning('Failed to update status BJS');

            return;
        }
        // TODO: check fail reason
        // $this->bjsService->bjs->addCancelReason($order->id, $state->failReason);

        $order->update([
            'status' => OrderStatus::CANCEL->value,
            'status_bjs' => OrderStatus::CANCEL->value,
            'partial_count' => $state->getRemains(),
            'end_at' => now(),
        ]);

        Log::info('Succesfully updating status BJS');
    }
}
