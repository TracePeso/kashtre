<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyAlert extends Model
{
    protected $fillable = [
        'business_id',
        'service_point_id',
        'service_point_name',
        'room_name',
        'button_name',
        'message',
        'display_message',
        'color',
        'is_active',
        'triggered_by',
        'triggered_at',
        'activated_at',
        'scheduled_announce_at',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'triggered_at'         => 'datetime',
        'activated_at'         => 'datetime',
        'scheduled_announce_at'=> 'datetime',
        'resolved_at'          => 'datetime',
    ];

    public function servicePoint()
    {
        return $this->belongsTo(ServicePoint::class);
    }

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
