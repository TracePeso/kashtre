<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RIS Amendment v2.6, Chunk 3: one study's live position in its protocol
 * workflow. Created by WorkflowEngineService::startWorkflow()/
 * resolveOrStartExecution() — never created or mutated directly.
 */
class ImagingStudyWorkflowExecution extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'imaging_study_id',
        'imaging_protocol_workflow_id',
        'current_protocol_workflow_step_id',
        'status',
    ];

    public function study()
    {
        return $this->belongsTo(ImagingStudy::class, 'imaging_study_id');
    }

    public function protocolWorkflow()
    {
        return $this->belongsTo(ProtocolWorkflow::class, 'imaging_protocol_workflow_id');
    }

    public function currentStep()
    {
        return $this->belongsTo(ProtocolWorkflowStep::class, 'current_protocol_workflow_step_id');
    }

    public function stepExecutions()
    {
        return $this->hasMany(ImagingWorkflowStepExecution::class, 'imaging_study_workflow_execution_id');
    }

    /**
     * RIS Amendment v2.6, Chunk 4: ownership history for this execution.
     */
    public function claims()
    {
        return $this->hasMany(ImagingWorkflowClaim::class, 'imaging_study_workflow_execution_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
