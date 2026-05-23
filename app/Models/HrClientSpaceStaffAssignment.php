<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrClientSpaceStaffAssignment extends Model
{
    public const TYPE_PRIMARY = 'primary';
    public const TYPE_SECONDARY = 'secondary';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'uuid',
        'organization_id',
        'client_space_unit_id',
        'staff_assignment_id',
        'staff_uuid',
        'assignment_type',
        'status',
        'assigned_by_user_id',
        'assigned_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
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

    public function staffAssignment()
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function isSecondary(): bool
    {
        return $this->assignment_type === self::TYPE_SECONDARY;
    }
}
