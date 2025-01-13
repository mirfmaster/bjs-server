<?php

namespace App\Jobs;

use App\Client\BJSClient;
use App\Client\UtilClient;
use App\Models\Order;
use App\Services\BJSService;
use App\Services\OrderService;
use App\Wrapper\BJSWrapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SyncBJSOrders implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 120;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 115;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'sync_bjs_orders';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Starting job SyncBJSOrders');

        $bjsCli = new BJSClient();

        $bjsService = new BJSService($bjsCli);
        $orderService = new OrderService(new Order());

        $bjsWrapper = new BJSWrapper($bjsService, $orderService, new UtilClient());

        $loginStateBjs = Redis::get('system:bjs:login-state');
        if ((bool) $loginStateBjs) {
            $watchlistLike = [167];
            $watchlistFollow = [164];
            $auth = $bjsWrapper->bjsService->auth();
            if (! $auth) {
                Log::warning('Job is failed');

                return;
            }
            $stats = Order::query()
                ->whereDate('created_at', now()->toDateString())
                ->where('kind', 'follow')
                ->select([
                    'kind',
                    DB::raw('COALESCE(SUM(requested), 0) as total_requested'),
                    DB::raw('COALESCE(SUM(margin_request), 0) as total_margin_requested'),
                ])
                ->groupBy('kind')
                ->first();

            $currentTotal = $stats->total_requested;

            $bjsWrapper->fetchLikeOrder($watchlistLike);
            $bjsWrapper->fetchFollowOrder($watchlistFollow, $currentTotal);
            $bjsWrapper->processOrders();
            $bjsWrapper->handleServicesAvailability();
        } else {
            Log::info('Skipping BJS actions because login state is false, only processing direct order');
        }

        $bjsWrapper->processDirectOrders();
    }
}
