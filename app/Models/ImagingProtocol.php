<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagingProtocol extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'modality_type',
        'involves_ionizing_radiation',
        'is_active',
        'requires_consent',
        'requires_preparation',
        'is_contrast_enhanced',
        'requires_recovery',
        'preparation_requirements',
        'readiness_checks',
        'reporting_template',
        'consumables_recipe',
        'default_contrast_item_id',
        'default_contrast_volume_ml',
        'default_kvp_metrics',
        'consumption_trigger_status',
    ];

    // Pillar 12.1: which ImagingStudy lifecycle point consumption fires at
    // for this protocol — see ImagingStudy::triggerConsumptionIfDue().
    const TRIGGER_PREPARATION_COMPLETE = 'PREPARATION_COMPLETE';
    const TRIGGER_IN_PROGRESS = 'IN_PROGRESS';
    const TRIGGER_IMAGE_ACQUIRED = 'IMAGE_ACQUIRED';
    const TRIGGER_RECOVERY_COMPLETE = 'RECOVERY_COMPLETE';

    const CONSUMPTION_TRIGGERS = [
        self::TRIGGER_PREPARATION_COMPLETE,
        self::TRIGGER_IN_PROGRESS,
        self::TRIGGER_IMAGE_ACQUIRED,
        self::TRIGGER_RECOVERY_COMPLETE,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'involves_ionizing_radiation' => 'boolean',
        'requires_consent' => 'boolean',
        'requires_preparation' => 'boolean',
        'is_contrast_enhanced' => 'boolean',
        'requires_recovery' => 'boolean',
        'preparation_requirements' => 'array',
        'readiness_checks' => 'array',
        'reporting_template' => 'array',
        'consumables_recipe' => 'array',
        'default_contrast_volume_ml' => 'decimal:2',
    ];

    protected static function booted()
    {
        // RIS Amendment v2.6, Chunk 5: keeps imaging_workflow_step_completion_rules
        // in sync with this protocol's own preparation_requirements/
        // readiness_checks/requires_consent fields — those fields stay the
        // source of truth, still edited through the existing admin form
        // unchanged; this just mirrors them into the new rule-engine tables
        // CompletionRuleService actually reads, so an admin editing a
        // protocol's checklist never has to know the rule engine exists.
        static::saved(function (ImagingProtocol $protocol) {
            $protocol->syncLegacyCompletionRules();
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function workflows()
    {
        return $this->hasMany(ProtocolWorkflow::class, 'imaging_protocol_id');
    }

    /**
     * RIS Amendment v2.6: the workflow WorkflowEngineService::startWorkflow()
     * resolves for a new study — there should only ever be one, enforced by
     * the admin UI, but latest() is a safe tiebreaker if that's ever violated.
     */
    public function activeWorkflow(): ?ProtocolWorkflow
    {
        return $this->workflows()->active()->latest('workflow_version')->first();
    }

    /**
     * default_contrast_item_id is a plain indexed column, not a FK (same
     * cross-domain-decoupling rule as everywhere else in this module) —
     * a lookup, not a real Eloquent relation.
     */
    public function resolveDefaultContrastItem(): ?Item
    {
        return $this->default_contrast_item_id
            ? Item::find($this->default_contrast_item_id)
            : null;
    }

    /**
     * Regenerates this protocol's legacy_sync completion rules (Chunk 5)
     * from its current preparation_requirements/readiness_checks/
     * requires_consent fields — a no-op if it has no active workflow yet
     * (nothing to attach rules to until one exists). Idempotent: safe to
     * call on every save, and deliberately re-called from
     * ManageProtocolWorkflow::save() too, since that method recreates every
     * ProtocolWorkflowStep row on each save (cascade-deleting whatever
     * completion rules were attached to the old ones).
     */
    public function syncLegacyCompletionRules(): void
    {
        $workflow = $this->activeWorkflow();

        if (! $workflow) {
            return;
        }

        $prepCompleteStep = $workflow->steps()->where('ris_status', ImagingStudy::STATUS_PREPARATION_COMPLETE)->first();

        if ($prepCompleteStep) {
            $this->replaceLegacySyncedRules($prepCompleteStep, collect($this->preparation_requirements ?? [])
                ->map(fn ($code) => [
                    'rule_type' => ImagingWorkflowStepCompletionRule::TYPE_CHECKLIST,
                    'rule_key' => $code,
                    'is_required' => true,
                    'allow_override' => false,
                ])
                ->all());
        }

        $readyStep = $workflow->steps()->where('ris_status', ImagingStudy::STATUS_READY_FOR_STUDY)->first();

        if ($readyStep) {
            $rules = collect($this->readiness_checks ?? [])
                ->map(fn ($code) => [
                    'rule_type' => ImagingWorkflowStepCompletionRule::TYPE_CHECKLIST,
                    'rule_key' => $code,
                    'is_required' => true,
                    'allow_override' => false,
                ])
                ->all();

            if ($this->requires_consent) {
                $rules[] = [
                    'rule_type' => ImagingWorkflowStepCompletionRule::TYPE_SIGNATURE,
                    'rule_key' => 'consent',
                    'is_required' => true,
                    'allow_override' => false,
                ];
            }

            $this->replaceLegacySyncedRules($readyStep, $rules);
        }
    }

    /**
     * Wipes and recreates just the legacy_sync rows for $step — any
     * manually-added rule (a different source value) is left untouched.
     */
    protected function replaceLegacySyncedRules(ProtocolWorkflowStep $step, array $rules): void
    {
        $step->completionRules()->legacySynced()->delete();

        foreach ($rules as $rule) {
            $step->completionRules()->create(array_merge($rule, [
                'source' => ImagingWorkflowStepCompletionRule::SOURCE_LEGACY_SYNC,
            ]));
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModality($query, string $modalityType)
    {
        return $query->where('modality_type', $modalityType);
    }

    /**
     * System-wide protocols (business_id null) plus any business-specific ones.
     */
    public function scopeAvailableToBusiness($query, ?int $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->whereNull('business_id');

            if ($businessId) {
                $q->orWhere('business_id', $businessId);
            }
        });
    }
}
