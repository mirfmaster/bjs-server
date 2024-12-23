<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'name',
        'modem_type',
        'apn',
        'version',
        'phone_number',
        'status',
        'mode',
        'last_activity',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function statistics()
    {
        return $this->hasOne(DeviceStatistic::class);
    }
}
