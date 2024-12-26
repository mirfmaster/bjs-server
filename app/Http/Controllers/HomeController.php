<?php

namespace App\Http\Controllers;

use App\Models\Device;
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

        // Get all devices and count their states
        $sixHoursAgo = Carbon::now()->subHours(6);
        $devices = Device::query();

        $totalDevices = $devices->count();
        $inactiveDevices = $devices->where('last_activity', '<', $sixHoursAgo)->count();
        $activeDevices = $totalDevices - $inactiveDevices;

        // Count devices by mode
        $workerModeCount = Device::where('mode', 'worker')->count();
        $loginModeCount = Device::where('mode', 'login')->count();

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
                'all' => $totalDevices,
                'active' => $activeDevices,
                'dead' => $inactiveDevices,
                'worker' => $workerModeCount,
                'login' => $loginModeCount,
            ],
        ]);
    }
}
