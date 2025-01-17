
<?php

namespace App\Consts;

class BJSConst
{
    public const PENDING = 0;

    public const INPROGRESS = 1;

    public const COMPLETED = 2;

    public const PARTIAL = 3;

    public const CANCELED = 4;

    public const PROCESSING = 5;

    public const FAIL = 6;

    public const ERROR = 7;

    public const TO_STRING = [
        self::PENDING => 'pending',
        self::INPROGRESS => 'inprogress',
        self::COMPLETED => 'completed',
        self::PARTIAL => 'partial',
        self::CANCELED => 'canceled',
        self::PROCESSING => 'processing',
        self::FAIL => 'fail',
        self::ERROR => 'error',
    ];

    public const TO_CODE = [
        'pending' => self::PENDING,
        'inprogress' => self::INPROGRESS,
        'completed' => self::COMPLETED,
        'partial' => self::PARTIAL,
        'canceled' => self::CANCELED,
        'processing' => self::PROCESSING,
        'fail' => self::FAIL,
        'error' => self::ERROR,
    ];
}
