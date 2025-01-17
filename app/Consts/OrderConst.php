<?php

namespace App\Consts;

class OrderConst
{
    public const MAX_FOLLOW = 1;

    public const FOLLOW_SERVICES = [164];

    public const LIKE_SERVICES = [167];

    public const MAX_PROCESS_FOLLOW_PER_DAY = 100_000;

    public const MAX_LIKE = 3;

    // BJS ORDER RESPONSE
    // +"status": 4
    // +"status_name": "Canceled"
    public const TO_BJS_STATUS = [
        'inprogress' => 1,
        'completed' => 2,
        'partial' => 3,
        'cancel' => 4,
        'processing' => 5,
    ];

    public const FROM_BJS_STATUS = [
        1 => 'inprogress',
        2 => 'completed',
        3 => 'partial',
        4 => 'cancel',
        5 => 'processing',
    ];
}
