<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\ConsumptionGateway;
use App\Models\ClinicalBed;
use App\Models\ClinicalConsumptionEvent;
use App\Services\Clinical\ConsumptionEventBroker;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ConsumptionRecord;
use Exception;

/**
 * CLINICAL_DRIVER=local: wraps ConsumptionEventBroker, the behaviour
 * RecordConsumption had before this gateway existed. This is also the
 * Inventory decrement — the broker resolves the store and reduces stock
 * itself, unlike the API driver where Clinical owns that.
 */
class LocalConsumptionGateway implements ConsumptionGateway
{
    public function __construct(private readonly ConsumptionEventBroker $broker)
    {
    }

    public function forPatient(ClinicalActor $actor, string $patientId, int $limit = 10): array
    {
        return ClinicalConsumptionEvent::where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (ClinicalConsumptionEvent $e) => ConsumptionRecord::fromModel($e))
            ->all();
    }

    public function record(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $itemCode,
        float $quantity,
        string $factToken,
        string $usageContext,
        ?string $justificationNote = null,
    ): ConsumptionRecord {
        $wardId = ClinicalBed::where('current_client_id', $patientId)->value('ward_id');

        $response = $this->broker->emitConsumptionFact([
            'business_id' => $actor->businessId,
            'branch_id' => $actor->branchId,
            'client_id' => $patientId,
            'visit_id' => $visitId,
            'ward_id' => $wardId,
            'item_code' => $itemCode,
            'quantity' => $quantity,
            'fact_token' => $factToken,
            'usage_context' => $usageContext,
            'notes' => $justificationNote,
        ], $actor->userId);

        if ($response['status'] !== 'RECONCILED') {
            throw new Exception(match ($response['status']) {
                'EXCEPTION' => "Could not deplete stock: {$response['exception_reason']}",
                'INVENTORY_MODULE_INACTIVE' => 'Inventory module is not active for this business.',
                default => (string) $response['status'],
            });
        }

        $event = ClinicalConsumptionEvent::where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->where('item_code', $itemCode)
            ->orderByDesc('occurred_at')
            ->first();

        return $event
            ? ConsumptionRecord::fromModel($event)
            : new ConsumptionRecord(
                id: 0,
                patientId: $patientId,
                visitId: $visitId,
                itemCode: $itemCode,
                quantity: $quantity,
                factToken: $factToken,
                justificationNote: $justificationNote,
                recordedAt: now()->toIso8601String(),
                recordedByUserId: $actor->userId,
                physicalStockReduced: $response['physical_stock_reduced'] ?? null,
                billingTriggered: $response['billing_triggered'] ?? null,
            );
    }

    /**
     * Nothing is ever pending here — ConsumptionEventBroker bills through
     * InventoryUsageEvent synchronously, in the same call that records the
     * chart fact, whether that call came from this chart panel or from
     * Record Usage directly. There is no separate queue to fall behind on
     * under this driver.
     */
    public function pendingFloorStockForBusiness(ClinicalActor $actor, int $limit = 50): array
    {
        return [];
    }
}
