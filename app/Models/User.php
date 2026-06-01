<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Lab404\Impersonate\Models\Impersonate;
use Illuminate\Support\Str;




class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use Impersonate;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'p2p_display_name',
        'p2p_ringtone',
        'email',
        'deactivated_at',
        'staff_uuid',
        'is_hr_admin',
        'password',
        'status',
        'business_id',
        'branch_id', // Uncomment if you want to allow branch assignment,
        'service_points',
        'permissions',
        'allowed_branches',
        'qualification_id',
        'department_id',
        'section_id',
        'title_id',
        'gender',
        'phone',
        'nin',
        'profile_photo_path',
        'email_verified_at',
        'remember_token',
        'total_balance',
        'current_balance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'service_points' => 'array',
        'permissions' => 'array',
        'allowed_branches' => 'array',
        'is_hr_admin' => 'boolean',
        'gender' => 'string',
        'phone' => 'string',
        'nin' => 'string',
        'profile_photo_path' => 'string',
        'email_verified_at' => 'datetime',
        'remember_token' => 'string',
        'status' => 'string',
        'total_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public const HR_PERMISSIONS = [
        'View HR Staff',
        'Add HR Staff',
        'Edit HR Staff',
        'View HR Setup',
        'Add HR Setup',
        'Edit HR Setup',
        'View HR Approvals',
        'Edit HR Approvals',
        'Designate HR Roster Approvers',
        'Manage HR Biometrics',
        'Manage AI Roster Constraints',
    ];

    public function getP2pNameAttribute()
    {
        return $this->p2p_display_name ?: $this->name;
    }

    public function getStaffUuidAttribute($value): ?string
    {
        return $value ?: $this->uuid;
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function qualification()
    {
        return $this->belongsTo(\App\Models\Qualification::class);
    }

    public function title()
    {
        return $this->belongsTo(\App\Models\Title::class);
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class);
    }

    public function section()
    {
        return $this->belongsTo(\App\Models\Section::class);
    }

    public function contractorProfile()
    {
        return $this->hasOne(ContractorProfile::class);
    }

    /**
     * Check if user is a cashier (has Cashier permission)
     */
    public function isCashier()
    {
        $permissions = $this->permissions ?? [];
        return in_array('Cashier', $permissions);
    }

    /**
     * Get the current working branch for the user
     */
    public function getCurrentBranchAttribute()
    {
        $currentBranchId = session('current_branch_id', $this->branch_id);
        
        // If we have a branch ID, try to find the branch
        if ($currentBranchId) {
            $branch = Branch::find($currentBranchId);
            if ($branch) {
                return $branch;
            }
        }
        
        // Fallback to the user's assigned branch
        if ($this->branch_id) {
            $branch = Branch::find($this->branch_id);
            if ($branch) {
                return $branch;
            }
        }
        
        // If no branch is found, return null
        return null;
    }

    /**
     * Get the service points assigned to this user
     */
    public function servicePoints()
    {
        if ($this->service_points) {
            return ServicePoint::whereIn('id', $this->service_points);
        }
        return collect();
    }

    /**
     * Get the service queues for this user's service points
     */
    public function serviceQueues()
    {
        if ($this->service_points) {
            return ServiceQueue::whereIn('service_point_id', $this->service_points);
        }
        return collect();
    }

    /**
     * Get pending queues for user's service points
     */
    public function pendingQueues()
    {
        return $this->serviceQueues()->pending()->orderBy('queue_number');
    }

    /**
     * Get in-progress queues for user's service points
     */
    public function inProgressQueues()
    {
        return $this->serviceQueues()->inProgress()->orderBy('started_at');
    }

    /**
     * Get completed queues for user's service points today
     */
    public function completedQueuesToday()
    {
        return $this->serviceQueues()->completed()->today()->orderBy('completed_at', 'desc');
    }

    public static function filterHrPermissions(mixed $permissions): array
    {
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true) ?: [];
        }

        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_intersect(self::HR_PERMISSIONS, self::flattenPermissions($permissions)));
    }

    private static function flattenPermissions(array $permissions): array
    {
        $flattened = [];

        foreach ($permissions as $permission) {
            if (is_array($permission)) {
                $flattened = array_merge($flattened, self::flattenPermissions($permission));
                continue;
            }

            if (is_object($permission)) {
                $flattened = array_merge($flattened, self::flattenPermissions((array) $permission));
                continue;
            }

            if (is_string($permission) && trim($permission) !== '') {
                $flattened[] = trim($permission);
            }
        }

        return array_values(array_unique($flattened));
    }

    public function hasHrPermission(string $permission): bool
    {
        return (bool) ($this->attributes['is_hr_admin'] ?? false)
            || in_array($permission, (array) ($this->permissions ?? []), true);
    }

    public function hasAnyHrPermission(array $permissions): bool
    {
        return (bool) ($this->attributes['is_hr_admin'] ?? false)
            || count(array_intersect($permissions, (array) ($this->permissions ?? []))) > 0;
    }

    public function canViewHrStaff(): bool
    {
        return $this->hasAnyHrPermission(['View HR Staff', 'Add HR Staff', 'Edit HR Staff']);
    }

    public function canAddHrStaff(): bool
    {
        return $this->hasHrPermission('Add HR Staff');
    }

    public function canEditHrStaff(): bool
    {
        return $this->hasHrPermission('Edit HR Staff');
    }

    public function canViewHrSetup(): bool
    {
        return $this->hasAnyHrPermission(['View HR Setup', 'Add HR Setup', 'Edit HR Setup']);
    }

    public function canAddHrSetup(): bool
    {
        return $this->hasHrPermission('Add HR Setup');
    }

    public function canEditHrSetup(): bool
    {
        return $this->hasHrPermission('Edit HR Setup');
    }

    public function canViewHrApprovals(): bool
    {
        return $this->hasAnyHrPermission(['View HR Approvals', 'Edit HR Approvals']);
    }

    public function canViewHrBiometrics(): bool
    {
        return $this->hasAnyHrPermission(['View HR Staff', 'Edit HR Staff', 'Manage HR Biometrics']);
    }

    public function canManageHrBiometrics(): bool
    {
        return $this->hasAnyHrPermission(['Edit HR Staff', 'Manage HR Biometrics']);
    }

    public function canManageAiRosterConstraints(): bool
    {
        return $this->hasHrPermission('Manage AI Roster Constraints');
    }

    public function canEditHrApprovals(): bool
    {
        return $this->hasHrPermission('Edit HR Approvals');
    }

    public function canDesignateHrRosterApprovers(): bool
    {
        return $this->hasHrPermission('Designate HR Roster Approvers');
    }

    public function canViewAllApprovals(): bool
    {
        return $this->canViewHrApprovals();
    }

    public function canSyncHrData(): bool
    {
        return $this->hasAnyHrPermission(['Add HR Staff', 'Edit HR Staff']);
    }

    public function canManageAllApprovals(): bool
    {
        return $this->canEditHrApprovals();
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null && $this->status === 'active';
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            $user->uuid = (string) Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
