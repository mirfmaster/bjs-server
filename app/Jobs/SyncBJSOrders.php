<?php

namespace App\Jobs;

use App\Client\InstagramClient;
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
use Illuminate\Support\Facades\Artisan;
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

        $bjsService = app(BJSService::class);
        $orderService = new OrderService(new Order());
        $igCli = new InstagramClient();

        $bjsWrapper = new BJSWrapper($bjsService, $orderService, new UtilClient(), $igCli);

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
                ->selectRaw("
                    'follow' as kind,
                    COALESCE(SUM(requested), 0) as total_requested,
                    COALESCE(SUM(margin_request), 0) as total_margin_requested
                ")
                ->whereDate('created_at', now()->toDateString())
                ->where('kind', 'follow')
                ->first();

            $currentTotal = $stats->total_requested;
            // $bjsWrapper->fetchLikeOrder($watchlistLike);
            // $bjsWrapper->fetchFollowOrder($watchlistFollow, $currentTotal);
            // $bjsWrapper->processOrders();
            // $bjsWrapper->syncOrdersBJS();
            // $bjsWrapper->handleServicesAvailability();

            // NOTE: DEPRECATED SINCE ITS NOT WORTH THE PENNY
            // // Fetch TikTok orders
            // try {
            //     Log::info('Starting TikTok order fetching...');
            //     $tiktokOrderCount = $bjsWrapper->fetchTiktokOrder([147]);
            //     Log::info("TikTok order fetching completed. Found {$tiktokOrderCount} orders.");
            // } catch (\Exception $e) {
            //     Log::error('Error fetching TikTok orders: ' . $e->getMessage(), [
            //         'exception' => $e,
            //     ]);
            // }

            // Process Telegram updates
            try {
                Log::info('Processing Telegram updates...');
                $exitCode = Artisan::call('telegram:fetch-updates');
                $output = Artisan::output();

                // Log the output of the command
                Log::info('Telegram updates processed', [
                    'exit_code' => $exitCode,
                    'output' => trim($output),
                ]);
            } catch (\Exception $e) {
                Log::error('Error processing Telegram updates: '.$e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        } else {
            Log::info('Skipping BJS actions because login state is false, only processing direct order');
        }

        $bjsWrapper->processDirectOrders();
    }
}
