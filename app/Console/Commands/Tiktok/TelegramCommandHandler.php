<?php

namespace App\Console\Commands\Tiktok;

use App\Client\BJSClient;
use App\Notifications\TelegramNotification;
use App\Services\BJSService;
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
     * BJSService instance
     */
    protected $bjsService;

    /**
     * BJSClient instance
     */
    protected $bjsClient;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(BJSService $bjsService)
    {
        parent::__construct();
        $this->bjsService = $bjsService;
        $this->bjsClient = $bjsService->bjs;
    }

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
            $this->info('Parameters: ' . implode(', ', $parameters));
            $this->info("Chat ID: {$chatId}");

            switch ($command) {
                case '/help':
                    $this->handleHelpCommand($chatId);
                    break;

                case '/start':
                    $this->handleStartCommand($parameters, $chatId);
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

                case '/status':
                    $this->handleStatusCommand($parameters, $chatId);
                    break;

                default:
                    $this->handleUnknownCommand($command, $chatId);
                    break;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error handling command: {$e->getMessage()}");
            Log::error('Error processing Telegram command: ' . $e->getMessage(), [
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
        $notification = new TelegramNotification([
            '👋 Welcome to BJS Order Management Bot',
            '',
            'Available commands:',
            '• /help - Show this help message',
            '• /start {order_id} {start_count} - Start working the order',
            '• /complete {order_id} - Mark an order as completed',
            '• /cancel {order_id} {reason} - Cancel an order with reason',
            '• /partial {order_id} {remaining_count} - Mark an order as partially completed',
            '• /status {order_id} - Check order status',
            '',
            'Please provide an order ID with the commands where required.',
        ], $chatId);

        // Override the default markdown format
        $notification->formatAs('');

        Notification::sendNow([$chatId], $notification);
        $this->info("Notification sent to {$chatId}");
    }

    /**
     * Handle /start command for order initialization
     */
    private function handleStartCommand(array $parameters, string $chatId): void
    {
        if (count($parameters) < 2) {
            $this->sendNotification([
                '⚠️ Error: Order ID and start count are required',
                'Usage: /start {order_id} {start_count}',
            ], $chatId);

            return;
        }

        $orderId = $parameters[0];
        $startCount = (int) $parameters[1];

        // First authenticate with BJS
        if (! $this->bjsService->auth()) {
            $this->sendNotification([
                '❌ Authentication failed',
                'Could not authenticate with BJS service.',
                'Please try again later.',
            ], $chatId);

            return;
        }

        // Get order details to verify it exists
        $orderInfo = $this->bjsClient->getOrderDetail($orderId);
        if (! $orderInfo) {
            $this->sendNotification([
                '❌ Order not found',
                "Could not find order with ID: {$orderId}",
                'Please check the order ID and try again.',
            ], $chatId);

            return;
        }

        // Set start count
        $startCountSuccess = $this->bjsClient->setStartCount($orderId, $startCount);
        if (! $startCountSuccess) {
            $this->sendNotification([
                '❌ Failed to set start count',
                "Order ID: {$orderId}",
                "Start count: {$startCount}",
                'The operation failed. Please check BJS service logs.',
            ], $chatId);

            return;
        }

        // Change status to inprogress
        $statusSuccess = $this->bjsClient->changeStatus($orderId, 'inprogress');
        if (! $statusSuccess) {
            $this->sendNotification([
                '⚠️ Start count set but status change failed',
                "Order ID: {$orderId}",
                "Start count set to: {$startCount}",
                "Failed to change order status to 'inprogress'.",
            ], $chatId);

            return;
        }

        // Success notification
        $this->sendNotification([
            '✅ Order started successfully',
            "Order ID: {$orderId}",
            "Start count set to: {$startCount}",
            'Status changed to: inprogress',
            '',
            'Quantity: ' . ($orderInfo->count ?? 'Unknown'),
        ], $chatId);
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

        // Authenticate with BJS
        if (! $this->bjsService->auth()) {
            $this->sendNotification([
                '❌ Authentication failed',
                'Could not authenticate with BJS service.',
                'Please try again later.',
            ], $chatId);

            return;
        }

        // Get order details
        $orderInfo = $this->bjsClient->getOrderDetail($orderId);
        if (! $orderInfo) {
            $this->sendNotification([
                '❌ Order not found',
                "Could not find order with ID: {$orderId}",
                'Please check the order ID and try again.',
            ], $chatId);

            return;
        }

        // Set remains to 0 (fully completed)
        $remainsSuccess = $this->bjsClient->setRemains($orderId, 0);
        if (! $remainsSuccess) {
            $this->sendNotification([
                '❌ Failed to set remains',
                "Order ID: {$orderId}",
                'The operation failed. Please check BJS service logs.',
            ], $chatId);

            return;
        }

        // Change status to completed
        $statusSuccess = $this->bjsClient->changeStatus($orderId, 'completed');
        if (! $statusSuccess) {
            $this->sendNotification([
                '⚠️ Remains set but status change failed',
                "Order ID: {$orderId}",
                "Failed to change order status to 'completed'.",
            ], $chatId);

            return;
        }

        // Success notification
        $this->sendNotification([
            '✅ Order marked as completed',
            "Order ID: {$orderId}",
            'Status changed to: completed',
            '',
            'Quantity: ' . ($orderInfo->count ?? 'Unknown'),
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

        // Authenticate with BJS
        if (! $this->bjsService->auth()) {
            $this->sendNotification([
                '❌ Authentication failed',
                'Could not authenticate with BJS service.',
                'Please try again later.',
            ], $chatId);

            return;
        }

        // Get order details
        $orderInfo = $this->bjsClient->getOrderDetail($orderId);
        if (! $orderInfo) {
            $this->sendNotification([
                '❌ Order not found',
                "Could not find order with ID: {$orderId}",
                'Please check the order ID and try again.',
            ], $chatId);

            return;
        }

        // Cancel the order
        $cancelSuccess = $this->bjsClient->cancelOrder($orderId);
        if (! $cancelSuccess) {
            $this->sendNotification([
                '❌ Failed to cancel order',
                "Order ID: {$orderId}",
                'The operation failed. Please check BJS service logs.',
            ], $chatId);

            return;
        }

        // Add cancel reason if provided
        $cancelReason = isset($parameters[1]) ? implode(' ', array_slice($parameters, 1)) : 'Sorry~';
        $this->bjsClient->addCancelReason($orderId, $cancelReason);

        // Success notification
        $this->sendNotification([
            '✅ Order cancelled successfully',
            "Order ID: {$orderId}",
            "Reason: {$cancelReason}",
        ], $chatId);
    }

    /**
     * Handle /partial command
     */
    private function handlePartialCommand(array $parameters, string $chatId): void
    {
        if (count($parameters) < 2) {
            $this->sendNotification([
                '⚠️ Error: Order ID and remaining count are required',
                'Usage: /partial {order_id} {remaining_count}',
            ], $chatId);

            return;
        }

        $orderId = $parameters[0];
        $remainingCount = (int) $parameters[1];

        // Authenticate with BJS
        if (! $this->bjsService->auth()) {
            $this->sendNotification([
                '❌ Authentication failed',
                'Could not authenticate with BJS service.',
                'Please try again later.',
            ], $chatId);

            return;
        }

        // Get order details
        $orderInfo = $this->bjsClient->getOrderDetail($orderId);
        if (! $orderInfo) {
            $this->sendNotification([
                '❌ Order not found',
                "Could not find order with ID: {$orderId}",
                'Please check the order ID and try again.',
            ], $chatId);

            return;
        }

        // Set order as partial
        $partialSuccess = $this->bjsClient->setPartial($orderId, $remainingCount);
        if (! $partialSuccess) {
            $this->sendNotification([
                '❌ Failed to set order as partial',
                "Order ID: {$orderId}",
                "Remaining count: {$remainingCount}",
                'The operation failed. Please check BJS service logs.',
            ], $chatId);

            return;
        }

        // Calculate processed count
        $totalOrdered = $orderInfo->count ?? 0;
        $processedCount = $totalOrdered - $remainingCount;

        // Success notification
        $this->sendNotification([
            '✅ Order marked as partial',
            "Order ID: {$orderId}",
            "Processed: {$processedCount} / {$totalOrdered}",
            "Remaining: {$remainingCount}",
        ], $chatId);
    }

    /**
     * Handle /status command
     */
    private function handleStatusCommand(array $parameters, string $chatId): void
    {
        if (empty($parameters)) {
            $this->sendNotification([
                '⚠️ Error: Order ID is required',
                'Usage: /status {order_id}',
            ], $chatId);

            return;
        }

        $orderId = $parameters[0];

        // Authenticate with BJS
        if (! $this->bjsService->auth()) {
            $this->sendNotification([
                '❌ Authentication failed',
                'Could not authenticate with BJS service.',
                'Please try again later.',
            ], $chatId);

            return;
        }

        // Get order details
        $orderInfo = $this->bjsClient->getOrderDetail($orderId);
        if (! $orderInfo) {
            $this->sendNotification([
                '❌ Order not found',
                "Could not find order with ID: {$orderId}",
                'Please check the order ID and try again.',
            ], $chatId);

            return;
        }

        // Get order info from BJS
        $orderData = $this->bjsClient->getInfo($orderId);

        // Format status emoji
        $statusEmoji = '⏳';
        if (isset($orderInfo->status)) {
            switch (strtolower($orderInfo->status)) {
                case 'completed':
                    $statusEmoji = '✅';
                    break;
                case 'partial':
                    $statusEmoji = '⚠️';
                    break;
                case 'inprogress':
                    $statusEmoji = '🔄';
                    break;
                case 'pending':
                    $statusEmoji = '⏳';
                    break;
                case 'cancel':
                case 'canceled':
                    $statusEmoji = '❌';
                    break;
            }
        }

        // Format the detailed status message
        $this->sendNotification([
            "{$statusEmoji} Order Status Information",
            "Order ID: <code>{$orderId}</code>",
            'Status: ' . ($orderInfo->status_name ?? 'Unknown'),
            '',
            'Service: ' . ($orderInfo->service_name ?? 'Unknown'),
            'Link: ' . ($orderInfo->link ?? 'Unknown'),
            'Quantity: ' . ($orderInfo->count ?? 'Unknown'),
            'Start Count: ' . ($orderInfo->start_count ?? 'Not set'),
            'Remains: ' . ($orderInfo->remains ?? 'N/A'),
            '',
            'Created: ' . (isset($orderInfo->created) ? date('Y-m-d H:i:s', strtotime($orderInfo->created)) : 'N/A'),
            'User: ' . ($orderInfo->user ?? 'N/A'),
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
        Notification::sendNow([$chatId], (new TelegramNotification($messages, $chatId))->formatAs('HTML'));
        $this->info("Notification sent to {$chatId}");
    }
}

