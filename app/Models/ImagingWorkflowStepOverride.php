<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RIS Amendment v2.6, Chunk 5: an audit record that a blocked step
 * completion was overridden — created by
 * CompletionRuleService::recordOverride(), immutable afterward.
 */
class ImagingWorkflowStepOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_workflow_execution_id',
        'imaging_protocol_workflow_step_id',
        'user_id',
        'reason',
    ];

    public function studyExecution()
    {
        return $this->belongsTo(ImagingStudyWorkflowExecution::class, 'imaging_study_workflow_execution_id');
    }

    public function protocolWorkflowStep()
    {
        return $this->belongsTo(ProtocolWorkflowStep::class, 'imaging_protocol_workflow_step_id');
    }

    /**
     * user_id is a plain column, not a real FK (same cross-domain
     * decoupling rule as every other Main Module User reference in this
     * module) — a lookup, not an Eloquent relation.
     */
    public function resolveUser(): ?User
    {
        return $this->user_id ? User::find($this->user_id) : null;
    }
}
