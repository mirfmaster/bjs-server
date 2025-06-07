<?php

namespace App\Enums;

// NOTE: Flow
// - First accepted order is inprogress
// - After worker start deal with the order status changed as processing
// - After processing its changed by the worker
//
// NOTE:
// - Worker only deal status on cache level
// - Server deal db and bjs status after changed by worker
// - Order status update after inprogress (processing, (cancel, partial, completed))
enum OrderStatus: string
{
    case COMPLETED = 'completed';
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case CANCEL = 'cancel';
    case PROCESSING = 'processing';
    case INPROGRESS = 'inprogress';
    case UNKNOWN = 'unknown';

    public function isProcessable(): bool
    {
        return match ($this) {
            self::INPROGRESS, self::PROCESSING => true,
            default => false,
        };
    }
}
