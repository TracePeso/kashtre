<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class P2PCall extends Model
{
    protected $table = 'p2p_calls';

    protected $guarded = [];

    protected $casts = [
        'started_at'  => 'datetime',
        'answered_at' => 'datetime',
        'ended_at'    => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($call) {
            $call->uuid = (string) Str::uuid();
            $call->started_at = $call->started_at ?? now();
        });
    }

    // ── Relationships ──────────────────────────────────

    public function caller()
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee()
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    // ── Accessors ──────────────────────────────────────

    /**
     * Duration in seconds (only for answered calls)
     */
    public function getDurationAttribute()
    {
        if ($this->answered_at && $this->ended_at) {
            return $this->answered_at->diffInSeconds($this->ended_at);
        }
        return 0;
    }

    /**
     * Human-readable duration (e.g. "2:35")
     */
    public function getFormattedDurationAttribute()
    {
        $seconds = $this->duration;
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $secs);
    }

    // ── Scopes ─────────────────────────────────────────

    public function scopeForUser($query, $userId)
    {
        return $query->where('caller_id', $userId)
                     ->orWhere('callee_id', $userId);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeMissed($query)
    {
        return $query->where('status', 'missed');
    }

    /**
     * Get the "other" user in a call relative to the given user ID
     */
    public function getOtherUser($userId)
    {
        return $this->caller_id == $userId ? $this->callee : $this->caller;
    }

    /**
     * Check if the given user is the caller
     */
    public function isCaller($userId)
    {
        return $this->caller_id == $userId;
    }
}
