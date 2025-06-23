<?php

namespace App\Console\Commands\Order;

use App\Actions\BJS\CancelBJSOrderAction;
use App\Client\BJSClient;
use App\Models\Order;
use App\Repositories\OrderCacheRepository;
use Illuminate\Console\Command;

class ForceCompletionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:force-completion
                            {ids* : One or more BJS order IDs to cancel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel one or more BJS orders and force completing order';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Resolve the BJS client and authenticate
        /** @var BJSClient $client */
        $client = app(BJSClient::class);
        $this->info('Authenticating to BJS service…');

        if (! $client->authenticate()) {
            $this->error('Failed to authenticate to BJS server.');

            return self::FAILURE;
        }

        // Grab the IDs from the CLI
        $ids = $this->argument('ids');

        if (empty($ids)) {
            $this->error('No order IDs provided. At least one is required.');

            return self::FAILURE;
        }

        /** @var CancelBJSOrderAction $action */
        $action = app(CancelBJSOrderAction::class);

        /** @var OrderCacheRepository $repo */
        $repo = app(OrderCacheRepository::class);

        foreach ($ids as $id) {
            $client->authenticate();
            $order = Order::where('bjs_id', $id)->first();
            if (! $order) {
                $this->info("Order $id is not found, skipping");

                continue;
            }
            if ($order->status == 'cancel') {
                $this->info("Order $id is already cancelled, skipping");

                continue;
            }

            $state = $repo->getState($id);
            if (is_null($state)) {
                $this->warn("Order {$id} has no cached state; skipping.");

                continue;
            }

            try {
                $this->info("Cancelling order {$id}…");
                $result = $action->handle($client, $id);
                if (! $result) {
                    $this->warn('Failed to change status in BJS');

                    continue;
                }

                $this->info("✔ Order {$id} canceled successfully.");
                $status = $state->getCompletionStatus();
                $repo->setStatus($id, $status->value);

                $order->partial_count = $state->getRemains();
                $order->status = $state->getCompletionStatus();
                $order->status_bjs = $state->getCompletionStatus();
                $order->save();

                $this->info('✔ Updating status order to ' . $status->value);
            } catch (\Throwable $e) {
                $this->error("✖ Failed to cancel order {$id}: {$e->getMessage()}");
            }

            $this->line(''); // blank line between orders
        }

        $this->call('order:cache');

        return Command::SUCCESS;
    }
}
