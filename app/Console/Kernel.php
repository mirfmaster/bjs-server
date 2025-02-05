<?php

namespace App\Console;

use App\Jobs\GetUserIndofoll;
use App\Jobs\SyncBJSOrders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new GetUserIndofoll())->daily();

        $schedule->job(new SyncBJSOrders())
            ->everyThreeMinutes()
            ->withoutOverlapping()
            ->when(function () {
                Log::warning('Global work is false, skipping job');

                return (bool) Redis::get('system:global-work');
            });

        $schedule->command('redispo:move-users')->everySixHours()->appendOutputTo(storage_path('logs/scheduler.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
