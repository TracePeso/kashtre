<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Organization extends Model
{
    protected $fillable = [
        'uuid',
        'external_business_uuid',
        'name',
        'weekend_days',
        'biometric_network_restriction_enabled',
        'biometric_allowed_networks',
        'biometric_geofence_enabled',
        'biometric_geofence_latitude',
        'biometric_geofence_longitude',
        'biometric_geofence_radius_meters',
        'biometric_geofence_max_accuracy_meters',
        'biometric_geofence_locations',
        'biometric_late_clock_in_enabled',
        'biometric_late_clock_in_threshold_minutes',
        'biometric_late_clock_in_repeat_count',
        'allow_cross_branch_locum_coverage',
    ];

    protected $casts = [
        'weekend_days' => 'array',
        'biometric_network_restriction_enabled' => 'boolean',
        'biometric_allowed_networks' => 'array',
        'biometric_geofence_enabled' => 'boolean',
        'biometric_geofence_latitude' => 'float',
        'biometric_geofence_longitude' => 'float',
        'biometric_geofence_radius_meters' => 'integer',
        'biometric_geofence_max_accuracy_meters' => 'integer',
        'biometric_geofence_locations' => 'array',
        'biometric_late_clock_in_enabled' => 'boolean',
        'biometric_late_clock_in_threshold_minutes' => 'integer',
        'biometric_late_clock_in_repeat_count' => 'integer',
        'allow_cross_branch_locum_coverage' => 'boolean',
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

    public static function current(?User $user = null): ?self
    {
        $user ??= Auth::user();
        $sessionOrganizationId = null;

        try {
            $sessionOrganizationId = session('current_organization_id');
        } catch (\Throwable) {
            $sessionOrganizationId = null;
        }

        if ($user?->staff_uuid) {
            $organization = static::whereHas('staffAssignments', fn ($query) => $query->where('staff_uuid', $user->staff_uuid))
                ->orderBy('id')
                ->first();

            if ($organization) {
                try {
                    session(['current_organization_id' => $organization->id]);
                } catch (\Throwable) {
                }

                return $organization;
            }
        }

        if ($user?->business?->uuid) {
            $organization = static::where('external_business_uuid', $user->business->uuid)->first();

            if ($organization) {
                try {
                    session(['current_organization_id' => $organization->id]);
                } catch (\Throwable) {
                }

                return $organization;
            }
        }

        if ($sessionOrganizationId) {
            $organization = static::find($sessionOrganizationId);

            if ($organization) {
                return $organization;
            }
        }

        return static::where('external_business_uuid', '!=', 'demo-business-uuid')
            ->orderBy('id')
            ->first()
            ?? static::orderBy('id')->first();
    }

    public function staffAssignments()
    {
        return $this->hasMany(StaffAssignment::class);
    }

    public function biometricProfiles()
    {
        return $this->hasMany(HrBiometricProfile::class);
    }

    public function biometricVerifications()
    {
        return $this->hasMany(HrBiometricVerification::class);
    }

    public function biometricDevices()
    {
        return $this->hasMany(HrBiometricDevice::class);
    }

    public function clientSpaces()
    {
        return $this->hasMany(HrClientSpace::class);
    }

    public function approvalWorkflows()
    {
        return $this->hasMany(ApprovalWorkflow::class);
    }

    public function approvalRequests()
    {
        return $this->hasMany(HrApprovalRequest::class);
    }

    public function shiftTypes()
    {
        return $this->hasMany(ShiftType::class);
    }

    public function leaveTypes()
    {
        return $this->hasMany(LeaveType::class);
    }

    public function calendarEvents()
    {
        return $this->hasMany(HrCalendarEvent::class);
    }

    public function regionalPolicies()
    {
        return $this->hasMany(HrRegionalPolicy::class);
    }

    public function policyVersions()
    {
        return $this->hasMany(HrPolicyVersion::class);
    }

    public function staffUnavailabilities()
    {
        return $this->hasMany(HrStaffUnavailability::class);
    }

    public function staffRosteringProfiles()
    {
        return $this->hasMany(HrStaffRosteringProfile::class);
    }

    public function openShifts()
    {
        return $this->hasMany(HrOpenShift::class);
    }
}
