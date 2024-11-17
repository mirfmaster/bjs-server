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

class GetUserIndofoll implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $yesterday = Carbon::yesterday();
        $IFUser = IF_User::query()
            ->select(['user_id', 'username', 'password'])
            ->whereDate('created_at', $yesterday)
            ->get()->chunk(5000);

        $counter = 0;
        DB::beginTransaction();
        foreach ($IFUser as $chunk) {
            $usernames = $chunk->pluck('username')->toArray();

            // Find the usernames that do not exist in the Worker table
            $existingUserWorkers = Worker::query()
                ->whereIn('username', $usernames)
                ->pluck('username')->toArray();
            $nonExistingUsernames = array_diff($usernames, $existingUserWorkers);
            $counter += count($nonExistingUsernames);

            // Prepare the data for mass insertion
            $newWorkers = $chunk->filter(function ($user) use ($nonExistingUsernames) {
                return in_array($user->username, $nonExistingUsernames);
            })->map(function ($user) {
                return [
                    'pk_id' => $user->user_id,
                    'username' => $user->username,
                    'password' => $user->password,
                    'status' => 'bjs_new_login',
                    'code' => 'bjs:indofoll-job',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            // Insert the new workers into the Worker table
            Worker::query()->insert($newWorkers);
        }

        DB::commit();

    }
}
