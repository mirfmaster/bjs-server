<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming webhook requests from Telegram
     *
     * @return \Illuminate\Http\Response
     */
    public function handleWebhook(Request $request)
    {
        try {
            // Log the incoming webhook data for debugging
            Log::debug('Telegram webhook received', ['payload' => $request->all()]);

            // Get the message data from the request
            $message = $request->input('message', []);
            $text = $message['text'] ?? '';

            // If the message doesn't start with /, it's not a command
            if (! $text || substr($text, 0, 1) !== '/') {
                return response()->json(['status' => 'not_a_command']);
            }

            // Parse the command and parameters
            [$command, $parameters] = $this->parseCommand($text);
            Log::debug('Telegram command params', [$command, $parameters]);

            // Dispatch the job to process the command
            // ProcessTelegramCommand::dispatch($command, $parameters);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Error handling Telegram webhook: '.$e->getMessage(), [
                'exception' => $e,
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse a command string into command name and parameters
     *
     * @return array [command, parameters]
     */
    private function parseCommand(string $text): array
    {
        // Split the text by spaces
        $parts = explode(' ', trim($text));

        // The first part is the command
        $command = strtolower($parts[0]);

        // Remove the command and keep the rest as parameters
        array_shift($parts);
        $parameters = array_filter($parts, function ($value) {
            return $value !== '';
        });

        return [$command, array_values($parameters)];
    }
}
