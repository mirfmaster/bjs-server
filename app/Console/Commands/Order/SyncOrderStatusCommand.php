<?php

namespace App\Console\Commands\Order;

use App\Actions\Order\SyncOrderStatus;
use Illuminate\Console\Command;

class SyncOrderStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:sync-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync order status based on the progress';

    public function __construct(private SyncOrderStatus $action)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting sync order status');
        $this->action->handle();

        $this->info('Sync success');

        $this->call('order:cache');

        return Command::SUCCESS;
    }
}
