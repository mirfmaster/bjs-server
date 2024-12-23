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
use Illuminate\Support\Facades\Redis;

class SyncBJSOrders implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

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
        $watchlistLike = [167];
        $watchlistFollow = [164];

        $bjsCli = new BJSClient;

        $bjsService = new BJSService($bjsCli);
        $orderService = new OrderService(new Order, new Redis);

        $bjsWrapper = new BJSWrapper($bjsService, $orderService, new UtilClient);

        $bjsWrapper->bjsService->auth();
        $bjsWrapper->fetchLikeOrder($watchlistLike);
        $bjsWrapper->fetchFollowOrder($watchlistFollow);
        $bjsWrapper->processCachedOrders();
        $bjsWrapper->processOrders();
    }
}
