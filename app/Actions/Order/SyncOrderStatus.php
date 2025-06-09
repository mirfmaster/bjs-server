<?php

namespace App\Actions\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\OrderCacheRepository;
use App\Repositories\OrderState;
use App\Services\BJSService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncOrderStatus
{
    public function __construct(
        public Order $order,
        public OrderCacheRepository $cache,
        public readonly BJSService $bjsService,
    ) {
    }

    // TODO: the actions only handle BJS status, extract get query, direct order handler to upper layer
    public function handle()
    {
        $like = $this->order->query()
            ->whereIn('status_bjs', ['inprogress', 'processing'])
            ->orderBy('priority', 'desc')
            ->where('kind', 'like')
            ->orderBy('created_at', 'asc')
            // TODO: extract this into single constants
            ->limit(20)
            ->get();
        $follow = $this->order->query()
            ->whereIn('status_bjs', ['inprogress', 'processing'])
            ->orderBy('priority', 'desc')
            ->where('kind', 'follow')
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get();
        $orders = $like->merge($follow);
        Log::info('Syncing '.count($orders).' orders status');

        foreach ($orders as $order) {
            match ($order->source) {
                'bjs' => $this->handleBJS($order),
                'direct' => $this->handleDirect($order),
                default => Log::warning("ORDER IS NOT RECOGNIZED: $order->source "),
            };
            Log::info('=================================');
        }
    }

    private function handleBJS($order)
    {
        $state = $this->cache->getState($order->id);
        Log::info('Order bjs processed', [
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

        if ($state->status == OrderStatus::UNKNOWN) {
            Log::warning("State is not exist $order->id");

            return;
        }

        match ($state->status) {
            OrderStatus::INPROGRESS => $this->handleInProgress($order, $state),
            OrderStatus::PROCESSING => $this->handleProcessing($order, $state),
            OrderStatus::PARTIAL => $this->handlePartial($order, $state),
            OrderStatus::CANCEL => $this->handleCancel($order, $state),
            OrderStatus::COMPLETED => $this->handleCompleted($order, $state),
            default => Log::warning('STATUS STATE IS NOT RECOGNIZED: '.$state->status->value),
        };
    }

    private function handleDirect($order)
    {
        $state = $this->cache->getState($order->id);
        Log::info('Order direct processed', [
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
            Log::warning("State is not exist $order->id");

            return;
        }

        $order->update([
            'status' => $state->status->value,
            'status_bjs' => $state->status->value,
            'partial_count' => $state->getRemains(),
            'end_at' => now(),
        ]);

        Log::info('Succesfully updating status direct source');
    }

    // LIMIT of source function handler

    private function handlePartial(Order $order, OrderState $state)
    {
        $resultOk = $this->bjsService->bjs->setPartial($order->bjs_id, $state->getRemains());
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
        $resultOk = $this->bjsService->bjs->cancelOrder($order->bjs_id);
        if (! $resultOk) {
            Log::warning('Failed to update status BJS');

            return;
        }
        // TODO: check fail reason
        // $this->bjsService->bjs->addCancelReason($order->bjs_id, $state->failReason);

        $order->update([
            'status' => OrderStatus::CANCEL->value,
            'status_bjs' => OrderStatus::CANCEL->value,
            'partial_count' => $state->getRemains(),
            'end_at' => now(),
        ]);

        Log::info('Succesfully updating status BJS');
    }

    // Extra handler in case worker cannot update the status, this should be only updating the status
    private function handleInProgress(Order $order, OrderState $state)
    {
        $remains = $state->getRemains();
        if ($remains <= 0) {
            Log::info('Order completed and yet the status is progress, moving to completed status');
            // only update the status and let the handleCompleted handle the rest
            $this->cache->setStatus($order->id, OrderStatus::COMPLETED->value);

            return;
        }
    }

    private function handleProcessing(Order $order, OrderState $state)
    {
        // In case we do fetch profile by worker
        // $resultOk = $this->bjsService->bjs->setStartCount($order->bjs_id);

        $this->bjsService->bjs->setRemains($order->bjs_id, $state->getRemains());
        $resultOk = $this->bjsService->bjs->changeStatus($order->bjs_id, OrderStatus::PROCESSING->value);
        if (! $resultOk) {
            Log::warning('Failed to update status BJS');

            return;
        }
        $ts = Carbon::createFromTimestamp(
            $state->firstInteraction
        )->format('Y-m-d H:i:s');
        $order->update([
            'started_at' => $ts,
            'status' => OrderStatus::PROCESSING->value,
            'status_bjs' => OrderStatus::PROCESSING->value,
            'partial_count' => $state->getRemains(),
        ]);

        Log::info('Succesfully updating status BJS');
    }

    private function handleCompleted(Order $order, OrderState $state)
    {
        $this->bjsService->bjs->setRemains($order->bjs_id, $state->getCompletedRemains());
        $resultOk = $this->bjsService->bjs->changeStatus($order->bjs_id, OrderStatus::COMPLETED->value);
        if (! $resultOk) {
            Log::warning('Failed to update status BJS');

            return;
        }

        $order->update([
            'status' => OrderStatus::COMPLETED->value,
            'status_bjs' => OrderStatus::COMPLETED->value,
            'partial_count' => $state->getCompletedRemains(),
            'end_at' => now(),
        ]);

        Log::info('Succesfully updating status BJS');
    }
}
