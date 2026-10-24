<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title', 'message', 'category',
        'is_high_priority', 'is_archived', 'publish_at',
    ];

    protected $casts = [
        'publish_at'       => 'datetime',
        'is_high_priority' => 'boolean',
        'is_archived'      => 'boolean',
    ];

    // Visible to customers (also used later for the bell icon)
    public function scopeActive(Builder $query)
    {
        return $query->where('is_archived', false)->where('publish_at', '<=', now());
    }

    public function scopeScheduled(Builder $query)
    {
        return $query->where('is_archived', false)->where('publish_at', '>', now());
    }

    // $announcement->status gives: archived, scheduled, or active
    public function getStatusAttribute()
    {
        if ($this->is_archived) return 'archived';
        if ($this->publish_at->isFuture()) return 'scheduled';
        return 'active';
    }

    // Small icon for each category
    public function getIconAttribute()
    {
        return match ($this->category) {
            'Maintenance' => '🛠️',
            'Event'       => '🎉',
            'Promotion'   => '🏷️',
            default       => '📢',
        };
    }
}