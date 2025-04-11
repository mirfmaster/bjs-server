<?php

namespace App\Console\Commands\Tiktok;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// @deprecated
class FetchTelegramUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:fetch-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and process Telegram updates';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $token = config('services.telegram.bot_token');
        if (! $token) {
            $this->error('Telegram bot token not found in config');

            return Command::FAILURE;
        }

        $lastUpdateId = Cache::get('telegram:last_update_id', 0);

        try {
            $response = Http::get("https://api.telegram.org/bot{$token}/getUpdates", [
                'offset' => $lastUpdateId + 1,
                'limit' => 100,
                'timeout' => 5,
                'allowed_updates' => ['message'],
            ]);

            if (! $response->successful()) {
                $this->error('Failed to fetch updates: ' . $response->body());

                return Command::FAILURE;
            }

            $updates = $response->json('result', []);
            $this->info('Received ' . count($updates) . ' updates');

            foreach ($updates as $update) {
                $this->processUpdate($update);

                // Store the latest update ID for next fetch
                if ($update['update_id'] > $lastUpdateId) {
                    $lastUpdateId = $update['update_id'];
                    Cache::put('telegram:last_update_id', $lastUpdateId, now()->addDays(7));
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error fetching Telegram updates: ' . $e->getMessage());
            Log::error('Error fetching Telegram updates', ['exception' => $e]);

            return Command::FAILURE;
        }
    }

    /**
     * Process a single update
     */
    private function processUpdate(array $update): void
    {
        if (! isset($update['message']) || ! isset($update['message']['text'])) {
            return;
        }

        $message = $update['message'];
        $text = $message['text'];
        $chatId = config('services.telegram.chat_id');

        // Only process commands (messages starting with /)
        if (substr($text, 0, 1) !== '/') {
            return;
        }

        // Parse command and parameters
        [$command, $parameters] = $this->parseCommand($text);

        $this->info("Processing command: {$command}");

        // Execute the command handler
        $artisanParams = ['kind' => $command];

        if (! empty($parameters)) {
            $artisanParams['parameters'] = $parameters;
        }

        if ($chatId) {
            $artisanParams['--chat_id'] = $chatId;
        }

        Artisan::call('telegram:tiktok-command', $artisanParams);
    }

    /**
     * Parse a command string into command name and parameters
     *
     * @return array [command, parameters]
     */
    private function parseCommand(string $text): array
    {
        $parts = explode(' ', trim($text));

        $command = strtolower($parts[0]);

        array_shift($parts);
        $parameters = array_filter($parts, function ($value) {
            return $value !== '';
        });

        return [$command, array_values($parameters)];
    }
}
