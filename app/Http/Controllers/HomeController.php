<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // public function __construct()
    // {
    //     $this->middleware('auth');
    // }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $workerCounters = Worker::query()->count();
        $activeWorkerCounter = Worker::query()->where('status', 'active')->count();
        $loginRequiredCounter = Worker::query()->where('status', 'login_required')->count();
        $newWorkers = Worker::query()
            ->where('status', 'bjs_new_login')
            ->whereDate('created_at', Carbon::today())
            ->count();
        $loginStateBjs = Redis::get('system:bjs:login-state') ? 'True' : 'False';
        $workerVersion = Redis::get('system:worker-version') ?? 'Not set';

        $orderCompleted = Order::query()->where('status', 'completed')->count();
        $orderProgress = Order::query()->where('status', 'inprogress')->count();
        $orderProcessing = Order::query()->where('status', 'processing')->count();
        $orderCanceled = Order::query()->where('status', 'canceled')->count();
        $orderPartialled = Order::query()->where('status', 'partial')->count();
        $mismatchedOrders = Order::query()
            ->whereColumn('status', '!=', 'status_bjs')
            ->count();

        $allDevices = Redis::smembers('devices:all');
        // Count workers by mode
        $workerModeCount = 0;
        $loginModeCount = 0;

        // Count inactive devices (>6 hours)
        $inactiveCount = 0;
        $sixHoursAgo = Carbon::now()->subHours(6)->unix();

        foreach ($allDevices as $device) {
            // Check mode counts
            $mode = Redis::get("device:mode:$device");
            if ($mode === 'worker') {
                $workerModeCount++;
            } elseif ($mode === 'login') {
                $loginModeCount++;
            }

            // Check last activity
            $lastActivity = Redis::get("device:last-activity:$device");
            if ($lastActivity && intval($lastActivity) < $sixHoursAgo) {
                $inactiveCount++;
            }
        }

        return view('pages.dashboard', [
            'workerCounter' => $workerCounters,
            'activeCounter' => $activeWorkerCounter,
            'loginCounter' => $loginRequiredCounter,
            'newWorkers' => $newWorkers,
            'loginStateBjs' => $loginStateBjs,
            'workerVersion' => $workerVersion,

            // Order Section
            'orders' => [
                'completed' => $orderCompleted,
                'inprogress' => $orderProgress,
                'processing' => $orderProcessing,
                'canceled' => $orderCanceled,
                'partial' => $orderPartialled,
                'out_sync' => $mismatchedOrders,
            ],

            // Devices Section
            'devices' => [
                'all' => count($allDevices),
                'active' => count($allDevices) - $inactiveCount,
                'dead' => $inactiveCount,
                'worker' => $workerModeCount,
                'login' => $loginModeCount,
            ],

        ]);
    }
}
