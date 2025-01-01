<?php

namespace App\Jobs;

use App\Models\IF_User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetUserIndofoll implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $logFile = 'scheduler.log';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = now();
        $this->log('Starting GetUserIndofoll job at ' . $startTime->format('d M Y H:i:s'));

        $yesterday = Carbon::yesterday();
        $IFUser = IF_User::query()
            ->select(['user_id', 'username', 'password'])
            ->whereDate('created_at', $yesterday)
            ->get()->chunk(5000);

        $totalProcessed = 0;
        $totalInserted = 0;

        DB::beginTransaction();
        try {
            foreach ($IFUser as $chunk) {
                $usernames = $chunk->pluck('username')->toArray();
                $totalProcessed += count($usernames);

                // Find the usernames that do not exist in the Worker table
                $existingUserWorkers = Worker::query()
                    ->whereIn('username', $usernames)
                    ->pluck('username')->toArray();
                $nonExistingUsernames = array_diff($usernames, $existingUserWorkers);
                $totalInserted += count($nonExistingUsernames);

                // Prepare the data for mass insertion
                $newWorkers = $chunk->filter(function ($user) use ($nonExistingUsernames) {
                    return in_array($user->username, $nonExistingUsernames);
                })->map(function ($user) {
                    return [
                        'pk_id' => $user->user_id,
                        'username' => $user->username,
                        'password' => $user->password,
                        'status' => 'new_login',
                        'code' => 'bjs:indofoll-job',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                // Insert the new workers into the Worker table
                Worker::query()->insert($newWorkers);

                $this->log("Processed chunk: {$totalProcessed} users, Inserted in this chunk: " . count($nonExistingUsernames));
            }

            DB::commit();

            $endTime = now();
            $duration = $endTime->diffInSeconds($startTime);

            $this->log('Job completed successfully at ' . $endTime->format('d M Y H:i:s'));
            $this->log("Total users processed: {$totalProcessed}");
            $this->log("Total users inserted: {$totalInserted}");
            $this->log("Duration: {$duration} seconds");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->log('Error occurred: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Log message to both Laravel log and scheduler log file
     */
    protected function log(string $message, string $level = 'info'): void
    {
        // Log to Laravel's default log
        Log::$level($message);

        // Log to scheduler.log
        $logPath = storage_path('logs/' . $this->logFile);
        $timestamp = now()->format('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] {$level}: {$message}" . PHP_EOL;
        file_put_contents($logPath, $formattedMessage, FILE_APPEND);
    }
}

