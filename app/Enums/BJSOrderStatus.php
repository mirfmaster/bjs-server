<?php

namespace App\Enums;

enum BJSOrderStatus: int
{
    case PENDING = 0;
    case INPROGRESS = 1;
    case COMPLETED = 2;
    case PARTIAL = 3;
    case CANCELED = 4;
    case PROCESSING = 5;
    case FAIL = 6;
    case ERROR = 7;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'pending',
            self::INPROGRESS => 'inprogress',
            self::COMPLETED => 'completed',
            self::PARTIAL => 'partial',
            self::CANCELED => 'canceled',
            self::PROCESSING => 'processing',
            self::FAIL => 'fail',
            self::ERROR => 'error',
        };
    }

    public static function fromLabel(string $label): ?self
    {
        return match (strtolower($label)) {
            'pending' => self::PENDING,
            'inprogress' => self::INPROGRESS,
            'completed' => self::COMPLETED,
            'partial' => self::PARTIAL,
            'canceled' => self::CANCELED,
            'processing' => self::PROCESSING,
            'fail' => self::FAIL,
            'error' => self::ERROR,
            default => null,
        };
    }
}
