<?php

namespace App\Console\Commands\Tiktok;

use App\Notifications\TelegramNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class TelegramCommandHandler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:tiktok-command {kind} {parameters?*} {--chat_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle Telegram commands';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $command = $this->argument('kind');
        $parameters = $this->argument('parameters') ?? [];
        $chatId = $this->option('chat_id') ?? config('services.telegram.chat_id');

        try {
            $this->info("Processing command: {$command}");
            $this->info('Parameters: '.implode(', ', $parameters));
            $this->info("Chat ID: {$chatId}");

            switch ($command) {
                case '/help':
                    $this->handleHelpCommand($chatId);
                    break;

                case '/start':
                    $this->handleStartCommand($chatId);
                    break;

                case '/complete':
                    $this->handleCompleteCommand($parameters, $chatId);
                    break;

                case '/cancel':
                    $this->handleCancelCommand($parameters, $chatId);
                    break;

                case '/partial':
                    $this->handlePartialCommand($parameters, $chatId);
                    break;

                default:
                    $this->handleUnknownCommand($command, $chatId);
                    break;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error handling command: {$e->getMessage()}");
            Log::error('Error processing Telegram command: '.$e->getMessage(), [
                'command' => $command,
                'parameters' => $parameters,
                'exception' => $e,
            ]);

            $this->sendNotification([
                '⚠️ Error processing command',
                "Command: {$command}",
                "Error: {$e->getMessage()}",
            ], $chatId);

            return Command::FAILURE;
        }
    }

    /**
     * Handle /help command
     */
    private function handleHelpCommand(string $chatId): void
    {
        $this->sendNotification([
            '👋 Welcome to BJS Order Management Bot',
            '',
            'Available commands:',
            '• /help - Show this help message',
            '• /start {order_id} {start_count} - Start working the order',
            '• /complete {order_id} - Mark an order as completed',
            '• /cancel {order_id} - Cancel an order',
            '• /partial {order_id} [processed_count] - Mark an order as partially completed',
            '',
            'Please provide an order ID with the commands where required.',
        ], $chatId);
    }

    /**
     * Handle /start command
     */
    private function handleStartCommand(string $chatId): void
    {
        $this->sendNotification([
            '🚀 Service initialized!',
            '',
            'Your chat ID has been registered for notifications.',
            'Type /help to see available commands.',
        ], $chatId);

        // Here you would add logic to initialize or register the chat
        // For example, you might store the chat ID in a database
    }

    /**
     * Handle /complete command
     */
    private function handleCompleteCommand(array $parameters, string $chatId): void
    {
        if (empty($parameters)) {
            $this->sendNotification([
                '⚠️ Error: Order ID is required',
                'Usage: /complete {order_id}',
            ], $chatId);

            return;
        }

        $orderId = $parameters[0];

        // Placeholder for your actual implementation
        // Here you would add logic to mark an order as completed

        $this->sendNotification([
            '✅ Command received: Mark order as completed',
            "Order ID: {$orderId}",
            '',
            'This is a placeholder. Implement your completion logic here.',
        ], $chatId);
    }

    /**
     * Handle /cancel command
     */
    private function handleCancelCommand(array $parameters, string $chatId): void
    {
        if (empty($parameters)) {
            $this->sendNotification([
                '⚠️ Error: Order ID is required',
                'Usage: /cancel {order_id}',
            ], $chatId);

            return;
        }

        $orderId = $parameters[0];

        // Placeholder for your actual implementation
        // Here you would add logic to cancel an order

        $this->sendNotification([
            '❌ Command received: Cancel order',
            "Order ID: {$orderId}",
            '',
            'This is a placeholder. Implement your cancellation logic here.',
        ], $chatId);
    }

    /**
     * Handle /partial command
     */
    private function handlePartialCommand(array $parameters, string $chatId): void
    {
        if (empty($parameters)) {
            $this->sendNotification([
                '⚠️ Error: Order ID is required',
                'Usage: /partial {order_id} [processed_count]',
            ], $chatId);

            return;
        }

        $orderId = $parameters[0];
        $processedCount = isset($parameters[1]) ? $parameters[1] : 'unspecified';

        // Placeholder for your actual implementation
        // Here you would add logic to mark an order as partially completed

        $this->sendNotification([
            '⚠️ Command received: Mark order as partial',
            "Order ID: {$orderId}",
            "Processed Count: {$processedCount}",
            '',
            'This is a placeholder. Implement your partial completion logic here.',
        ], $chatId);
    }

    /**
     * Handle unknown commands
     */
    private function handleUnknownCommand(string $command, string $chatId): void
    {
        $this->sendNotification([
            "❓ Unknown command: {$command}",
            '',
            'Type /help to see available commands.',
        ], $chatId);
    }

    /**
     * Send notification to Telegram
     */
    private function sendNotification(array $messages, string $chatId): void
    {
        Notification::sendNow([$chatId], new TelegramNotification($messages, $chatId));
        $this->info("Notification sent to {$chatId}");
    }
}
