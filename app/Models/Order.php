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
        'price',
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

    /**
     * Get the complete Instagram URL for the target
     */
    public function getInstagramUrl(): string
    {
        return filter_var($this->target, FILTER_VALIDATE_URL)
            ? $this->target
            : "https://instagram.com/{$this->target}";
    }

    /**
     * Get the anonymized URL for the target
     */
    public function getAnonymizedUrl(): string
    {
        return 'https://anon.ws/?to='.urlencode($this->getInstagramUrl());
    }

    // CACHE
    public function state(): OrderState
    {
        return OrderCache::state($this);
    }

    public function markProcessing(): int
    {
        return OrderCache::processing($this);
    }

    public function markProcessed(): int
    {
        return OrderCache::processed($this);
    }

    public function markFailed(): int
    {
        return OrderCache::failed($this);
    }

    public function markDuplicate(): int
    {
        return OrderCache::duplicateInteraction($this);
    }

    public function setStatusCached(string $status): void
    {
        OrderCache::setStatus($this, $status);
    }
}
