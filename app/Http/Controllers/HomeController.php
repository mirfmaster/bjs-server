<?php

namespace App\Http\Controllers;

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
        $loginRequiredCounter = Worker::query()->where('status', 'relogin')->count();

        $newWorkers = Worker::query()
            ->where('status', 'new_login')
            ->whereDate('created_at', Carbon::today())
            ->count();
        $loginStateBjs = Redis::get('system:bjs:login-state') ? 'True' : 'False';

        return view('pages.dashboard', [
            'workerCounter' => $workerCounters,
            'activeCounter' => $activeWorkerCounter,
            'loginCounter' => $loginRequiredCounter,
            'newWorkers' => $newWorkers,
            'loginStateBjs' => $loginStateBjs,

        ]);
    }
}
