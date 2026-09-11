<?php

namespace App\Services\Clinical;

use App\Models\ClinicalBed;
use App\Models\ClinicalProcess;
use App\Models\ClinicalProcessExecution;
use App\Models\ClinicalProcessStep;
use App\Models\ClinicalProcessStepExecution;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * SRD §4.3 Configurable Major Clinical Transitions Engine. Routine
 * bedside care is deliberately NOT governed by this — it applies strictly
 * to the five major state transitions (Admission, Transfer, Discharge,
 * Referral, Death Certification), each a configurable ordered step list
 * (clinical_processes / clinical_process_steps) with an immutable,
 * insert-only execution history (clinical_process_step_executions).
 */
class ClinicalProcessEngine
{
    public function startProcess(
        string $processCode,
        int $businessId,
        ?int $branchId,
        string $clientId,
        ?string $visitId,
        int $userId,
        ?string $initiationNote = null,
    ): ClinicalProcessExecution {
        $process = ClinicalProcess::resolve($businessId, $processCode);

        if (! $process) {
            throw new Exception("Unknown or inactive clinical process: {$processCode}");
        }

        $alreadyRunning = ClinicalProcessExecution::query()
            ->where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->where('process_id', $process->id)
            ->where('status', ClinicalProcessExecution::STATUS_IN_PROGRESS)
            ->exists();

        if ($alreadyRunning) {
            throw new Exception("A {$processCode} process is already in progress for this patient.");
        }

        $firstStep = $process->steps()->first();

        if (! $firstStep) {
            throw new Exception("Process {$processCode} has no configured steps.");
        }

        return ClinicalProcessExecution::create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'client_id' => $clientId,
            'visit_id' => $visitId,
            'process_id' => $process->id,
            'status' => ClinicalProcessExecution::STATUS_IN_PROGRESS,
            'current_step_id' => $firstStep->id,
            'initiation_note' => $initiationNote,
            'started_by_user_id' => $userId,
            'started_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $sideEffectParams e.g. ['bed_id' => 5] for an ALLOCATE_BED step
     */
    public function completeStep(ClinicalProcessExecution $execution, int $userId, array $sideEffectParams = [], ?string $notes = null): ClinicalProcessStepExecution
    {
        return DB::connection('clinical')->transaction(function () use ($execution, $userId, $sideEffectParams, $notes) {
            $step = $this->requireCurrentStep($execution);

            $this->applySideEffect($step, $execution, $sideEffectParams);

            $stepExecution = ClinicalProcessStepExecution::create([
                'execution_id' => $execution->id,
                'step_id' => $step->id,
                'status' => ClinicalProcessStepExecution::STATUS_COMPLETED,
                'completed_by_user_id' => $userId,
                'completed_at' => now(),
                'notes' => $notes,
            ]);

            $this->advance($execution, $step);

            return $stepExecution;
        });
    }

    public function skipStep(ClinicalProcessExecution $execution, int $userId, ?string $overrideReason = null): ClinicalProcessStepExecution
    {
        return DB::connection('clinical')->transaction(function () use ($execution, $userId, $overrideReason) {
            $step = $this->requireCurrentStep($execution);

            if ($step->is_mandatory && empty($overrideReason)) {
                throw new Exception("Step '{$step->step_name}' is mandatory — an override reason is required to skip it.");
            }

            $stepExecution = ClinicalProcessStepExecution::create([
                'execution_id' => $execution->id,
                'step_id' => $step->id,
                'status' => ClinicalProcessStepExecution::STATUS_SKIPPED,
                'completed_by_user_id' => $userId,
                'completed_at' => now(),
                'override_reason' => $overrideReason,
            ]);

            $this->advance($execution, $step);

            return $stepExecution;
        });
    }

    private function requireCurrentStep(ClinicalProcessExecution $execution): ClinicalProcessStep
    {
        if ($execution->status !== ClinicalProcessExecution::STATUS_IN_PROGRESS || ! $execution->current_step_id) {
            throw new Exception("This process is not in progress (status: {$execution->status}).");
        }

        return ClinicalProcessStep::findOrFail($execution->current_step_id);
    }

    private function advance(ClinicalProcessExecution $execution, ClinicalProcessStep $completedStep): void
    {
        $nextStep = ClinicalProcessStep::where('process_id', $completedStep->process_id)
            ->where('step_order', $completedStep->step_order + 1)
            ->first();

        if ($nextStep) {
            $execution->update(['current_step_id' => $nextStep->id]);

            return;
        }

        $execution->update([
            'status' => ClinicalProcessExecution::STATUS_COMPLETED,
            'current_step_id' => null,
            'completed_at' => now(),
        ]);
    }

    private function applySideEffect(ClinicalProcessStep $step, ClinicalProcessExecution $execution, array $params): void
    {
        match ($step->side_effect) {
            ClinicalProcessStep::EFFECT_ALLOCATE_BED => $this->allocateBed($execution, $params),
            ClinicalProcessStep::EFFECT_RELEASE_BED => $this->releaseBed($execution),
            default => null,
        };
    }

    private function allocateBed(ClinicalProcessExecution $execution, array $params): void
    {
        if (empty($params['bed_id'])) {
            throw new Exception('A bed must be selected to complete this step.');
        }

        $bed = ClinicalBed::whereHas('ward', function ($query) use ($execution) {
            $query->where('business_id', $execution->business_id);
        })->findOrFail($params['bed_id']);

        if ($bed->operational_state !== ClinicalBed::STATE_AVAILABLE) {
            throw new Exception("Bed {$bed->bed_code} is not available.");
        }

        $bed->update([
            'operational_state' => ClinicalBed::STATE_OCCUPIED,
            'current_client_id' => $execution->client_id,
            'current_visit_id' => $execution->visit_id,
        ]);
    }

    /**
     * Mirrors WardCensusBoard::releaseBed()'s auto-retirement rule (SRD
     * §5.3): an overflow bed is retired entirely rather than left
     * AVAILABLE.
     */
    private function releaseBed(ClinicalProcessExecution $execution): void
    {
        $bed = ClinicalBed::where('current_client_id', $execution->client_id)
            ->where('operational_state', ClinicalBed::STATE_OCCUPIED)
            ->first();

        if (! $bed) {
            return;
        }

        if ($bed->is_overflow) {
            $bed->delete();

            return;
        }

        $bed->update([
            'operational_state' => ClinicalBed::STATE_AVAILABLE,
            'current_client_id' => null,
            'current_visit_id' => null,
        ]);
    }
}
