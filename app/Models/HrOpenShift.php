<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrOpenShift extends Model
{
    public const SOURCE_LEAVE = 'leave';
    public const SOURCE_OFFSITE_DUTY = 'offsite_duty';
    public const SOURCE_MANUAL = 'manual';

    public const STATUS_OPEN = 'open';
    public const STATUS_FILLED = 'filled';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'organization_id',
        'client_space_unit_id',
        'duty_roster_id',
        'shift_type_id',
        'source_staff_assignment_id',
        'source_duty_roster_entry_id',
        'filled_by_staff_assignment_id',
        'filled_by_user_id',
        'roster_date',
        'discipline_key',
        'discipline_label',
        'expected_headcount',
        'source_type',
        'status',
        'source_reason',
        'filled_at',
        'metadata',
    ];

    protected $casts = [
        'roster_date' => 'date',
        'filled_at' => 'datetime',
        'expected_headcount' => 'integer',
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

    public function clientSpace()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'client_space_unit_id');
    }

    public function dutyRoster()
    {
        return $this->belongsTo(HrDutyRoster::class, 'duty_roster_id');
    }

    public function shiftType()
    {
        return $this->belongsTo(ShiftType::class, 'shift_type_id');
    }

    public function sourceStaffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'source_staff_assignment_id');
    }

    public function sourceRosterEntry()
    {
        return $this->belongsTo(HrDutyRosterEntry::class, 'source_duty_roster_entry_id');
    }

    public function filledByStaffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'filled_by_staff_assignment_id');
    }

    public function filledBy()
    {
        return $this->belongsTo(User::class, 'filled_by_user_id');
    }

    public function bids()
    {
        return $this->hasMany(HrOpenShiftBid::class, 'hr_open_shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
