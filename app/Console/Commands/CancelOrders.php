<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BJSService;
use App\Services\OrderService;
use Illuminate\Console\Command;

class CancelOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redispo:cancel-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel all orders and update state BJS(if available)';

    public function __construct(
        private OrderService $orderService,
        private BJSService $bjsService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting to cancel all orders...');

        // Get all orders that are in progress or processing
        $orders = Order::query()
            ->whereIn('status', ['inprogress', 'processing'])
            ->get();

        $totalOrders = $orders->count();
        $this->info("Found {$totalOrders} orders to process");

        $processed = 0;
        $errors = 0;

        foreach ($orders as $order) {
            $this->info("Processing order #{$order->id} (BJS ID: {$order->bjs_id})");

            try {
                // For BJS orders, check current status first
                if ($order->source === 'bjs') {
                    $bjsOrder = $this->bjsService->bjs->getOrderDetail($order->bjs_id);
                    if ($bjsOrder) {
                        // Map BJS status names to our system's status names
                        $statusMap = [
                            'Partial' => 'partial',
                            'Canceled' => 'cancel',
                        ];

                        // If order is already cancelled or partial in BJS, sync the status
                        if (in_array($bjsOrder->status_name, ['Partial', 'Canceled'])) {
                            $localStatus = $statusMap[$bjsOrder->status_name];
                            $this->info("Order #{$order->id} is already {$bjsOrder->status_name} in BJS, syncing to local status: {$localStatus}");

                            $order->update([
                                'status' => $localStatus,
                                'status_bjs' => $localStatus,
                                'end_at' => now(),
                            ]);
                            $this->orderService->deleteOrderRedisKeys($order->id);
                            $this->orderService->updateCache();
                            $processed++;

                            continue;
                        }
                    }
                }

                // Set order ID for Redis operations
                $this->orderService->setOrderID($order->id);
                $redisData = $this->orderService->getOrderRedisKeys();

                // Determine if order should be cancelled or marked as partial
                $shouldBePartial = ($redisData['processed'] ?? 0) > 0;
                $newStatus = $shouldBePartial ? 'partial' : 'cancel';

                // Handle BJS orders
                if ($order->source === 'bjs') {
                    $this->info("Updating BJS status for order #{$order->id}");

                    // Authenticate with BJS
                    if (! $this->bjsService->auth()) {
                        $this->warn("Failed to authenticate with BJS for order #{$order->id}, skipping...");
                        $errors++;

                        continue;
                    }

                    // Update BJS status
                    if ($shouldBePartial) {
                        $remainingCount = $redisData['requested'] - $redisData['processed'];
                        $success = $this->bjsService->bjs->setPartial($order->bjs_id, $remainingCount);
                    } else {
                        $success = $this->bjsService->bjs->cancelOrder($order->bjs_id);
                    }

                    if (! $success) {
                        dump($bjsOrder);
                        $this->warn("Failed to update BJS status for order #{$order->id}, skipping...");
                        $errors++;

                        continue;
                    }
                }

                // Update local database
                $updateData = [
                    'status' => $newStatus,
                    'status_bjs' => $newStatus,
                    'end_at' => now(),
                ];

                if ($shouldBePartial) {
                    $updateData['partial_count'] = $redisData['requested'] - $redisData['processed'];
                    $updateData['processed'] = $redisData['processed'];
                }

                $order->update($updateData);

                // Delete Redis keys
                $this->orderService->deleteOrderRedisKeys($order->id);

                // Update order cache
                $this->orderService->updateCache();

                $processed++;
                $this->info("Successfully processed order #{$order->id} - New status: {$newStatus}");

            } catch (\Throwable $th) {
                $this->logError($th, ['order_id' => $order->id]);
                $this->error("Error processing order #{$order->id}: ".$th->getMessage());
                $errors++;
            }
        }

        $this->info("\nProcessing completed!");
        $this->info("Total orders processed: {$processed}");
        $this->info("Errors encountered: {$errors}");

        return Command::SUCCESS;
    }
}
