<?php

namespace App\Console\Commands\BJS;

use App\Actions\BJS\CancelBJSOrderAction;
use App\Client\BJSClient;
use Illuminate\Console\Command;

class CancelOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bjs:cancel-order
                            {ids* : One or more BJS order IDs to cancel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel one or more BJS orders';

    /**
     * Execute the console command.
     */
    public function handle(): int
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

        foreach ($ids as $id) {
            $this->info("Cancelling order {$id}…");

            try {
                $action->handle($client, $id);
                $this->info("✔ Order {$id} canceled successfully.");
            } catch (\Throwable $e) {
                $this->error("✖ Failed to cancel order {$id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
