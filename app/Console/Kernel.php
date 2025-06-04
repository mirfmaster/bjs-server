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
                $enabled = (bool) Redis::get('system:global-work');
                Log::info('state', ['system:global-work' => $enabled ? 'yes' : 'no']);

                if (! $enabled) {
                    Log::warning('Global work disabled; skipping SyncBJSOrders job.');
                }

                return $enabled;
            });

        // $schedule->command('redispo:move-users')->daily()->appendOutputTo(storage_path('logs/scheduler.log'));

        // Delete hold state for 'like' every 45 minutes
        $schedule->command('redispo:delete-hold-state like')
            ->cron('*/45 * * * *')
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // Delete hold state for 'follow' every hour
        $schedule->command('redispo:delete-hold-state follow')
            ->hourly()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // Reset daily counters every day at midnight
        $schedule->command('worker:stats-reset daily')
            ->dailyAt('00:00')
            ->runInBackground()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/stats-daily-reset.log'));

        // Reset weekly counters every Monday at 00:05
        $schedule->command('worker:stats-reset weekly')
            ->weeklyOn(1, '00:05') // Monday at 00:05
            ->runInBackground()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/stats-weekly-reset.log'));

        // Reset monthly counters on the first day of each month at 00:10
        $schedule->command('worker:stats-reset monthly')
            ->monthlyOn(1, '00:10') // 1st day of month at 00:10
            ->runInBackground()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/stats-monthly-reset.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
