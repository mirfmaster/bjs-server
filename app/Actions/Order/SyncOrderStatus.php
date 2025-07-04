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
    ) {}

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
        Log::info('Syncing ' . count($orders) . ' orders status');

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
            default => Log::warning('STATUS STATE IS NOT RECOGNIZED: ' . $state->status->value),
        };
    }

    private function handleDirect(Order $order)
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
            'remains' => $state?->getRemains(),
        ]);

        if (! $state) {
            Log::warning("State does not exist for {$order->id}");

            return;
        }

        $this->updateModelOnly($order, $state);

        Log::info('Successfully updated direct‐source model only');
    }

    private function handlePartial(Order $order, OrderState $state)
    {
        $ok = $this->bjsService->bjs
            ->setPartial($order->bjs_id, $state->getRemains());
        if (! $ok) {
            Log::warning('Failed to set BJS Partial');

            return;
        }

        $this->updateModelOnly($order, $state);

        Log::info('Successfully updated BJS + model only for Partial');
    }

    private function handleCancel(Order $order, OrderState $state)
    {
        $ok = $this->bjsService->bjs->cancelOrder($order->bjs_id);
        if (! $ok) {
            Log::warning('Failed to cancel in BJS');

            return;
        }

        $this->updateModelOnly($order, $state);

        Log::info('Successfully updated BJS + model only for Cancel');
    }

    private function handleInProgress(Order $order, OrderState $state)
    {
        // on InProgress we only update the model _if_ it’s time
        $this->updateModelOnly($order, $state);
        $remains = $state->getRemains();
        if ($remains <= 0) {
            $this->cache->setStatus(
                $order->id,
                OrderStatus::COMPLETED->value
            );
            Log::info(
                "Order {$order->id} inprogress→completed (cache only)"
            );
        }
    }

    private function handleProcessing(Order $order, OrderState $state)
    {
        $this->bjsService->bjs->setRemains(
            $order->bjs_id,
            $state->getRemains()
        );
        $ok = $this->bjsService->bjs
            ->changeStatus($order->bjs_id, OrderStatus::PROCESSING->value);
        if (! $ok) {
            Log::warning('Failed to change status to Processing in BJS');

            return;
        }

        $this->updateModelOnly($order, $state);

        Log::info('Successfully updated BJS + model only for Processing');
    }

    private function handleCompleted(Order $order, OrderState $state)
    {
        $this->bjsService->bjs->setRemains(
            $order->bjs_id,
            $state->getCompletedRemains()
        );
        $ok = $this->bjsService->bjs
            ->changeStatus($order->bjs_id, OrderStatus::COMPLETED->value);
        if (! $ok) {
            Log::warning('Failed to change status to Completed in BJS');

            return;
        }

        $this->updateModelOnly($order, $state);

        Log::info('Successfully updated BJS + model only for Completed');
    }

    /**
     * Apply _only_ the Eloquent‐model updates for a given state.
     * Does _not_ call BJS at all.
     */
    public function updateModelOnly(Order $order, OrderState $state): void
    {
        $remains = $state->getRemains();

        switch ($state->status) {
            case OrderStatus::INPROGRESS:
                if ($remains <= 0) {
                    Log::info("Model-only: {$order->id} inprogress→completed");
                    $order->update([
                        'processed' => $state->processed,
                        'status' => OrderStatus::COMPLETED->value,
                        'status_bjs' => OrderStatus::COMPLETED->value,
                        'partial_count' => $state->getCompletedRemains(),
                        'end_at' => now(),
                    ]);
                }
                break;

            case OrderStatus::PROCESSING:
                $ts = Carbon::createFromTimestamp(
                    $state->firstInteraction
                )->format('Y-m-d H:i:s');
                Log::info("Model-only: {$order->id} processing");
                $order->update([
                    'processed' => $state->processed,
                    'started_at' => $ts,
                    'status' => OrderStatus::PROCESSING->value,
                    'status_bjs' => OrderStatus::PROCESSING->value,
                    'partial_count' => $remains,
                ]);
                break;

            case OrderStatus::PARTIAL:
                Log::info("Model-only: {$order->id} partial");
                $order->update([
                    'processed' => $state->processed,
                    'status' => OrderStatus::PARTIAL->value,
                    'status_bjs' => OrderStatus::PARTIAL->value,
                    'partial_count' => $remains,
                    'end_at' => now(),
                ]);
                break;

            case OrderStatus::CANCEL:
                Log::info("Model-only: {$order->id} cancel");
                $order->update([
                    'processed' => $state->processed,
                    'status' => OrderStatus::CANCEL->value,
                    'status_bjs' => OrderStatus::CANCEL->value,
                    'partial_count' => $remains,
                    'end_at' => now(),
                ]);
                break;

            case OrderStatus::COMPLETED:
                Log::info("Model-only: {$order->id} completed");
                $order->update([
                    'processed' => $state->processed,
                    'status' => OrderStatus::COMPLETED->value,
                    'status_bjs' => OrderStatus::COMPLETED->value,
                    'partial_count' => $state->getCompletedRemains(),
                    'end_at' => now(),
                ]);
                break;

            default:
                Log::warning(
                    "Model-only: {$order->id} unhandled status "
                        . $state->status->value
                );
        }
    }
}
