<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HrStaffUnavailability extends Model
{
    use SoftDeletes;

    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';

    public const REASON_LEAVE = 'leave';
    public const REASON_SICK = 'sick';
    public const REASON_TRAINING = 'training';
    public const REASON_OFFSITE_DUTY = 'offsite_duty';
    public const REASON_MANUAL_BLOCK = 'manual_block';
    public const REASON_OTHER = 'other';

    protected $fillable = [
        'uuid',
        'organization_id',
        'staff_assignment_id',
        'leave_type_id',
        'approval_request_id',
        'reason_type',
        'title',
        'starts_on',
        'ends_on',
        'status',
        'blocks_rosters',
        'notes',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'blocks_rosters' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvalRequest()
    {
        return $this->belongsTo(HrApprovalRequest::class, 'approval_request_id');
    }

    public function reasonLabel(): string
    {
        return match ($this->reason_type) {
            self::REASON_LEAVE => 'leave',
            self::REASON_OFFSITE_DUTY => 'Official Workshop/Meeting',
            default => str_replace('_', ' ', (string) $this->reason_type),
        };
    }

    public function statusLabel(): string
    {
        $prefix = match ($this->status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };

        return trim($prefix . ' ' . $this->reasonLabel());
    }

    public function allowsAttendanceBypass(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->reason_type === self::REASON_OFFSITE_DUTY;
    }

    public function isActiveForRosters(): bool
    {
        return $this->blocks_rosters
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }
}
