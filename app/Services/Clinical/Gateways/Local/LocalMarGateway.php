<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\MarGateway;
use App\Models\ClinicalBed;
use App\Models\ClinicalConsumptionEvent;
use App\Models\ClinicalMarDose;
use App\Services\Clinical\ConsumptionEventBroker;
use App\Services\Clinical\MarSchedulerService;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\MarDoseRecord;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: the MAR against local tables, via MarSchedulerService.
 *
 * Under this driver *we* emit the consumption fact to Inventory, through
 * ConsumptionEventBroker. Under the API driver Clinical emits it instead
 * (§12) and this module must not — which is the whole reason administer()
 * returns the outcome rather than the caller reaching for the broker itself.
 */
class LocalMarGateway implements MarGateway
{
    public function __construct(
        private readonly MarSchedulerService $marScheduler,
        private readonly ConsumptionEventBroker $consumptionBroker,
    ) {
    }

    public function dosesForPatient(ClinicalActor $actor, string $patientId, ?string $state = 'DUE'): array
    {
        return ClinicalMarDose::query()
            ->whereHas('medicationOrder', function ($query) use ($actor, $patientId) {
                $query->where('business_id', $actor->businessId)
                    ->where('client_id', $patientId);
            })
            ->when($state, fn ($query) => $query->where('status', $state))
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->with('medicationOrder')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (ClinicalMarDose $dose) => MarDoseRecord::fromModel($dose))
            ->all();
    }

    public function administer(ClinicalActor $actor, int|string $doseId, array $verification = []): array
    {
        $dose = $this->findDose($actor, $doseId);

        // The ward drives which sub-store the stock decrement is posted
        // against. Derived from the patient's current bed because the local
        // schema has no sub_store_id on the dose itself; the API driver sends
        // sub_store_id explicitly instead.
        $wardId = ClinicalBed::where('current_client_id', $dose->medicationOrder->client_id)
            ->value('ward_id');

        return $this->marScheduler->administerDose($dose, $actor->userId, $wardId);
    }

    public function hold(ClinicalActor $actor, int|string $doseId, string $reasonCode, ?string $note = null): void
    {
        $this->marScheduler->holdDose(
            $this->findDose($actor, $doseId),
            $actor->userId,
            $reasonCode,
            $note,
        );
    }

    public function refuse(ClinicalActor $actor, int|string $doseId, string $reasonCode, ?string $note = null): void
    {
        // The local schema has no distinct REFUSED state — a patient refusal
        // and a clinical hold both land as HELD with a reason code, which is
        // what the existing MAR views read. The API models them separately;
        // the reason code is what distinguishes them either way.
        $this->hold($actor, $doseId, $reasonCode, $note);
    }

    public function waste(
        ClinicalActor $actor,
        int|string $orderItemId,
        float $wastedQuantity,
        string $reasonCode,
        ?int $witnessedByUserId = null,
    ): void {
        $dose = $this->findDose($actor, $orderItemId);
        $order = $dose->medicationOrder;

        if (! $order->drug_code) {
            // Externally-fulfilled drugs never entered our stock, so there is
            // nothing to decrement and no fact to emit.
            return;
        }

        $wardId = ClinicalBed::where('current_client_id', $order->client_id)->value('ward_id');

        $this->consumptionBroker->emitConsumptionFact([
            'business_id' => $order->business_id,
            'branch_id' => $order->branch_id,
            'client_id' => $order->client_id,
            'visit_id' => $order->visit_id,
            'ward_id' => $wardId,
            'item_code' => $order->drug_code,
            'quantity' => $wastedQuantity,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_MEDICATION_WASTED,
            'usage_context' => 'WASTAGE',
            // The broker's payload has no dedicated reason/witness columns —
            // the local consumption schema predates wastage witnessing — so
            // both are recorded in the audited note rather than dropped.
            'notes' => trim("Wastage {$reasonCode}".($witnessedByUserId ? ", witnessed by user {$witnessedByUserId}" : '')),
        ], $actor->userId);
    }

    private function findDose(ClinicalActor $actor, int|string $doseId): ClinicalMarDose
    {
        $dose = ClinicalMarDose::query()
            ->whereHas('medicationOrder', fn ($query) => $query->where('business_id', $actor->businessId))
            ->with('medicationOrder')
            ->find($doseId);

        if (! $dose) {
            throw new RuntimeException("MAR dose {$doseId} was not found for this facility.");
        }

        return $dose;
    }
}
