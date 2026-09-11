<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\ProcessExecutionGateway;
use App\Models\ClinicalBed;
use App\Models\ClinicalProcessExecution;
use App\Services\Clinical\ClinicalProcessEngine;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ProcessInstance;
use Exception;

/**
 * CLINICAL_DRIVER=local: wraps ClinicalProcessEngine, the behaviour
 * ClinicalProcessPanel had before this gateway existed.
 *
 * Local's engine already had a real skipStep() all along — the interface
 * just never exposed it as its own action, routing every "doesn't apply"
 * case through abandon() instead. Now executeStep($skip: true) reaches it
 * directly, same as the API driver.
 *
 * Local has no PROCESS_OVERRIDE reason-code dictionary or audited
 * override_reason_code/override_note pair — skipStep()'s single free-text
 * reason is the closest local equivalent, so both are folded into it.
 * Local also has no completion_rule concept, so a mandatory step here is
 * only ever blocked by "is mandatory", never by an unmet chart condition.
 */
class LocalProcessExecutionGateway implements ProcessExecutionGateway
{
    public function __construct(private readonly ClinicalProcessEngine $engine)
    {
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        return ClinicalProcessExecution::where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->with('process', 'currentStep')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get()
            ->map(fn (ClinicalProcessExecution $e) => ProcessInstance::fromModel($e))
            ->all();
    }

    public function start(
        ClinicalActor $actor,
        string $processCode,
        string $patientId,
        ?string $visitId,
        ?string $initiationNote = null,
    ): ProcessInstance {
        $execution = $this->engine->startProcess(
            $processCode,
            $actor->businessId,
            $actor->branchId,
            $patientId,
            $visitId,
            $actor->userId,
            $initiationNote,
        );

        return ProcessInstance::fromModel($execution->load('process', 'currentStep'));
    }

    /**
     * Local's engine only ever dispatches ALLOCATE_BED/RELEASE_BED side
     * effects (see ClinicalProcessEngine::applySideEffect) — it has no
     * chart-lock or FHIR-export concept, so $lockNote/$exportFormat are
     * accepted only to satisfy the shared interface and are otherwise
     * ignored under this driver.
     */
    public function executeStep(
        ClinicalActor $actor,
        int|string $instanceId,
        string $stepCode,
        bool $skip = false,
        ?string $completionNote = null,
        ?int $bedId = null,
        ?string $overrideReasonCode = null,
        ?string $overrideNote = null,
        ?string $lockNote = null,
        ?string $exportFormat = null,
    ): ProcessInstance {
        $execution = $this->findOwned($actor, $instanceId);

        if ($skip) {
            $reason = $overrideReasonCode
                ? ($overrideNote ? "{$overrideReasonCode}: {$overrideNote}" : $overrideReasonCode)
                : null;

            $this->engine->skipStep($execution, $actor->userId, $reason);
        } else {
            $sideEffectParams = $bedId ? ['bed_id' => $bedId] : [];

            $this->engine->completeStep($execution, $actor->userId, $sideEffectParams, $completionNote);
        }

        return ProcessInstance::fromModel($execution->refresh()->load('process', 'currentStep'));
    }

    public function abandon(
        ClinicalActor $actor,
        int|string $instanceId,
        string $reasonCode,
        ?string $note = null,
    ): ProcessInstance {
        $execution = $this->findOwned($actor, $instanceId);

        if ($execution->status !== ClinicalProcessExecution::STATUS_IN_PROGRESS) {
            throw new Exception("This process is not in progress (status: {$execution->status}).");
        }

        $this->engine->skipStep($execution, $actor->userId, $note ? "{$reasonCode}: {$note}" : $reasonCode);
        $execution->refresh();

        // skipStep() only advances past one step; abandoning the instance
        // outright is closing it regardless of how many steps remain.
        if ($execution->status === ClinicalProcessExecution::STATUS_IN_PROGRESS) {
            $execution->update(['status' => ClinicalProcessExecution::STATUS_CANCELLED]);
        }

        return ProcessInstance::fromModel($execution->refresh()->load('process', 'currentStep'));
    }

    /**
     * Best-effort local equivalent — there is no ward-outbox handshake to
     * emit ADMISSION_REQUESTED to under this driver (Main *is* this driver),
     * so this only starts the process and, if a bed was named, reserves it
     * directly. requiresBedAllocation is always the inverse of whether a bed
     * was actually reserved, same meaning as the API driver's field.
     */
    public function decisionToAdmit(
        ClinicalActor $actor,
        string $patientId,
        string $visitId,
        string $targetWardCode,
        ?string $targetSpecialty = null,
        ?string $admissionNote = null,
        ?int $bedId = null,
    ): ProcessInstance {
        $execution = $this->engine->startProcess(
            'ADMISSION',
            $actor->businessId,
            $actor->branchId,
            $patientId,
            $visitId,
            $actor->userId,
            $admissionNote,
        );

        $bedCode = null;

        if ($bedId) {
            $bed = ClinicalBed::whereHas('ward', fn ($q) => $q->where('business_id', $actor->businessId))
                ->findOrFail($bedId);

            $bed->update([
                'operational_state' => ClinicalBed::STATE_RESERVED,
                'current_client_id' => $patientId,
                'current_visit_id' => $visitId,
            ]);

            $bedCode = $bed->bed_code;
        }

        $instance = ProcessInstance::fromModel($execution->load('process', 'currentStep'));

        return new ProcessInstance(
            id: $instance->id,
            processCode: $instance->processCode,
            patientId: $instance->patientId,
            visitId: $instance->visitId,
            status: $instance->status,
            startedAt: $instance->startedAt,
            completedAt: $instance->completedAt,
            nextStep: $instance->nextStep,
            bedReserved: $bedCode,
            requiresBedAllocation: $bedCode === null,
        );
    }

    private function findOwned(ClinicalActor $actor, int|string $instanceId): ClinicalProcessExecution
    {
        return ClinicalProcessExecution::where('business_id', $actor->businessId)
            ->findOrFail($instanceId);
    }
}
