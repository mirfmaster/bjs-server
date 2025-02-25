<?php

namespace App\Console\Commands;

use App\Models\Worker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWorkersCommand extends Command
{
    protected $signature = 'workers:import
                          {file : Path to CSV file}
                          {--delimiter=, : CSV delimiter character}
                          {--batch=1000 : Batch size for processing}';

    protected $description = 'Import workers from a CSV file';

    private function convertToBoolean($value): bool
    {
        if (empty($value)) {
            return false;
        }

        return in_array(strtolower($value), ['true', '1', 'yes', 'y']);
    }

    private function convertToInt($value): int
    {
        if (empty($value) || ! is_numeric($value)) {
            return 0;
        }

        return (int) $value;
    }

    private function convertToDateTime($value): ?string
    {
        if (empty($value) || $value == '?') {
            return null;
        }

        try {
            return date('Y-m-d H:i:s', strtotime($value));
        } catch (\Exception $e) {
            return null;
        }
    }

    private function processUsernamePassword($value): array
    {
        if (str_contains($value, ':')) {
            [$username, $password] = explode(':', $value, 2);

            return [
                'username' => trim($username),
                'password' => trim($password),
            ];
        }

        return [
            'username' => trim($value),
            'password' => null,
        ];
    }

    public function handle()
    {
        $path = $this->argument('file');
        $delimiter = $this->option('delimiter');
        $batchSize = $this->option('batch');

        if (! file_exists($path)) {
            $this->error("CSV file not found: {$path}");

            return Command::FAILURE;
        }

        $this->info("Starting to import workers from CSV with delimiter: '{$delimiter}'");

        DB::beginTransaction();

        try {
            $handle = fopen($path, 'r');
            $header = fgetcsv($handle, 0, $delimiter);

            if (! $header) {
                throw new \Exception('Unable to read CSV headers');
            }

            // Convert headers to lowercase and trim
            $header = array_map(function ($h) {
                return trim(strtolower($h));
            }, $header);

            $batch = [];
            $totalProcessed = 0;
            $totalSkipped = 0;
            $bar = $this->output->createProgressBar();
            $bar->start();

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count($data) !== count($header)) {
                    $totalSkipped++;

                    continue;
                }

                $row = array_combine($header, $data);

                // Handle username:password format in both dedicated columns and combined column
                if (isset($row['username_password'])) {
                    $credentials = $this->processUsernamePassword($row['username_password']);
                    $row['username'] = $credentials['username'];
                    $row['password'] = $credentials['password'];
                } elseif (isset($row['username'])) {
                    $credentials = $this->processUsernamePassword($row['username']);
                    if ($credentials['password']) {
                        $row['username'] = $credentials['username'];
                        $row['password'] = $credentials['password'];
                    }
                }

                $worker = [
                    'username' => $row['username'] ?? null,
                    'password' => $row['password'] ?? null,
                    'secret_key_2fa' => $row['secret_key_2fa'] ?? null,
                    'status' => $row['status'] ?? 'relogin',
                    'followers_count' => $this->convertToInt($row['followers_count'] ?? null),
                    'following_count' => $this->convertToInt($row['following_count'] ?? null),
                    'media_count' => $this->convertToInt($row['media_count'] ?? null),
                    'pk_id' => $row['pk_id'] ?? null,
                    'is_max_following_error' => $this->convertToBoolean($row['is_max_following_error'] ?? null),
                    'is_probably_bot' => $this->convertToBoolean($row['is_probably_bot'] ?? null),
                    'is_verified_email' => $this->convertToBoolean($row['is_verified_email'] ?? null),
                    'has_profile_picture' => $this->convertToBoolean($row['has_profile_picture'] ?? null),
                    'last_access' => $this->convertToDateTime($row['last_access'] ?? null),
                    'code' => $row['code'] ?? null,
                    'is_verified' => $this->convertToBoolean($row['is_verified'] ?? null),
                    'on_work' => $this->convertToBoolean($row['on_work'] ?? null),
                    'last_work' => $this->convertToDateTime($row['last_work'] ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Skip if no username is present
                if (empty($worker['username'])) {
                    $totalSkipped++;

                    continue;
                }

                $batch[] = $worker;
                $totalProcessed++;
                $bar->advance();

                if (count($batch) >= $batchSize) {
                    Worker::insert($batch);
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                Worker::insert($batch);
            }

            fclose($handle);
            DB::commit();
            $bar->finish();

            $this->newLine();
            $this->info('Import completed:');
            $this->info("- Processed: {$totalProcessed}");
            $this->info("- Skipped: {$totalSkipped}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error importing workers: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
