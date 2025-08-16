<?php

namespace App\Observers;

use App\Models\Worker;
use App\Models\WorkerStatusEvent;

class WorkerObserver
{
    public function updated(Worker $worker)
    {
        if ($worker->isDirty('status')) {
            WorkerStatusEvent::create([
                'worker_id' => $worker->id,
                'previous_status' => $worker->getOriginal('status'),
                'current_status' => $worker->status,
                'activity' => $worker->activity,   // <-- here
                'meta' => $worker->meta,       // if you also set meta
                'created_at' => now(),
            ]);
        }
    }
}
