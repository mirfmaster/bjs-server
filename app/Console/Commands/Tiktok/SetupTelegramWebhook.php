<?php

namespace App\Console\Commands\Tiktok;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class SetupTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:setup-webhook {--url= : The full URL for the webhook}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the webhook URL for the Telegram bot';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $token = config('services.telegram.bot_token');

        if (! $token) {
            $this->error('Bot token not found in services.telegram.bot_token config');

            return Command::FAILURE;
        }

        // Get the webhook URL
        $url = $this->option('url');

        if (! $url) {
            // Generate webhook URL based on the current app URL
            $appUrl = rtrim(config('app.url'), '/');
            $url = $appUrl.'/api/telegram/webhook-tiktok';

            $this->info("No URL provided, using: {$url}");
        }

        // Confirm with the user
        if (! $this->confirm("Set Telegram webhook to: {$url}?", true)) {
            $this->info('Operation cancelled');

            return Command::SUCCESS;
        }

        try {
            // Set the webhook
            $response = Http::get("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $url,
                'allowed_updates' => ['message'],
            ]);

            $result = $response->json();

            if ($response->successful() && ($result['ok'] ?? false)) {
                $this->info('Webhook set successfully!');

                // Get webhook info to verify
                $infoResponse = Http::get("https://api.telegram.org/bot{$token}/getWebhookInfo");
                $info = $infoResponse->json();

                if ($infoResponse->successful() && isset($info['result'])) {
                    $this->table(
                        ['Property', 'Value'],
                        collect($info['result'])->map(function ($value, $key) {
                            return [$key, is_array($value) ? json_encode($value) : $value];
                        })->toArray()
                    );
                }

                return Command::SUCCESS;
            } else {
                $this->error('Failed to set webhook: '.($result['description'] ?? 'Unknown error'));
                $this->line(json_encode($result, JSON_PRETTY_PRINT));

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Exception occurred: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
