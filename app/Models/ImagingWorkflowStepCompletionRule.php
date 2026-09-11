<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RIS Amendment v2.6, Chunk 5: one requirement that must be satisfied
 * before a study can be moved into its parent ProtocolWorkflowStep.
 * Evaluated by CompletionRuleService::validateStepCompletion() — never
 * checked directly by callers.
 */
class ImagingWorkflowStepCompletionRule extends Model
{
    use HasFactory;

    const TYPE_FIELD = 'FIELD';
    const TYPE_CHECKLIST = 'CHECKLIST';
    const TYPE_ATTACHMENT = 'ATTACHMENT';
    const TYPE_SIGNATURE = 'SIGNATURE';

    const TYPES = [
        self::TYPE_FIELD,
        self::TYPE_CHECKLIST,
        self::TYPE_ATTACHMENT,
        self::TYPE_SIGNATURE,
    ];

    const SOURCE_MANUAL = 'manual';
    const SOURCE_LEGACY_SYNC = 'legacy_sync';

    protected $fillable = [
        'imaging_protocol_workflow_step_id',
        'rule_type',
        'rule_key',
        'is_required',
        'allow_override',
        'authorized_override_permissions',
        'source',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'allow_override' => 'boolean',
        'authorized_override_permissions' => 'array',
    ];

    public function protocolWorkflowStep()
    {
        return $this->belongsTo(ProtocolWorkflowStep::class, 'imaging_protocol_workflow_step_id');
    }

    public function scopeLegacySynced($query)
    {
        return $query->where('source', self::SOURCE_LEGACY_SYNC);
    }
}
