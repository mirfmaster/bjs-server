<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountStatusEvent extends Model
{
    use HasFactory;

    protected $table = 'account_status_events';

    protected $fillable = [
        'account_id',
        'previous_status',
        'current_status',
        'activity',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
