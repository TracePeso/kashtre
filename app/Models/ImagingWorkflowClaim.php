<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RIS Amendment v2.6, Chunk 4: ownership of a study at its current workflow
 * step. Created/released by WorkflowOwnershipService — never mutated
 * directly. An execution has at most one active (released_at null) claim at
 * a time; WorkflowEngineService::completeStep() auto-releases it when the
 * study advances, so a fresh claim is needed at each step.
 */
class ImagingWorkflowClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_workflow_execution_id',
        'imaging_protocol_workflow_step_id',
        'assigned_user_id',
        'claimed_at',
        'released_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function studyExecution()
    {
        return $this->belongsTo(ImagingStudyWorkflowExecution::class, 'imaging_study_workflow_execution_id');
    }

    public function protocolWorkflowStep()
    {
        return $this->belongsTo(ProtocolWorkflowStep::class, 'imaging_protocol_workflow_step_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('released_at');
    }

    /**
     * assigned_user_id is a plain column, not a real FK (same cross-domain
     * decoupling rule as every other Main Module User reference in this
     * module) — a lookup, not an Eloquent relation.
     */
    public function resolveAssignedUser(): ?User
    {
        return $this->assigned_user_id ? User::find($this->assigned_user_id) : null;
    }
}
