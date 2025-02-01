<?php

namespace Database\Seeders;

use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkerSeeder extends Seeder
{
    /**
     * Convert string boolean values to actual boolean
     *
     * @param  string|null  $value
     * @return bool
     */
    private function convertToBoolean($value)
    {
        if (empty($value)) {
            return false;
        }

        return in_array(strtolower($value), ['true', '1', 'yes', 'y']);
    }

    /**
     * Convert string to integer, handling empty values
     *
     * @param  string|null  $value
     * @return int|null
     */
    private function convertToInt($value)
    {
        if (empty($value) || ! is_numeric($value)) {
            return 0;  // or return null if you prefer
        }

        return (int) $value;
    }

    /**
     * Convert string to DateTime, handling empty values and invalid dates
     *
     * @param  string|null  $value
     * @return string|null
     */
    private function convertToDateTime($value)
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

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $path = storage_path('app/assets/dev/workers.csv'); // Dev asset

        // Check if workers already exist
        // $workerCount = Worker::count();
        // if ($workerCount > 0) {
        //     $this->command->error("Workers table is not empty ({$workerCount} records found). Please truncate the table first or use --force option.");
        //
        //     return;
        // }

        $filename = 'workers_2fa.csv';
        $path = storage_path('app/assets/prod/' . $filename);

        if (! file_exists($path)) {
            $this->command->error("CSV file not found: {$path}");

            return;
        }

        $this->command->info("Starting to import workers from {$filename}...");

        // Begin transaction
        DB::beginTransaction();

        try {
            // Truncate the workers table
            $this->command->info('Truncating workers table...');
            Worker::truncate();

            // Read CSV file
            $handle = fopen($path, 'r');
            $header = fgetcsv($handle, 0, '|'); // Get headers

            if (! $header) {
                throw new \Exception('Unable to read CSV headers');
            }

            // Convert headers to lowercase and trim
            $header = array_map(function ($h) {
                return trim(strtolower($h));
            }, $header);

            $batch = [];
            $batchSize = 1000;
            $totalProcessed = 0;

            while (($data = fgetcsv($handle, 0, '|')) !== false) {
                if (count($data) !== count($header)) {
                    continue; // Skip malformed rows
                }

                $row = array_combine($header, $data);

                // Prepare the worker data with improved type casting
                $worker = [
                    'username' => $row['username'] ?? null,
                    'password' => $row['password'] ?? null,
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

                $batch[] = $worker;
                $totalProcessed++;

                // Insert batch when size is reached
                if (count($batch) >= $batchSize) {
                    Worker::insert($batch);
                    $batch = [];
                    $this->command->info("Processed {$totalProcessed} workers...");
                }
            }

            // Insert remaining records
            if (! empty($batch)) {
                Worker::insert($batch);
            }

            fclose($handle);
            DB::commit();

            $this->command->info("Successfully imported {$totalProcessed} workers.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error importing workers: ' . $e->getMessage());
            throw $e;
        }
    }
}
