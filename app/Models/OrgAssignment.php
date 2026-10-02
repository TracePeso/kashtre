<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class OrgAssignment extends Model
{
    protected $fillable = [
        'uuid',
        'business_id',
        'user_id',
        'org_unit_id',
        'terminal_org_unit_id',
        'department_id',
        'branch_id',
        'client_space_id',
        'title_id',
        'external_id',
        'position_title',
        'assignment_type',
        'is_primary',
        'hierarchy_level',
        'roster_eligible',
        'client_space_label',
        'scope_note',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'uuid' => 'string',
        'business_id' => 'integer',
        'user_id' => 'integer',
        'org_unit_id' => 'integer',
        'terminal_org_unit_id' => 'integer',
        'department_id' => 'integer',
        'branch_id' => 'integer',
        'client_space_id' => 'integer',
        'title_id' => 'integer',
        'is_primary' => 'boolean',
        'hierarchy_level' => 'integer',
        'roster_eligible' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrgAssignment $assignment) {
            $assignment->uuid ??= (string) Str::uuid();
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

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function terminalOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'terminal_org_unit_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function clientSpace(): BelongsTo
    {
        return $this->belongsTo(ClientSpace::class);
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function reportingRelationship(): HasOne
    {
        return $this->hasOne(ReportingRelationship::class, 'subject_assignment_id');
    }
}
