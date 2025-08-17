<?php

namespace App\Console\Commands\Order;

use App\Actions\BJS\CancelBJSOrderAction;
use App\Client\BJSClient;
use App\Models\Order;
use App\Models\OrderCache;
use Illuminate\Console\Command;

class ForceCompletionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:force-completion
                            {ids?* : One or more BJS order IDs to cancel}
                            {--all : Cancel all orders with status inprogress|processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description =
        'Cancel one or more BJS orders (or all in-progress) and force completion';

    public function handle(): int
    {
        $all = $this->option('all');
        $ids = $this->argument('ids');

        // Validation: either --all or at least one id
        if (! $all && empty($ids)) {
            $this->error('You must provide one or more IDs, or pass the --all flag.');

            return self::FAILURE;
        }

        if ($all && ! empty($ids)) {
            $this->error('You cannot pass IDs and --all at the same time.');

            return self::FAILURE;
        }

        // If --all, pull all inprogress|processing orders
        if ($all) {
            $this->info('Fetching all inprogress|processing orders…');
            $ids = Order::whereIn('status', ['inprogress', 'processing'])
                ->pluck('bjs_id')
                ->filter()     // drop null/empty
                ->unique()
                ->toArray();

            if (empty($ids)) {
                $this->info('No inprogress or processing orders found.');

                return self::SUCCESS;
            }

            $this->info('Found '.count($ids).' orders to cancel.');
        }

        // Authenticate once
        /** @var BJSClient $client */
        $client = app(BJSClient::class);
        $this->info('Authenticating to BJS service…');
        if (! $client->authenticate()) {
            $this->error('Failed to authenticate to BJS server.');

            return self::FAILURE;
        }

        /** @var CancelBJSOrderAction $action */
        $action = app(CancelBJSOrderAction::class);

        foreach ($ids as $id) {
            $order = Order::where('bjs_id', $id)->first();
            if (! $order) {
                $this->warn("Order {$id} not found in DB; skipping.");

                continue;
            }

            if ($order->status === 'cancel') {
                $this->info("Order {$id} already cancelled; skipping.");

                continue;
            }

            $state = OrderCache::state($order);
            if (is_null($state)) {
                $this->warn("Order {$id} has no cached state; skipping.");

                continue;
            }

            try {
                $this->info("Cancelling order {$id}…");
                $ok = $action->handle($client, $id);
                if (! $ok) {
                    $this->warn("Failed to cancel {$id} in BJS; skipping update.");

                    continue;
                }

                $this->info("✔ Order {$id} cancelled in BJS.");

                $newStatus = $state->completionStatus()->value;
                OrderCache::setStatus($order, $newStatus);

                $order->partial_count = $state->remains();
                $order->status = $newStatus;
                $order->status_bjs = $newStatus;
                $order->save();
                OrderCache::flush($order);

                $this->info("✔ Local order {$id} status updated to {$newStatus}");
            } catch (\Throwable $e) {
                $this->error("✖ Error cancelling {$id}: ".$e->getMessage());
            }

            $this->line(''); // blank line
        }

        // Rebuild cache at the end
        $this->call('order:cache');

        return self::SUCCESS;
    }
}
