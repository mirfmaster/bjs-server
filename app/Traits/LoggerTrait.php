<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LoggerTrait
{
    protected function logError(\Throwable $th, array $context = null)
    {
        $caller = debug_backtrace()[1];
        $functionName = $caller['function'] ?? 'unknown';
        $ctx = [
            "func" => $functionName,
            "class" => get_class($this),
            "line" => $th->getLine(),
            "context" => $context,
        ];

        if (config("app.debug")) {
            dump($th->getMessage(), $ctx);
        }

        Log::error($th->getMessage(), $ctx);
    }
}
