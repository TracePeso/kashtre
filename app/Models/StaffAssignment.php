<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StaffAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'hr_staff_assignments';

    protected $fillable = [
        'uuid', 'organization_id', 'organizational_unit_id', 'staff_uuid', 'staff_name', 'staff_cadre',
        'staff_department', 'staff_title',
        'home_branch_external_id', 'home_branch_name',
        'assignment_type', 'assigned_by', 'routed_by_user_id', 'routed_by_staff_uuid',
        'status', 'assigned_at', 'routed_at', 'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'routed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
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

    public function organizationalUnit()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'organizational_unit_id');
    }

    public function routedBy()
    {
        return $this->belongsTo(User::class, 'routed_by_user_id');
    }

    public function routingEvents()
    {
        return $this->hasMany(HrStaffRoutingEvent::class);
    }

    public function biometricProfiles()
    {
        return $this->hasMany(HrBiometricProfile::class);
    }

    public function biometricVerifications()
    {
        return $this->hasMany(HrBiometricVerification::class);
    }

    public function clientSpaceStaffAssignments()
    {
        return $this->hasMany(HrClientSpaceStaffAssignment::class, 'staff_assignment_id');
    }

    public function unavailabilities()
    {
        return $this->hasMany(HrStaffUnavailability::class, 'staff_assignment_id');
    }

    public function rosteringProfile()
    {
        return $this->hasOne(HrStaffRosteringProfile::class, 'staff_assignment_id');
    }

    public function attendanceLedger()
    {
        return $this->hasMany(HrAttendanceLedger::class, 'staff_assignment_id');
    }

    public function dutyRosterEntries()
    {
        return $this->hasMany(HrDutyRosterEntry::class, 'staff_assignment_id');
    }

    public function openShiftBids()
    {
        return $this->hasMany(HrOpenShiftBid::class, 'staff_assignment_id');
    }

    public function filledOpenShifts()
    {
        return $this->hasMany(HrOpenShift::class, 'filled_by_staff_assignment_id');
    }

    public function currentRoutingManager(): ?User
    {
        $unit = $this->organizationalUnit;

        if (! $unit?->isRoutingNode()) {
            return null;
        }

        $staffUuid = $unit->staffAssignments()
            ->whereNotIn('status', ['inactive', 'orphaned'])
            ->whereNotNull('staff_uuid')
            ->orderBy('staff_name')
            ->value('staff_uuid');

        return $staffUuid ? User::where('staff_uuid', $staffUuid)->first() : null;
    }

    public function scopeOrphaned(Builder $query): Builder
    {
        return $query->where('status', 'orphaned');
    }

    public function scopeWithoutActiveClientSpace(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', ['inactive', 'pending_routing'])
            ->whereDoesntHave('clientSpaceStaffAssignments', function (Builder $assignmentQuery): void {
                $assignmentQuery->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE);
            });
    }

    public function scopeStuckInRouting(Builder $query, Carbon $cutoff): Builder
    {
        return $query->where('status', 'pending_routing')
            ->where(function (Builder $inner) use ($cutoff): void {
                $inner->where('routed_at', '<=', $cutoff)
                    ->orWhere(function (Builder $fallback) use ($cutoff): void {
                        $fallback->whereNull('routed_at')
                            ->where('assigned_at', '<=', $cutoff);
                    });
            })
            ->whereHas('organizationalUnit', function (Builder $unitQuery): void {
                $unitQuery->where('unit_kind', HrOrganizationalUnit::KIND_ROUTING_NODE);
            });
    }
}
