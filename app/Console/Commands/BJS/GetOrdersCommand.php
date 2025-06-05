<?php

namespace App\Console\Commands\BJS;

use App\Actions\BJS\FetchFollowOrder;
use App\Actions\BJS\FetchLikeOrder;
use App\Client\InstagramClient;
use App\Services\BJSService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GetOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bjs:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /** @var BJSService */
        $bjsService = app(BJSService::class);
        /** @var InstagramClient */
        $igClient = app(InstagramClient::class);

        // TODO: update to use cache;
        $loginStateBjs = Redis::get('system:bjs:login-state');
        if (! (bool) $loginStateBjs) {
            $this->warn('Skipping fetching orders, login state is false');

            return Command::SUCCESS;
        }
        $auth = $bjsService->auth();
        if (! $auth) {
            $this->warn('Authentication failed');

            return Command::FAILURE;
        }

        $this->info('Starting fetching like orders');
        /** @var FetchLikeOrder */
        $fetchLikeAction = app(FetchLikeOrder::class);
        foreach ([167] as $serviceID) {
            $fetchLikeAction->handle($bjsService, $serviceID);
        }

        $this->info('Starting fetching follow orders');
        /** @var FetchFollowOrder */
        $fetchLikeAction = app(FetchFollowOrder::class);
        foreach ([164] as $serviceID) {
            $fetchLikeAction->handle($bjsService, $igClient, $serviceID);
        }

        return Command::SUCCESS;
    }
}
