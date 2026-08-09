<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\MedicationOrdersGateway;
use App\Models\ClinicalMedicationOrder;
use App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException;
use App\Services\Clinical\Api\Exceptions\ClinicalSafetyBlockException;
use App\Services\Clinical\ClinicalTranslatorEngine;
use App\Services\Clinical\DeterministicCdssShield;
use App\Services\Clinical\MarSchedulerService;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\MedicationOrderRecord;
use App\Support\Clinical\SafetyEvaluation;

/**
 * CLINICAL_DRIVER=local: prescribing against the local tables, via the
 * translator engine (catalogue resolution), the deterministic CDSS shield and
 * the MAR scheduler.
 *
 * This is the logic that used to sit inline in MedicationOrdersPanel::prescribe().
 * Moving it here is most of the point of the exercise: the Livewire component
 * now expresses *what* to do and this expresses *how*, so the API
 * implementation can substitute a different how.
 *
 * Note the refusals are raised as the same exception types the API driver
 * throws, even though nothing here speaks HTTP. Two drivers that fail
 * differently are not interchangeable, and the caller would end up with a
 * branch per driver — exactly what the seam is meant to prevent.
 */
class LocalMedicationOrdersGateway implements MedicationOrdersGateway
{
    public function __construct(
        private readonly ClinicalTranslatorEngine $translator,
        private readonly DeterministicCdssShield $shield,
        private readonly MarSchedulerService $marScheduler,
    ) {
    }

    public function activeOrders(ClinicalActor $actor, string $patientId): array
    {
        return ClinicalMedicationOrder::query()
            ->where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->where('status', ClinicalMedicationOrder::STATUS_ACTIVE)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ClinicalMedicationOrder $order) => MedicationOrderRecord::fromModel($order))
            ->all();
    }

    public function evaluateSafety(ClinicalActor $actor, string $patientId, array $draft): SafetyEvaluation
    {
        $item = $this->translator->resolveDrug(
            $actor->businessId,
            $draft['requested_term'],
            $draft['strength_descriptor'] ?? null,
        );

        return SafetyEvaluation::fromArray($this->shield->evaluateMedicationSafety([
            'drug_code' => $item?->code,
            'dose_mg' => (float) $draft['dose_amount'],
            'max_mg_per_kg' => $draft['max_mg_per_kg'] ?? null,
            'is_nephrotoxic' => $draft['is_nephrotoxic'] ?? false,
        ], $patientId, $actor->businessId));
    }

    public function prescribe(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        array $draft,
        ?string $overrideReasonCode = null,
        ?string $overrideNote = null,
        bool $confirmExternalFulfilment = false,
        // Accepted and ignored: idempotency is an HTTP concern. A local
        // prescribe() runs in the caller's own request, so there is no
        // retry-after-timeout window to protect against.
        ?string $idempotencyKey = null,
    ): MedicationOrderRecord {
        $item = $this->translator->resolveDrug(
            $actor->businessId,
            $draft['requested_term'],
            $draft['strength_descriptor'] ?? null,
        );

        $safety = SafetyEvaluation::fromArray($this->shield->evaluateMedicationSafety([
            'drug_code' => $item?->code,
            'dose_mg' => (float) $draft['dose_amount'],
            'max_mg_per_kg' => $draft['max_mg_per_kg'] ?? null,
            'is_nephrotoxic' => $draft['is_nephrotoxic'] ?? false,
        ], $patientId, $actor->businessId));

        if ($safety->requiresOverride() && ! $overrideReasonCode) {
            throw new ClinicalSafetyBlockException(
                'This prescription is blocked by a clinical safety rule.',
                422,
                'CDSS_HARD_BLOCK',
                ['hard_blocks' => $safety->hard_blocks, 'warnings' => $safety->warnings],
            );
        }

        // Nothing in the catalogue matched. The API refuses this and asks for
        // an explicit confirmation rather than silently turning an internal
        // prescription into an external referral — a clinician who mistyped a
        // drug name deserves to be told, not handed a referral PDF. Mirrored
        // here so both drivers agree.
        if (! $item && ! $confirmExternalFulfilment) {
            throw new ClinicalRuleRefusedException(
                "No catalogue item matches \"{$draft['requested_term']}\". Confirm external fulfilment to generate a referral instead.",
                422,
                'EXTERNAL_FULFILMENT_REQUIRED',
                ['unmatched' => [$draft['requested_term']]],
            );
        }

        $attributes = [
            'business_id' => $actor->businessId,
            'branch_id' => $actor->branchId,
            'client_id' => $patientId,
            'visit_id' => $visitId,
            'ordering_user_id' => $actor->userId,
            'dose_amount' => $draft['dose_amount'],
            'route_code' => $draft['route_code'],
            'frequency_code' => $draft['frequency_code'],
            'start_at' => now(),
            'cdss_override_reason' => $safety->requiresOverride() ? $overrideReasonCode : null,
        ];

        if ($item) {
            $order = ClinicalMedicationOrder::create($attributes + [
                'drug_code' => $item->code,
                'drug_display_name' => $item->name,
                'is_external' => false,
            ]);
        } else {
            $referralPath = $this->translator->generateExternalReferral($actor->businessId, [
                'client_id' => $patientId,
                'drug_search' => $draft['requested_term'],
                'strength' => $draft['strength_descriptor'] ?? null,
                'dose_amount' => $draft['dose_amount'],
                'route_code' => $draft['route_code'],
                'frequency_code' => $draft['frequency_code'],
                'clinical_indication' => $draft['clinical_indication'] ?? null,
                'ordering_clinician_name' => $actor->name,
            ]);

            $order = ClinicalMedicationOrder::create($attributes + [
                'drug_code' => null,
                'drug_display_name' => $draft['requested_term'],
                'is_external' => true,
                'external_referral_path' => $referralPath,
            ]);
        }

        $this->marScheduler->generateDosesForOrder($order);

        return MedicationOrderRecord::fromModel($order);
    }

    public function cancel(ClinicalActor $actor, int|string $orderId, string $cancellationReasonCode): void
    {
        ClinicalMedicationOrder::query()
            ->where('business_id', $actor->businessId)
            ->whereKey($orderId)
            ->update(['status' => ClinicalMedicationOrder::STATUS_DISCONTINUED]);
    }
}
