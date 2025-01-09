<?php

namespace App\Http\Controllers;

use App\Models\Worker;
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
        $activeWorkerCounter = Worker::query()->where('status', 'active')->count();
        $loginRequiredCounter = Worker::query()->where('status', 'relogin')->count();

        $loginStateBjs = Redis::get('system:bjs:login-state') ? 'True' : 'False';
        $globalWorkState = Redis::get('system:bjs:global-work') ? 'True' : 'False';

        return view('pages.dashboard', [
            'activeCounter' => $activeWorkerCounter,
            'loginCounter' => $loginRequiredCounter,
            'loginStateBjs' => $loginStateBjs,
            'globalWorkState' => $globalWorkState,
        ]);
    }
}
