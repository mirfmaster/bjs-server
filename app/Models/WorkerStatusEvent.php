<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkerStatusEvent extends Model
{
    use HasFactory;

    protected $table = 'worker_status_events';

    protected $fillable = [
        'worker_id',
        'previous_status',
        'current_status',
        'activity',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function worker()
    {
        return $this->belongsTo(Worker::class, 'worker_id', 'id');
    }
}
