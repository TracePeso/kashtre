<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrBiometricVerification extends Model
{
    public const RESULT_SUCCESS = 'success';
    public const RESULT_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'organization_id',
        'hr_biometric_profile_id',
        'staff_assignment_id',
        'staff_uuid',
        'modality',
        'result',
        'score',
        'threshold',
        'provider',
        'device_id',
        'source_event_id',
        'event_type',
        'verified_by_user_id',
        'verified_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'score' => 'float',
        'threshold' => 'float',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function profile()
    {
        return $this->belongsTo(HrBiometricProfile::class, 'hr_biometric_profile_id');
    }

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function attendanceLedger()
    {
        return $this->hasOne(HrAttendanceLedger::class, 'hr_biometric_verification_id');
    }

    public function passed(): bool
    {
        return $this->result === self::RESULT_SUCCESS;
    }
}
