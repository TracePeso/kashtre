<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RIS Amendment v2.6, Chunk 2: one ordered slot in a ProtocolWorkflow —
 * which reusable ImagingWorkflowStep sits at this position, which RIS
 * status it maps to (this app's existing ImagingStudy status vocabulary),
 * which coarse Main Module status bucket it falls into, and whether
 * reaching it should trigger inventory consumption (wired for real in
 * Chunk 6 — this flag is composed data only until then).
 */
class ProtocolWorkflowStep extends Model
{
    use HasFactory;

    protected $table = 'imaging_protocol_workflow_steps';

    const MAIN_STATUS_PENDING = 'PENDING';
    const MAIN_STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const MAIN_STATUS_COMPLETED = 'COMPLETED';

    const MAIN_STATUSES = [
        self::MAIN_STATUS_PENDING,
        self::MAIN_STATUS_IN_PROGRESS,
        self::MAIN_STATUS_COMPLETED,
    ];

    protected $fillable = [
        'imaging_protocol_workflow_id',
        'imaging_workflow_step_id',
        'sequence_no',
        'ris_status',
        'main_status',
        'triggers_consumption',
    ];

    protected $casts = [
        'triggers_consumption' => 'boolean',
    ];

    public function protocolWorkflow()
    {
        return $this->belongsTo(ProtocolWorkflow::class, 'imaging_protocol_workflow_id');
    }

    public function workflowStep()
    {
        return $this->belongsTo(ImagingWorkflowStep::class, 'imaging_workflow_step_id');
    }

    /**
     * RIS Amendment v2.6, Chunk 5: what must be satisfied to move a study
     * into this step — see CompletionRuleService::validateStepCompletion().
     */
    public function completionRules()
    {
        return $this->hasMany(ImagingWorkflowStepCompletionRule::class, 'imaging_protocol_workflow_step_id');
    }
}
