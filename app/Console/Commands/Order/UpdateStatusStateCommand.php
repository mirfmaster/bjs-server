<?php

namespace App\Console\Commands\Order;

use App\Enums\OrderStatus;
use App\Repositories\OrderCacheRepository;
use App\Repositories\OrderState;
use Illuminate\Console\Command;
use UnitEnum;

class UpdateStatusStateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = <<<'SIG'
order:update-state
    {ids* : One or more order IDs}
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

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mass-update order cache states, with before/after and optional dry-run';

    public function handle()
    {
        $ids = $this->argument('ids');
        $dry = $this->option('dry-run');

        // Map option names to your Redis suffixes
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

        // Gather only the options the user passed
        $updates = [];
        foreach ($map as $opt => $suffix) {
            $val = $this->option($opt);
            if (! is_null($val) && $val !== '') {
                $updates[$suffix] = $val;
            }
        }

        if (empty($updates)) {
            $this->error('No update options provided. Nothing to do.');

            return Command::INVALID;
        }

        /** @var OrderCacheRepository $repo */
        $repo = app(OrderCacheRepository::class);

        foreach ($ids as $id) {
            $before = $repo->getState($id);
            if (is_null($before)) {
                $this->warn("Order {$id} has no cached state; skipping.");

                continue;
            }

            // Build the "after" state object by overriding only provided fields
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
                        ? (string) $updates['first_interaction']
                        : $before->firstInteraction,
                    requested: isset($updates['requested'])
                        ? (int) $updates['requested']
                        : $before->requested,
                    failReason: isset($updates['fail_reason'])
                        ? (string) $updates['fail_reason']
                        : $before->failReason,
                );
            } catch (\ValueError $e) {
                $this->error("Invalid status for order {$id}: ".$e->getMessage());

                continue;
            }

            // Prepare display table rows
            $rows = [];
            foreach (OrderState::cacheSuffixes() as $suffix) {
                // map suffix → property name
                $prop = match ($suffix) {
                    'duplicate_interaction' => 'duplicateInteraction',
                    'first_interaction' => 'firstInteraction',
                    'fail_reason' => 'failReason',
                    default => lcfirst(str_replace('_', '', ucwords($suffix, '_'))),
                };

                $rawBefore = $before->{$prop};
                $rawAfter = $after->{$prop};

                // if it’s an enum, grab ->value, else leave as-is
                $dispBefore = $rawBefore instanceof UnitEnum
                    ? $rawBefore->value
                    : $rawBefore;
                $dispAfter = $rawAfter instanceof UnitEnum
                    ? $rawAfter->value
                    : $rawAfter;

                // finally cast to string (null→'' etc)
                $rows[] = [
                    $suffix,
                    (string) $dispBefore,
                    (string) $dispAfter,
                ];
            }
            $this->info("Order {$id}:");
            $this->table(['Field', 'Before', 'After'], $rows);

            if (! $dry) {
                $tags = $repo->store->tags("order:{$id}");

                // Status
                if (isset($updates['status'])) {
                    $repo->setStatus($id, $after->status->value);
                }

                // All other fields: do a straight put
                foreach ($updates as $suffix => $value) {
                    if ($suffix === 'status') {
                        continue;
                    }
                    $key = "order:{$id}:{$suffix}";
                    // For first interaction we just overwrite
                    $tags->put($key, $value);
                }

                $this->info(" → Updated cache for order {$id}.");
            } else {
                $this->info(" → Dry-run, no changes applied for order {$id}.");
            }

            $this->line(''); // blank line between orders
        }

        $this->call('order:cache');

        return Command::SUCCESS;
    }
}
