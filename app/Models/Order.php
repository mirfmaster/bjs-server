<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'username',
        'kind',
        'instagram_user_id',
        'target',
        'media_id',
        'requested',
        'margin_request',
        'start_count',
        'processed',
        'partial_count',
        'bjs_id',
        'priority',
        'status',
        'note',
        'started_at',
        'end_at',
        'source',
        'status_bjs',
        'reseller_name',
    ];

    protected $casts = [
        'requested' => 'integer',
        'margin_request' => 'integer',
        'start_count' => 'integer',
        'processed' => 'integer',
        'partial_count' => 'integer',
        'bjs_id' => 'integer',
        'priority' => 'integer',
        'started_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    // Default values
    protected $attributes = [
        'requested' => 0,
        'margin_request' => 0,
        'start_count' => 0,
        'processed' => 0,
        'partial_count' => 0,
        'priority' => 0,
        'status' => 'pending',
        'status_bjs' => 'pending',
    ];
}
