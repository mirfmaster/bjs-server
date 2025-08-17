<?php

namespace App\Console\Commands\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCache;
use App\Models\OrderState;
use Illuminate\Console\Command;
use UnitEnum;

class UpdateStatusStateCommand extends Command
{
    protected $signature = <<<'SIG'
order:update-state
    {orders?* : One or more order IDs or "all" for every pending order}
    {--status= : Set the order status (must be a valid OrderStatus value)}
    {--processing= : Set the "processing" counter}
    {--processed= : Set the "processed" counter}
    {--duplicate-interaction= : Set the "duplicate_interaction" counter}
    {--failed= : Set the "failed" counter}
    {--first-interaction= : Set the "first_interaction" timestamp}
    {--requested= : Set the "requested" counter}
    {--fail-reason= : Set the "fail_reason" string}
    {--dry-run : Do not actually write to cache; just show diffs}
SIG;

    protected $description = 'Mass-update order cache states, with before/after and optional dry-run';

    public function handle()
    {
        $orderIds = $this->argument('orders');
        $dry = $this->option('dry-run');

        // Build the list of orders to touch
        $orders = $orderIds === ['all'] || empty($orderIds)
            ? Order::cursor()
            : Order::whereIn('id', $orderIds)->cursor();

        // Map CLI options to cache suffixes
        $map = [
            'status' => 'status',
            'processing' => 'processing',
            'processed' => 'processed',
            'duplicate-interaction' => 'duplicate_interaction',
            'failed' => 'failed',
            'first-interaction' => 'first_interaction',
            'requested' => 'requested',
            'fail-reason' => 'fail_reason',
        ];

        $updates = [];
        foreach ($map as $option => $suffix) {
            $value = $this->option($option);
            if (! is_null($value) && $value !== '') {
                $updates[$suffix] = $value;
            }
        }

        if (empty($updates)) {
            $this->error('No update options provided. Nothing to do.');

            return Command::INVALID;
        }

        foreach ($orders as $order) {
            /** @var Order $order */
            $before = $order->state();

            // Build the new state
            try {
                $after = new OrderState(
                    id: $before->id,
                    status: isset($updates['status'])
                        ? OrderStatus::from($updates['status'])
                        : $before->status,
                    processing: isset($updates['processing'])
                        ? (int) $updates['processing']
                        : $before->processing,
                    processed: isset($updates['processed'])
                        ? (int) $updates['processed']
                        : $before->processed,
                    duplicateInteraction: isset($updates['duplicate_interaction'])
                        ? (int) $updates['duplicate_interaction']
                        : $before->duplicateInteraction,
                    failed: isset($updates['failed'])
                        ? (int) $updates['failed']
                        : $before->failed,
                    firstInteraction: isset($updates['first_interaction'])
                        ? (int) $updates['first_interaction']
                        : $before->firstInteraction,
                    requested: isset($updates['requested'])
                        ? (int) $updates['requested']
                        : $before->requested,
                    failReason: isset($updates['fail_reason'])
                        ? (string) $updates['fail_reason']
                        : $before->failReason,
                );
            } catch (\ValueError $e) {
                $this->error("Invalid status for order {$order->id}: ".$e->getMessage());

                continue;
            }

            // Display diff table
            $rows = [];
            foreach (OrderCache::SUFFIXES as $suffix) {
                $prop = match ($suffix) {
                    'duplicate_interaction' => 'duplicateInteraction',
                    'first_interaction' => 'firstInteraction',
                    'fail_reason' => 'failReason',
                    default => lcfirst(str_replace('_', '', ucwords($suffix, '_'))),
                };

                $rawBefore = $before->{$prop};
                $rawAfter = $after->{$prop};

                $dispBefore = $rawBefore instanceof UnitEnum ? $rawBefore->value : $rawBefore;
                $dispAfter = $rawAfter  instanceof UnitEnum ? $rawAfter->value : $rawAfter;

                $rows[] = [$suffix, (string) $dispBefore, (string) $dispAfter];
            }

            $this->info("Order {$order->id}:");
            $this->table(['Field', 'Before', 'After'], $rows);

            if ($dry) {
                $this->info(' → Dry-run, no changes applied.');

                continue;
            }

            // Apply the updates
            if (isset($updates['status'])) {
                OrderCache::setStatus($order, $after->status->value);
            }

            foreach ($updates as $suffix => $value) {
                if ($suffix === 'status') {
                    continue;
                }

                $key = OrderCache::key($order, $suffix);
                cache()->put($key, $value);
            }

            $this->info(" → Updated cache for order {$order->id}.");
            $this->line('');
        }

        $this->info('All done.');

        return Command::SUCCESS;
    }
}
