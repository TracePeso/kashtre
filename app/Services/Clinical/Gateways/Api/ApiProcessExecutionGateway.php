<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\ProcessExecutionGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ProcessInstance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CLINICAL_DRIVER=api: `clinical/transitions/*`. Request/response shapes
 * confirmed 2026-08-19 against Clinical's own ProcessWorkflowController /
 * ClinicalProcessEngine source — the guide documents this group's intent
 * but not every field, and an earlier version of this class was built from
 * live probing alone and got the completion-note field name and the
 * existence of a real per-step skip wrong. See the interface doc.
 *
 * Reads fail soft (an empty history is safer than hiding the whole panel);
 * writes fail hard — a clinician advancing a major transition must know if
 * it did not go through.
 */
class ApiProcessExecutionGateway implements ProcessExecutionGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        try {
            $data = $this->client->get(
                "clinical/patients/{$patientId}/transitions",
                [],
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load this patient\'s process history.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        $instances = array_map(
            fn (array $row) => ProcessInstance::fromApi($row),
            array_values(array_filter($rows, 'is_array')),
        );

        // The list endpoint's rows never carry next_step — confirmed live
        // 2026-08-19, its shape is id/tenant_id/process_id/process_code/
        // patient_id/visit_id/status/context/started_at/completed_at/
        // abandonment_*/completions, nothing else. Only the single-instance
        // GET (what `present()` builds on Clinical's side) includes it, so
        // the one instance a panel actually needs to act on is re-fetched.
        // Without this, every active instance silently showed no current
        // step — the panel could show history but never let anyone advance
        // it.
        foreach ($instances as $index => $instance) {
            if ($instance->isActive()) {
                $instances[$index] = $this->show($actor, $instance) ?? $instance;
            }
        }

        return $instances;
    }

    private function show(ClinicalActor $actor, ProcessInstance $fallback): ?ProcessInstance
    {
        try {
            $data = $this->client->get(
                "clinical/transitions/{$fallback->id}",
                [],
                ['business_id' => $actor->businessId],
            );

            return ProcessInstance::fromApi($data);
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load this transition\'s current step.', $e->context());

            // The list row is still better than nothing — just without a
            // current step to act on.
            return null;
        }
    }

    public function start(
        ClinicalActor $actor,
        string $processCode,
        string $patientId,
        ?string $visitId,
        ?string $initiationNote = null,
    ): ProcessInstance {
        $data = $this->client->post(
            "clinical/transitions/{$processCode}/start",
            array_filter([
                'patient_id' => $patientId,
                'visit_id' => $visitId,
                'note' => $initiationNote,
            ], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                // Two ADMISSION-start taps for the same patient are one
                // clinical decision, not two — Clinical itself also refuses
                // a second concurrent instance, but the key stops the retry
                // from even being a race.
                'idempotency_key' => 'process-start-'.$patientId.'-'.$processCode,
            ],
        );

        return ProcessInstance::fromApi($data);
    }

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
        $data = $this->client->post(
            'clinical/transitions/execute',
            array_filter([
                'instance_id' => $instanceId,
                'step_code' => $stepCode,
                'skip' => $skip ?: null,
                'completion_note' => $completionNote,
                'bed_id' => $bedId,
                'override_reason_code' => $overrideReasonCode,
                'override_note' => $overrideNote,
                'lock_note' => $lockNote,
                'export_format' => $exportFormat,
            ], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                // Confirmed live 2026-08-19: an override retry is NOT safe
                // to send under the same key as the blocked first attempt —
                // adding override_reason_code/override_note changes the
                // body, and §7 refuses a reused key with a different body
                // (409 IDEMPOTENCY_KEY_REUSED) rather than silently
                // replaying. A raw network retry of *this exact* attempt
                // (blocked, or overridden) still shares a key with itself.
                'idempotency_key' => 'process-step-'.$instanceId.'-'.$stepCode
                    .($skip ? '-skip' : '')
                    .($overrideReasonCode ? '-override' : ''),
            ],
        );

        // execute's response is the completion record, not the instance —
        // it carries next_step but not id/patient_id/status, so those are
        // filled in from what the caller already knows rather than left null.
        return new ProcessInstance(
            id: $data['instance_id'] ?? $instanceId,
            processCode: (string) ($data['process_code'] ?? ''),
            patientId: '',
            visitId: null,
            status: (string) ($data['transition_status'] ?? 'IN_PROGRESS'),
            startedAt: null,
            completedAt: null,
            nextStep: $data['next_step'] ?? null,
            stepEffects: is_array($data['effects'] ?? null) ? $data['effects'] : null,
        );
    }

    public function abandon(
        ClinicalActor $actor,
        int|string $instanceId,
        string $reasonCode,
        ?string $note = null,
    ): ProcessInstance {
        $data = $this->client->post(
            "clinical/transitions/{$instanceId}/abandon",
            array_filter([
                'reason_code' => $reasonCode,
                'note' => $note,
            ], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => (string) Str::uuid(),
            ],
        );

        return ProcessInstance::fromApi($data);
    }

    public function decisionToAdmit(
        ClinicalActor $actor,
        string $patientId,
        string $visitId,
        string $targetWardCode,
        ?string $targetSpecialty = null,
        ?string $admissionNote = null,
        ?int $bedId = null,
    ): ProcessInstance {
        $data = $this->client->post(
            'clinical/transitions/decision-to-admit',
            array_filter([
                'patient_id' => $patientId,
                'visit_id' => $visitId,
                'target_ward_code' => $targetWardCode,
                'target_specialty' => $targetSpecialty,
                'admission_note' => $admissionNote,
                'bed_id' => $bedId,
            ], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => 'decision-to-admit-'.$patientId.'-'.$visitId,
            ],
        );

        return ProcessInstance::fromApi($data);
    }
}
