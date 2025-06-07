<?php

namespace App\Enums;

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
