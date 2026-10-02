<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrgUnit extends Model
{
    protected $fillable = [
        'uuid',
        'business_id',
        'branch_id',
        'department_id',
        'parent_id',
        'head_user_id',
        'head_assignment_id',
        'external_id',
        'name',
        'org_unit_type',
        'head_name',
        'head_assignment_external_id',
        'org_level',
        'is_terminal',
        'org_path',
    ];

    protected $casts = [
        'uuid' => 'string',
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'department_id' => 'integer',
        'parent_id' => 'integer',
        'head_user_id' => 'integer',
        'head_assignment_id' => 'integer',
        'org_level' => 'integer',
        'is_terminal' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrgUnit $unit) {
            $unit->uuid ??= (string) Str::uuid();
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function headUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function headAssignment(): BelongsTo
    {
        return $this->belongsTo(OrgAssignment::class, 'head_assignment_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrgAssignment::class);
    }
}
