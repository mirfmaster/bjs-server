<?php

namespace App\Console\Commands\Redispo;

use App\Models\Worker;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use RedisException;

class UpdatePasswordWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redispo:update-password-worker
                            {--dry-run : Show what would be updated without making changes}
                            {--chunk-size=100 : Number of workers to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates worker passwords in Redis that contain "bjs" and tracks synced changes.';

    /**
     * The file to store synced password information.
     *
     * @var string
     */
    protected const SYNCED_PASSWORDS_FILE = 'synced_password.json';

    /**
     * Counters for tracking operations.
     */
    protected int $processedCount = 0;

    protected int $updatedCount = 0;

    protected int $skippedCount = 0;

    protected int $errorCount = 0;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk-size');

        $this->info('Starting password synchronization for workers with "bjs" in their password.');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Get total count for progress tracking
        $totalWorkers = Worker::where('password', 'like', '%bjs%')->count();

        if ($totalWorkers === 0) {
            $this->info('No workers found with "bjs" in their password.');

            return Command::SUCCESS;
        }

        $this->info("Found {$totalWorkers} workers to process (chunk size: {$chunkSize})");

        // Initialize the synced passwords tracker (only load if not dry run)
        $syncedPasswords = $isDryRun ? [] : $this->loadSyncedPasswords();

        // Create progress bar
        $progressBar = $this->output->createProgressBar($totalWorkers);
        $progressBar->start();

        // Process workers in chunks
        Worker::select(['pk_id', 'username', 'password'])
            ->where('password', 'like', '%bjs%')
            ->chunk($chunkSize, function ($workers) use ($isDryRun, &$syncedPasswords, $progressBar) {
                $this->processWorkerChunk($workers, $isDryRun, $syncedPasswords, $progressBar);
            });

        $progressBar->finish();
        $this->newLine();

        // Save the updated tracker file (only if not dry run and there were updates)
        if (! $isDryRun && $this->updatedCount > 0) {
            $this->saveSyncedPasswords($syncedPasswords);
        }

        // Display summary
        $this->displaySummary($isDryRun);

        // Log the operation
        Log::info('Password sync completed', [
            'dry_run' => $isDryRun,
            'processed' => $this->processedCount,
            'updated' => $this->updatedCount,
            'skipped' => $this->skippedCount,
            'errors' => $this->errorCount,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Process a chunk of workers.
     */
    protected function processWorkerChunk($workers, bool $isDryRun, array &$syncedPasswords, $progressBar): void
    {
        foreach ($workers as $worker) {
            $progressBar->advance();
            $this->processedCount++;

            try {
                // Validate worker data
                if (empty($worker->password) || empty($worker->username)) {
                    $this->warn("Skipping worker with incomplete data: ID {$worker->pk_id}");
                    $this->skippedCount++;

                    continue;
                }

                // Fetch worker data from Redis
                $workerDataJson = Redis::connection('redispo')->get("account:{$worker->pk_id}:info");

                if ($workerDataJson === null || $workerDataJson === false) {
                    $this->warn("Skipping worker {$worker->username} (ID: {$worker->pk_id}): No info found in Redis.");
                    $this->skippedCount++;

                    continue;
                }

                $workerData = json_decode($workerDataJson, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error("Failed to decode Redis data for worker {$worker->username} (ID: {$worker->pk_id}): ".json_last_error_msg());
                    $this->errorCount++;

                    continue;
                }

                // Check if password needs updating
                if (isset($workerData['password']) && $worker->password !== $workerData['password']) {
                    if ($isDryRun) {
                        $this->line("  [DRY RUN] Would update password for user {$worker->username} (ID: {$worker->pk_id})");
                    } else {
                        $this->info("  Updating password for user {$worker->username} (ID: {$worker->pk_id})");

                        // Update the password in Redis
                        $workerData['password'] = $worker->password;
                        Redis::connection('redispo')->set("account:{$worker->pk_id}:info", json_encode($workerData));

                        // Track the change
                        $syncedPasswords[$worker->pk_id] = [
                            'username' => $worker->username,
                            'synced_at' => now()->toDateTimeString(),
                            'new_password_hash' => hash('sha256', $worker->password),
                        ];
                    }

                    $this->updatedCount++;
                } else {
                    if ($this->output->isVerbose()) {
                        $this->line("  Password for user {$worker->username} (ID: {$worker->pk_id}) is already synchronized.");
                    }
                    $this->skippedCount++;
                }

            } catch (RedisException $e) {
                $this->error("  Redis error for worker {$worker->username} (ID: {$worker->pk_id}): ".$e->getMessage());
                $this->errorCount++;
                Log::error('Redis password update failed', [
                    'worker_id' => $worker->pk_id,
                    'username' => $worker->username,
                    'error' => $e->getMessage(),
                ]);
            } catch (Exception $e) {
                $this->error("  Unexpected error for worker {$worker->username} (ID: {$worker->pk_id}): ".$e->getMessage());
                $this->errorCount++;
                Log::error('Password sync unexpected error', [
                    'worker_id' => $worker->pk_id,
                    'username' => $worker->username,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Display operation summary.
     */
    protected function displaySummary(bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 Operation Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $this->processedCount],
                ['Updated', $this->updatedCount],
                ['Skipped', $this->skippedCount],
                ['Errors', $this->errorCount],
            ]
        );

        if ($isDryRun) {
            $this->warn('🔍 This was a dry run - no actual changes were made.');
            if ($this->updatedCount > 0) {
                $this->info("Run without --dry-run to apply {$this->updatedCount} password updates.");
            }
        } else {
            $this->info('✅ Password synchronization process completed.');
            if ($this->updatedCount > 0) {
                $this->info("Successfully updated {$this->updatedCount} passwords in Redis.");
            }
        }

        if ($this->errorCount > 0) {
            $this->error("⚠️  {$this->errorCount} errors occurred. Check the logs for details.");
        }
    }

    /**
     * Loads the synced passwords from the storage file.
     */
    protected function loadSyncedPasswords(): array
    {
        $filePath = self::SYNCED_PASSWORDS_FILE;

        if (Storage::disk('local')->exists($filePath)) {
            try {
                $contents = Storage::disk('local')->get($filePath);
                $data = json_decode($contents, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                } else {
                    $this->warn("Corrupted {$filePath} found. Starting with an empty tracker.");
                    Log::warning('JSON decoding error for synced passwords file: '.json_last_error_msg());
                }
            } catch (Exception $e) {
                $this->warn("Could not read {$filePath}. Starting with an empty tracker.");
                Log::warning('Error reading synced passwords file: '.$e->getMessage());
            }
        }

        return [];
    }

    /**
     * Saves the synced passwords to the storage file.
     */
    protected function saveSyncedPasswords(array $data): void
    {
        try {
            Storage::disk('local')->put(
                self::SYNCED_PASSWORDS_FILE,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            $this->info('📝 Synced passwords tracker updated at '.storage_path('app/'.self::SYNCED_PASSWORDS_FILE));
        } catch (Exception $e) {
            $this->error('Failed to save synced passwords tracker: '.$e->getMessage());
            Log::error('Failed to save synced passwords tracker', ['error' => $e->getMessage()]);
        }
    }
}
