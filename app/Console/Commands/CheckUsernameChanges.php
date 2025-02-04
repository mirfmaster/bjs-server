<?php

namespace App\Console\Commands;

use App\Client\InstagramClient;
use App\Models\Worker;
use App\Traits\LoggerTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckUsernameChanges extends Command
{
    use LoggerTrait;

    protected $signature = 'worker:check-username-changes
        {--batch=50 : Number of workers to process per batch}
        {--no-limit : Process all workers without batch limit}';

    protected $description = 'Check for username changes in workers with possibly_change_username status';

    private InstagramClient $igClient;

    private const PROCESS_DELAY = 2; // seconds

    public function __construct(InstagramClient $igClient)
    {
        parent::__construct();
        $this->igClient = $igClient;
    }

    public function handle()
    {
        $query = Worker::query()->where('status', 'possibly_change_username');

        // Apply batch limit unless --no-limit flag is used
        if (! $this->option('no-limit')) {
            $batchSize = $this->option('batch');
            $query->limit($batchSize);
            $this->info("Starting username verification process with batch size: {$batchSize}");
        } else {
            $this->info('Starting username verification process for all matching workers');
        }

        try {
            $totalWorkers = $query->count();
            $workers = $query->get();

            if ($workers->isEmpty()) {
                $this->info('No workers found with possibly_change_username status');

                return Command::SUCCESS;
            }

            $this->info("Found {$totalWorkers} workers to process");
            $bar = $this->output->createProgressBar($workers->count());
            $bar->start();

            foreach ($workers as $worker) {
                $this->processWorker($worker);
                sleep(self::PROCESS_DELAY);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Username verification process completed, processed: $totalWorkers");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logError($e);
            $this->error("Error during execution: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function processWorker($worker): void
    {
        $context = [
            'worker_id' => $worker->id,
            'username' => $worker->username,
        ];

        try {
            $this->info("\nProcessing worker ID: {$worker->id} (Username: {$worker->username})");
            Log::info('Processing worker', $context);

            $profile = $this->igClient->fetchProfile($worker->username);

            if ($profile->found) {
                if ($profile->username !== $worker->username) {
                    $oldUsername = $worker->username;
                    $worker->username = $profile->username;
                    $this->info("✓ Username changed: {$oldUsername} -> {$profile->username}");
                } else {
                    $this->info("✓ Username verified: {$worker->username}");
                }
                $worker->status = 'relogin_update_username';
                $this->info('✓ Status updated to: relogin_update_username');
                Log::info('Account exists, updating status', $context);
            } else {
                $worker->status = 'account_deleted';
                $this->error("✗ Account not found: {$worker->username}");
                $this->info('✓ Status updated to: account_deleted');
                Log::info('Account not found, marking as deleted', $context);
            }

            $worker->save();
            $this->line('──────────────────────────────────');
            $this->igClient->forceRandomProxy();
        } catch (\Exception $e) {
            $this->error("✗ Error processing worker: {$e->getMessage()}");
            $this->line('──────────────────────────────────');
            $this->logError($e, $context);
            Log::error("Error processing worker: {$e->getMessage()}", $context);
        }
    }
}
