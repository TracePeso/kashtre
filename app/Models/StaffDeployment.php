<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StaffDeployment extends Model
{
    protected $fillable = [
        'uuid',
        'business_id',
        'user_id',
        'org_assignment_id',
        'org_unit_id',
        'branch_id',
        'client_space_id',
        'external_id',
        'client_space_external_id',
        'position_title',
        'allocation_percent',
        'effective_from',
        'effective_to',
        'purpose',
    ];

    protected $casts = [
        'uuid' => 'string',
        'business_id' => 'integer',
        'user_id' => 'integer',
        'org_assignment_id' => 'integer',
        'org_unit_id' => 'integer',
        'branch_id' => 'integer',
        'client_space_id' => 'integer',
        'allocation_percent' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (StaffDeployment $deployment) {
            $deployment->uuid ??= (string) Str::uuid();
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(OrgAssignment::class, 'org_assignment_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function clientSpace(): BelongsTo
    {
        return $this->belongsTo(ClientSpace::class);
    }
}
