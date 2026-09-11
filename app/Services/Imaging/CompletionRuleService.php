<?php

namespace App\Services\Imaging;

use App\Models\ImagingStudy;
use App\Models\ImagingWorkflowStepCompletionRule;
use App\Models\ImagingWorkflowStepOverride;
use App\Models\ProtocolWorkflowStep;
use App\Models\User;

/**
 * RIS Amendment v2.6, Chunk 5. The single place completion requirements
 * are evaluated — replaces the ad hoc direct protocol-field reads that
 * used to be scattered across ImagingStudy's individual gate methods.
 * ImagingStudy still falls back to those same direct reads when a study's
 * protocol has no real, persisted workflow to attach rules to (a bare
 * in-memory ImagingProtocol, as unit tests use, or a protocol nobody has
 * configured a workflow for yet) — see ImagingStudy::resolveWorkflowStepByRisStatus().
 */
class CompletionRuleService
{
    /**
     * Evaluates every (optionally type-filtered) rule attached to $step —
     * $step being null (no real workflow to check against) always passes,
     * since ImagingStudy's caller is expected to fall back to its own
     * direct check in that case rather than treat "no step" as "blocked".
     */
    public function validateStepCompletion(ImagingStudy $study, ?ProtocolWorkflowStep $step, ?string $onlyType = null): bool|array
    {
        if (! $step) {
            return true;
        }

        $errors = [];

        $rules = $step->completionRules()->where('is_required', true)
            ->when($onlyType, fn ($q) => $q->where('rule_type', $onlyType))
            ->get();

        foreach ($rules as $rule) {
            if ($this->isRuleSatisfied($study, $rule)) {
                continue;
            }

            $errors[] = [
                'rule_id' => $rule->id,
                'rule_type' => $rule->rule_type,
                'rule_key' => $rule->rule_key,
                'message' => $this->buildMessage($rule),
                'allow_override' => $rule->allow_override,
                'authorized_override_permissions' => $rule->authorized_override_permissions ?? [],
            ];
        }

        return empty($errors) ? true : $errors;
    }

    protected function isRuleSatisfied(ImagingStudy $study, ImagingWorkflowStepCompletionRule $rule): bool
    {
        return match ($rule->rule_type) {
            ImagingWorkflowStepCompletionRule::TYPE_CHECKLIST => (bool) ($study->readiness_check_results[$rule->rule_key] ?? false),
            ImagingWorkflowStepCompletionRule::TYPE_SIGNATURE => $this->isSignatureSatisfied($study, $rule->rule_key),
            ImagingWorkflowStepCompletionRule::TYPE_FIELD => $this->isFieldSatisfied($study, $rule->rule_key),
            // No generic attachment/upload mechanism exists anywhere in this
            // module yet — fails safe (blocks) rather than silently passing
            // a requirement nothing can actually verify.
            ImagingWorkflowStepCompletionRule::TYPE_ATTACHMENT => false,
            default => false,
        };
    }

    protected function isSignatureSatisfied(ImagingStudy $study, string $ruleKey): bool
    {
        return match ($ruleKey) {
            'consent' => (bool) $study->consent_verified,
            'report_verification' => (bool) $study->reports()->whereNotNull('verified_at')->exists(),
            default => false,
        };
    }

    /**
     * An explicit map, same idiom as WorkflowEngineService's ris_status
     * lookups — new field keys need a resolver added here rather than
     * arbitrary reflection into whichever model happens to have that column.
     */
    protected function isFieldSatisfied(ImagingStudy $study, string $ruleKey): bool
    {
        return match ($ruleKey) {
            'contrast_volume_ml' => (bool) $study->contrastAdministrations()->whereNotNull('volume_ml')->exists(),
            'contrast_injection_time' => (bool) $study->contrastAdministrations()->whereNotNull('injection_time')->exists(),
            'dose_area_product_gy' => (bool) $study->radiationExposureLogs()->whereNotNull('dose_area_product_gy')->exists(),
            'exposure_time_ms' => (bool) $study->radiationExposureLogs()->whereNotNull('exposure_time_ms')->exists(),
            default => false,
        };
    }

    protected function buildMessage(ImagingWorkflowStepCompletionRule $rule): string
    {
        return (string) str($rule->rule_key)->replace('_', ' ')->title();
    }

    /**
     * True only if every failing rule allows override and the user holds
     * at least one of that specific rule's authorized permissions — a
     * partial override (some rules bypassable, others not) isn't allowed.
     */
    public function userCanOverride(array $errors, ?int $userId): bool
    {
        if (! $userId || empty($errors)) {
            return false;
        }

        $permissions = User::find($userId)?->permissions ?? [];

        foreach ($errors as $error) {
            if (! $error['allow_override']) {
                return false;
            }

            if (empty(array_intersect($error['authorized_override_permissions'], $permissions))) {
                return false;
            }
        }

        return true;
    }

    public function recordOverride(ImagingStudy $study, ProtocolWorkflowStep $step, int $userId, string $reason): ImagingWorkflowStepOverride
    {
        $execution = app(WorkflowEngineService::class)->resolveExecution($study);

        return ImagingWorkflowStepOverride::create([
            'imaging_study_workflow_execution_id' => $execution->id,
            'imaging_protocol_workflow_step_id' => $step->id,
            'user_id' => $userId,
            'reason' => $reason,
        ]);
    }
}
