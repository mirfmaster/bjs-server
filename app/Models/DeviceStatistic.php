<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceStatistic extends Model
{
    protected $fillable = [
        'device_id',
        'success_task_counter',
        'connection_status',
        'restart_modem_counter',
        'errors_counter',
    ];

    protected $casts = [
        'errors_counter' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
