<?php

namespace App\Console\Commands\Redispo;

use App\Traits\LoggerTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DeleteHoldState extends Command
{
    use LoggerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redispo:delete-hold-state {action? : The action to delete (like or follow)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete state feature hold for specified action (like or follow)';

    /**
     * Base URL for the API
     *
     * @var string
     */
    private $baseUrl = 'http://172.104.183.180:12091';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Start running command at: ' . now()->format('d M Y H:i:s'));

        $action = $this->argument('action');

        if ($action && ! in_array($action, ['like', 'follow'])) {
            $this->error('Invalid action. Please use "like" or "follow"');

            return Command::FAILURE;
        }

        $actions = $action ? [$action] : ['like', 'follow'];

        foreach ($actions as $currentAction) {
            $endpoint = "/v2/-/state-feature-hold?delete={$currentAction}";

            try {
                $response = Http::withHeaders([
                    'Authorization' => config('app.redispo_auth'),
                    'Accept' => 'application/json',
                ])->get($this->baseUrl . $endpoint);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $totalDeleted = $responseData['data']['totalDeleted'] ?? 0;

                    $this->info("Successfully deleted {$currentAction} hold state");
                    $this->info("Total deleted: {$totalDeleted}");
                } else {
                    $this->error("Failed to delete {$currentAction} hold state");
                    $this->error('Status: ' . $response->status());
                    $this->error('Response: ' . $response->body());
                }
            } catch (\Throwable $th) {
                $this->logError($th, ['action' => $currentAction, 'endpoint' => $endpoint]);
                $this->error("Error deleting {$currentAction} hold state");
                $this->error($th->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}

