<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Worker;
use App\Observers\OrderObserver;
use App\Observers\WorkerObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Worker::observe(WorkerObserver::class);
        Order::observe(OrderObserver::class);
    }
}
