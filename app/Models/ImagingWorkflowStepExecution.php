<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RIS Amendment v2.6, Chunk 3: history — one row per step a study has
 * actually completed. Created by WorkflowEngineService::completeStep().
 */
class ImagingWorkflowStepExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_study_workflow_execution_id',
        'imaging_protocol_workflow_step_id',
        'executed_by',
        'room_id',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
     * executed_by is a plain column, not a real FK (cross-domain
     * decoupling rule) — a lookup, not an Eloquent relation.
     */
    public function resolveExecutedByUser(): ?User
    {
        return $this->executed_by ? User::find($this->executed_by) : null;
    }

    /**
     * room_id is a plain column, not a real FK — the room comes from the
     * session/selected-room framework, not a registry this module owns.
     */
    public function resolveRoom(): ?Room
    {
        return $this->room_id ? Room::find($this->room_id) : null;
    }
}
