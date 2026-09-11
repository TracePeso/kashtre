<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalBreakGlassLog extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_break_glass_logs';

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'user_id',
        'client_id',
        'visit_id',
        'reason_code',
        'justification_note',
        'granted_until',
        'created_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'user_id' => 'integer',
        'granted_until' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClinicalBreakGlassLog $log) {
            $log->created_at ??= now();
        });
    }
}
