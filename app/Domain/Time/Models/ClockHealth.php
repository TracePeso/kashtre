<?php

namespace App\Domain\Time\Models;

use Illuminate\Database\Eloquent\Model;

class ClockHealth extends Model
{
    protected $table = 'core_time_clock_health';

    protected $fillable = [
        'node_key',
        'status',
        'drift_seconds',
        'source',
        'checked_at_utc',
        'metadata',
    ];

    protected $casts = [
        'checked_at_utc' => 'datetime',
        'metadata' => 'array',
        'drift_seconds' => 'integer',
    ];
}
