<?php

namespace Database\Seeders;

use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $csvFile = storage_path('app/assets/dev/workers.csv');

        if (! file_exists($csvFile)) {
            throw new \Exception('CSV file not found at: '.$csvFile);
        }

        // Open the CSV file
        $file = fopen($csvFile, 'r');

        // Skip the header row
        fgetcsv($file);

        // Begin transaction for better performance
        DB::beginTransaction();

        try {
            while (($data = fgetcsv($file)) !== false) {
                $password = $data[2];
                if (strlen($password) > 100) {
                    continue;
                }
                Worker::query()->create([
                    'id' => $this->convertToInt($data[0]),
                    'username' => $data[1],
                    'password' => $password,
                    'status' => $data[3],
                    'followers_count' => $this->convertToInt($data[4]),
                    'following_count' => $this->convertToInt($data[5]),
                    'media_count' => $this->convertToInt($data[6]),
                    'pk_id' => $data[7],
                    'is_max_following_error' => $this->convertToBoolean($data[8]),
                    'is_probably_bot' => $this->convertToBoolean($data[9]),
                    'is_verified_email' => $this->convertToBoolean($data[10]),
                    'has_profile_picture' => $this->convertToBoolean($data[11]),
                    'last_access' => $this->convertToDateTime($data[12]),
                    'created_at' => $this->convertToDateTime($data[13]),
                    'updated_at' => $this->convertToDateTime($data[14]),
                    'code' => $data[15],
                    'is_verified' => $this->convertToBoolean($data[16]),
                    'on_work' => $this->convertToBoolean($data[17]),
                    'last_work' => $this->convertToDateTime($data[18]),
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Close the file
        fclose($file);
    }

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
}
