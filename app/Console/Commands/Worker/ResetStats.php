<?php

namespace App\Console\Commands\Worker;

use App\Models\Worker;
use App\Transformers\StatsTransformer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worker:stats-reset {type : Type of reset (daily/weekly/monthly)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset statistics counters for accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');

        if (! in_array($type, ['daily', 'weekly', 'monthly'])) {
            $this->error('Invalid reset type. Must be one of: daily, weekly, monthly');

            return 1;
        }

        $now = Carbon::now();
        $this->info("Starting {$type} stats reset at {$now}");

        // Get accounts in batches to avoid memory issues
        Worker::query()->whereNotNull('statistics')->chunkById(100, function ($accounts) use ($type, $now) {
            foreach ($accounts as $account) {
                try {
                    $this->resetAccountStats($account, $type, $now);
                } catch (\Exception $e) {
                    Log::error("Error resetting {$type} stats for account {$account->id}: ".$e->getMessage());
                    $this->error("Error on account {$account->id}: ".$e->getMessage());
                }
            }
        });

        $this->info("Completed {$type} stats reset");

        return 0;
    }

    /**
     * Reset stats for a specific account
     */
    protected function resetAccountStats(Worker $account, string $type, Carbon $now): void
    {
        $stats = StatsTransformer::from($account->statistics);

        // Tasks that have counters
        $tasks = ['search', 'follow', 'like', 'comment', 'view'];

        if ($type === 'daily') {
            // For daily reset, just zero out the daily counters
            foreach ($tasks as $task) {
                $stats->set("{$task}.daily", 0);
            }
        } elseif ($type === 'weekly') {
            // For weekly reset, sum up daily into weekly, then reset daily
            foreach ($tasks as $task) {
                $daily = $stats->get("{$task}.daily", 0);
                $weekly = $stats->get("{$task}.weekly", 0);

                $stats->set("{$task}.weekly", $daily);
                $stats->set("{$task}.daily", 0);
            }
        } elseif ($type === 'monthly') {
            // For monthly reset, sum up weekly into monthly, then reset weekly and daily
            foreach ($tasks as $task) {
                $weekly = $stats->get("{$task}.weekly", 0);
                $monthly = $stats->get("{$task}.monthly", 0);

                $stats->set("{$task}.monthly", $weekly);
                $stats->set("{$task}.weekly", 0);
                $stats->set("{$task}.daily", 0);
            }
        }

        // Update the last reset timestamp
        $stats->set('last_reset', $now->toIso8601String());

        // Save the updated stats
        $account->statistics = $stats->toArray();
        $account->save();

        $this->line("Reset {$type} stats for account {$account->id}");
    }
}
