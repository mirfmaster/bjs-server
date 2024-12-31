<?php

namespace App\Console\Commands;

use App\Traits\LoggerTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MoveUserRedispo extends Command
{
    use LoggerTrait;

    protected $signature = 'redispo:move-users';

    protected $description = 'Move users from error states to active state every 6 hours';

    private $baseUrl = 'http://172.104.183.180:12091';

    private $endpoints = [
        '/v1/user/moveall/igresponseerror/active-99999999',
        '/v1/user/moveall/redispofailedspamming/active-99999999',
        '/v1/user/moveall/igactionspamerror/active-99999999',
    ];

    public function handle()
    {
        foreach ($this->endpoints as $endpoint) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => config('app.redispo_auth'),
                    'Accept' => 'application/json',
                ])->get($this->baseUrl . $endpoint);

                if ($response->successful()) {
                    $this->info("Successfully processed endpoint: $endpoint");
                    $this->info('Response: ' . $response->body());
                } else {
                    $this->error("Failed to process endpoint: $endpoint");
                    $this->error('Status: ' . $response->status());
                    $this->error('Response: ' . $response->body());
                }
            } catch (\Throwable $th) {
                $this->logError($th, ['endpoint' => $endpoint]);
                $this->error("Error processing endpoint: $endpoint");
                $this->error($th->getMessage());
            }
        }
    }
}
