<?php

namespace App\Actions\BJS;

use App\Client\BJSClient;
use Illuminate\Support\Facades\Log;

class CancelBJSOrderAction
{
    public function handle(BJSClient $bjs, $bjs_id, $reason = null)
    {
        Log::info("Cancelling order $bjs_id");
        if ($reason) {
            $bjs->addCancelReason($bjs_id, $reason);
        }

        return $bjs->cancelOrder($bjs_id);
    }
}
