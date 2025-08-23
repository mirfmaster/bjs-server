<?php

namespace App\Console\Commands\Order;

use App\Models\Order;
use App\Models\OrderCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteOrderCommand extends Command
{
    protected $signature = 'order:delete {bjs_ids* : List of BJS IDs to cleanup}';

    protected $description = 'Delete orders and their state based on BJS IDs';

    public function handle()
    {
        $bjsIds = $this->argument('bjs_ids');
        $this->info('Starting cleanup for '.count($bjsIds).' orders');

        $successCount = 0;
        $failureCount = 0;

        foreach ($bjsIds as $bjsId) {
            try {
                $order = Order::where('bjs_id', $bjsId)->first();

                if (! $order) {
                    $this->warn("Order with BJS ID {$bjsId} not found in database");
                    $failureCount++;

                    continue;
                }

                // Delete order cache/state
                OrderCache::flush($order);

                // Delete the order
                $order->delete();

                $this->info("Successfully deleted order and state for BJS ID: {$bjsId}");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("Failed to cleanup order with BJS ID {$bjsId}: ".$e->getMessage());
                Log::error("Cleanup failed for BJS ID {$bjsId}", ['error' => $e->getMessage()]);
                $failureCount++;
            }
        }

        $this->info("Cleanup completed: {$successCount} succeeded, {$failureCount} failed");

        $this->call('order:cache');

        return $failureCount > 0 ? 1 : 0;
    }
}
