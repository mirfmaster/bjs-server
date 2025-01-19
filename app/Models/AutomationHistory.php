<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationHistory extends Model
{
    protected $fillable = [
        'url',
        'media_id',
        'media_created_at',
        'instagram_user_id',
        'automation_id',
        'autolike_id',
    ];

    protected $casts = [
        'media_created_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the automation order associated with this history entry.
     */
    public function automationOrder()
    {
        return $this->belongsTo(Order::class, 'automation_id');
    }

    /**
     * Get the autolike order associated with this history entry.
     */
    public function autolikeOrder()
    {
        return $this->belongsTo(Order::class, 'autolike_id');
    }

    /**
     * Scope a query to get entries by Instagram user ID.
     */
    public function scopeByInstagramUser($query, $userId)
    {
        return $query->where('instagram_user_id', $userId);
    }

    /**
     * Scope a query to get entries by media ID.
     */
    public function scopeByMediaId($query, $mediaId)
    {
        return $query->where('media_id', $mediaId);
    }

    /**
     * Scope a query to get entries within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('media_created_at', [$startDate, $endDate]);
    }
}
