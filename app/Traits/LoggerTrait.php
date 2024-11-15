<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LoggerTrait
{
    protected function logError(\Throwable $th, string $context = null)
    {
        $caller = debug_backtrace()[1];
        $functionName = $caller['function'] ?? 'unknown';
        $ctx = [
            "func" => $context ?? $functionName,
            "class" => get_class($this),
            "line" => $th->getLine()
        ];

        if (config("app.debug")) {
            dump($th->getMessage(), $ctx);
        }

        Log::error($th->getMessage(), $ctx);
    }
}
