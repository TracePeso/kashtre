<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrAttendanceLedger extends Model
{
    public const PUNCH_IN = 'in';
    public const PUNCH_OUT = 'out';

    public const STATUS_OPEN = 'open';
    public const STATUS_PAIRED = 'paired';
    public const STATUS_IGNORED = 'ignored';

    protected $table = 'hr_attendance_ledger';

    protected $fillable = [
        'uuid',
        'organization_id',
        'staff_assignment_id',
        'staff_uuid',
        'hr_biometric_verification_id',
        'roster_entry_id',
        'client_space_unit_id',
        'shift_type_id',
        'punch_type',
        'punch_source',
        'provider',
        'device_id',
        'source_event_id',
        'occurred_at',
        'paired_with_id',
        'status',
        'is_late_clock_in',
        'minutes_late',
        'is_late_flagged',
        'ignored_reason',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'is_late_clock_in' => 'boolean',
        'minutes_late' => 'integer',
        'is_late_flagged' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }

    public function biometricVerification()
    {
        return $this->belongsTo(HrBiometricVerification::class, 'hr_biometric_verification_id');
    }

    public function rosterEntry()
    {
        return $this->belongsTo(HrDutyRosterEntry::class, 'roster_entry_id');
    }

    public function clientSpace()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'client_space_unit_id');
    }

    public function shiftType()
    {
        return $this->belongsTo(ShiftType::class, 'shift_type_id');
    }

    public function pairedWith()
    {
        return $this->belongsTo(self::class, 'paired_with_id');
    }

    public function isIgnored(): bool
    {
        return $this->status === self::STATUS_IGNORED;
    }

    public function isLateClockIn(): bool
    {
        return $this->punch_type === self::PUNCH_IN && $this->is_late_clock_in;
    }
}
