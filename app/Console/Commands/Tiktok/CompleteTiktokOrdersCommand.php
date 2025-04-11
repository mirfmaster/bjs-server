<?php

namespace App\Console\Commands\Tiktok;

use App\Client\BJSClient;
use App\Repository\RedisTiktokRepository;
use App\Services\BJSTiktokService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteTiktokOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiktok:complete-orders {--force : Force execution even if command is already running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process completed TikTok orders and update their status in BJS';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting TikTok order completion process...');

        // Check if command is already running
        if (! $this->option('force') && $this->isCommandRunning()) {
            $this->warn('Command is already running. Use --force to run anyway.');

            return Command::FAILURE;
        }

        try {
            // Set lock file to prevent concurrent runs
            $this->setLockFile();

            // Initialize services
            $bjsClient = app(BJSClient::class);
            $repository = app(RedisTiktokRepository::class);
            $service = new BJSTiktokService($bjsClient, $repository);

            // Process completed orders
            $result = $service->processCompletedOrders();

            // Output results
            $this->info('TikTok order completion finished:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Orders', $result['total']],
                    ['Successfully Completed', $result['success']],
                    ['Failed', $result['failed']],
                    ['Authentication Failed', $result['auth_failed'] ?? 'No'],
                ]
            );

            // Remove lock file
            $this->removeLockFile();

            if (isset($result['auth_failed']) && $result['auth_failed']) {
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error running command: ' . $e->getMessage());
            Log::error('Error completing TikTok orders', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Remove lock file on error
            $this->removeLockFile();

            return Command::FAILURE;
        }
    }

    /**
     * Check if the command is already running by checking lock file
     */
    private function isCommandRunning(): bool
    {
        $lockFile = storage_path('app/tiktok_complete_orders.lock');

        if (! file_exists($lockFile)) {
            return false;
        }

        $lockTime = (int) file_get_contents($lockFile);
        $currentTime = time();

        // If lock is older than 30 minutes, assume it's stale
        if ($currentTime - $lockTime > 1800) {
            $this->warn('Found stale lock file. Removing it.');
            $this->removeLockFile();

            return false;
        }

        return true;
    }

    /**
     * Create a lock file to prevent concurrent runs
     */
    private function setLockFile(): void
    {
        $lockFile = storage_path('app/tiktok_complete_orders.lock');
        file_put_contents($lockFile, time());
    }

    /**
     * Remove the lock file
     */
    private function removeLockFile(): void
    {
        $lockFile = storage_path('app/tiktok_complete_orders.lock');
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}
