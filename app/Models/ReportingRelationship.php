<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportingRelationship extends Model
{
    protected $fillable = [
        'uuid',
        'business_id',
        'subject_assignment_id',
        'subject_user_id',
        'line_manager_assignment_id',
        'approval_parent_assignment_id',
        'approval_terminal_assignment_id',
        'hierarchy_level',
        'approval_route',
        'approval_path_names',
        'approval_path_external_ids',
        'approval_depth',
        'notes',
    ];

    protected $casts = [
        'uuid' => 'string',
        'business_id' => 'integer',
        'subject_assignment_id' => 'integer',
        'subject_user_id' => 'integer',
        'line_manager_assignment_id' => 'integer',
        'approval_parent_assignment_id' => 'integer',
        'approval_terminal_assignment_id' => 'integer',
        'hierarchy_level' => 'integer',
        'approval_path_external_ids' => 'array',
        'approval_depth' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReportingRelationship $relationship) {
            $relationship->uuid ??= (string) Str::uuid();
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subjectAssignment(): BelongsTo
    {
        return $this->belongsTo(OrgAssignment::class, 'subject_assignment_id');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function lineManagerAssignment(): BelongsTo
    {
        return $this->belongsTo(OrgAssignment::class, 'line_manager_assignment_id');
    }

    public function approvalParentAssignment(): BelongsTo
    {
        return $this->belongsTo(OrgAssignment::class, 'approval_parent_assignment_id');
    }

    public function approvalTerminalAssignment(): BelongsTo
    {
        return $this->belongsTo(OrgAssignment::class, 'approval_terminal_assignment_id');
    }
}
