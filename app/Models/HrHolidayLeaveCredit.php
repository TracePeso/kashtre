<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrHolidayLeaveCredit extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_USED = 'used';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'organization_id',
        'staff_assignment_id',
        'hr_calendar_event_id',
        'source_in_ledger_id',
        'source_out_ledger_id',
        'earned_on',
        'credit_days',
        'status',
        'used_on',
        'notes',
    ];

    protected $casts = [
        'earned_on' => 'date',
        'used_on' => 'date',
        'credit_days' => 'decimal:2',
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

    public function holiday()
    {
        return $this->belongsTo(HrCalendarEvent::class, 'hr_calendar_event_id');
    }

    public function sourceInLedger()
    {
        return $this->belongsTo(HrAttendanceLedger::class, 'source_in_ledger_id');
    }

    public function sourceOutLedger()
    {
        return $this->belongsTo(HrAttendanceLedger::class, 'source_out_ledger_id');
    }
}
