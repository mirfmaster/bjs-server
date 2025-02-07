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

    private const MAX_BATCH_SIZE = 50; // Maximum number of records to process in one batch

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->igClient = new InstagramClient('managed');

        try {
            $totalCount = Worker::where('status', 'possibly_change_username')->count();

            if ($totalCount === 0) {
                $this->info('No workers found with possibly_change_username status');

                return Command::SUCCESS;
            }

            $this->info("Found {$totalCount} total workers to process");

            if ($this->option('no-limit')) {
                $processedCount = 0;
                $bar = $this->output->createProgressBar($totalCount);
                $bar->start();

                do {
                    $workers = Worker::query()
                        ->where('status', 'possibly_change_username')
                        ->inRandomOrder()
                        ->limit(self::MAX_BATCH_SIZE)
                        ->get();

                    foreach ($workers as $worker) {
                        $this->processWorker($worker);
                        $processedCount++;
                        $bar->advance();
                        sleep(1);
                    }

                    // Break if we've processed all workers or if no more workers are found
                    if ($processedCount >= $totalCount || $workers->isEmpty()) {
                        break;
                    }

                    sleep(rand(15, 60));
                } while (true);

                $bar->finish();
                $this->newLine();
                $this->info("Username verification process completed, processed: $processedCount");
            } else {
                // Process single batch
                $batchSize = $this->option('batch');
                $this->info("Starting username verification process with batch size: {$batchSize}");

                $workers = Worker::query()
                    ->where('status', 'possibly_change_username')
                    ->inRandomOrder()
                    ->limit($batchSize)
                    ->get();

                $bar = $this->output->createProgressBar($workers->count());
                $bar->start();

                foreach ($workers as $worker) {
                    $this->processWorker($worker);
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
                $this->info("Username verification process completed, processed: {$workers->count()}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logError($e);
            $this->error("Error during execution: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function processWorker($worker): void
    {
        // Clean username (remove @ if present)
        $worker->username = ltrim($worker->username, '@');

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
