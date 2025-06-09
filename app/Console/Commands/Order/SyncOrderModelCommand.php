<?php

namespace App\Console\Commands\Order;

use App\Actions\Order\SyncOrderStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\OrderCacheRepository;
use Illuminate\Console\Command;

class SyncOrderModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:sync-model
      {ids* : One or more order IDs}
  ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description =
        'Sync the orders’ Eloquent models from cache only, no BJS calls.';

    /**
     * Execute the console command.
     */
    public function handle(
        OrderCacheRepository $cache,
        SyncOrderStatus $syncStatus
    ): int {
        $ids = $this->argument('ids');
        foreach ($ids as $id) {
            /** @var \App\Models\Order|null $order */
            $order = Order::find($id);
            if (! $order) {
                $this->warn("Order {$id} not found; skipping.");

                continue;
            }

            $state = $cache->getState($id);
            if (! $state || $state->status === OrderStatus::UNKNOWN) {
                $this->warn("No valid cache state for order {$id}; skipping.");

                continue;
            }

            $this->info("→ Syncing model for Order {$id}");
            $syncStatus->updateModelOnly($order, $state);
            $this->info("   Order {$id} updated.");
        }
        $this->call('order:cache');

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
