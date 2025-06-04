<?php

namespace App\Console\Commands\BJS;

use App\Actions\BJS\FetchLikeOrder;
use App\Models\Order;
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

        // TODO: update to use cache;
        $loginStateBjs = Redis::get('system:bjs:login-state');
        if (! (bool) $loginStateBjs) {
            $this->warn('Skipping fetching orders, login state is false');

            return Command::SUCCESS;
        }
        $auth = $bjsService->auth();
        if (! $auth) {
            $this->warn('Authentication failed');
        }

        /** @var FetchLikeOrder */
        $fetchLikeAction = app(FetchLikeOrder::class);
        foreach ([165] as $serviceID) {
            $fetchLikeAction->handle($bjsService, $serviceID);
        }

        // $stats = Order::query()
        //     ->selectRaw("
        //             'follow' as kind,
        //             COALESCE(SUM(requested), 0) as total_requested,
        //             COALESCE(SUM(margin_request), 0) as total_margin_requested
        //         ")
        //     ->whereDate('created_at', now()->toDateString())
        //     ->where('kind', 'follow')
        //     ->first();
        //
        // $currentTotal = $stats->total_requested;

        return Command::SUCCESS;
    }
}
