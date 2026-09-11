<?php

namespace App\Services\Clinical\Integration;

use App\Models\CdeObservation;
use App\Models\CdeRegistry;
use App\Models\ClinicalConsumptionEvent;
use App\Models\ClinicalUomMaster;
use App\Models\ClinicalWorkOrder;
use App\Services\Clinical\CdeExecutionEngine;
use App\Services\Clinical\ConsumptionEventBroker;
use Exception;

/**
 * Local receivers for the 'clinical'.'lab-result-validated',
 * 'clinical'.'lab-critical-result', and 'clinical'.'lab-reagent-consumption'
 * facts (Clinical-to-LIMS ICD). Mirrors RisIntegrationProxyService's role
 * for imaging (Chunk 3).
 */
class LimsIntegrationProxyService
{
    /**
     * @param array<string, mixed> $payload LabResultValidatedFact::toPayload()
     */
    public function handleResultValidated(array $payload): array
    {
        $this->recordObservation($payload);

        $workOrder = $this->findWorkOrder($payload['lab_order_uuid']);

        if ($workOrder) {
            $workOrder->update([
                'status' => ClinicalWorkOrder::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        return ['status' => 'RECORDED'];
    }

    /**
     * @param array<string, mixed> $payload LabCriticalResultFact::toPayload()
     */
    public function handleCriticalResult(array $payload): array
    {
        CdeObservation::create([
            'business_id' => $payload['business_id'],
            'branch_id' => $payload['branch_id'] ?? null,
            'client_id' => $payload['client_id'],
            'visit_id' => $payload['visit_id'] ?? null,
            'cde_code' => 'LAB_CRITICAL_'.$payload['test_code'],
            'captured_value_text' => "{$payload['critical_type']} (observed {$payload['observed_value']})",
            'capture_method' => CdeObservation::METHOD_IMPORTED_DATA,
            'validation_status' => 'VALIDATED',
            'validated_by_user_id' => $payload['authorizing_pathologist_id'],
            'captured_at' => now(),
        ]);

        return ['status' => 'RECORDED'];
    }

    /**
     * @param array<string, mixed> $payload LabReagentConsumptionFact::toPayload()
     */
    public function handleReagentConsumption(array $payload): array
    {
        return app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => $payload['business_id'],
            'branch_id' => $payload['branch_id'] ?? null,
            'client_id' => $payload['client_id'],
            'visit_id' => $payload['visit_id'] ?? null,
            'ward_id' => null, // resolved via the scientist's own default store, not a patient ward
            'item_code' => $payload['item_code'],
            'quantity' => $payload['quantity'],
            'fact_token' => ClinicalConsumptionEvent::TOKEN_LAB_CONSUMPTION_FACT,
            'usage_context' => 'PATIENT',
        ], $payload['scientist_user_id']);
    }

    /**
     * Tries CdeExecutionEngine first (proper unit conversion + heuristic
     * shield) when the cde_code/unit are both known to this business'
     * registry; falls back to a direct, unconverted insert for LIMS test
     * codes with no matching CdeRegistry entry (e.g. the generic
     * LAB_RESULT_{test_code} fallback) or a value the shield rejects —
     * imported lab data is already validated upstream, so it's recorded
     * either way rather than dropped.
     */
    private function recordObservation(array $payload): void
    {
        $cde = CdeRegistry::resolve($payload['business_id'], $payload['cde_code']);
        $inputUomId = ! empty($payload['unit_label'])
            ? ClinicalUomMaster::where('unit_label', $payload['unit_label'])->value('id')
            : null;

        if ($cde && $cde->base_uom_id && $inputUomId) {
            try {
                app(CdeExecutionEngine::class)->captureObservation([
                    'client_id' => $payload['client_id'],
                    'visit_id' => $payload['visit_id'] ?? null,
                    'cde_code' => $payload['cde_code'],
                    'value_numeric' => $payload['value_numeric'],
                    'input_uom_id' => $inputUomId,
                    'capture_method' => CdeObservation::METHOD_IMPORTED_DATA,
                ], $payload['validated_by_user_id'], $payload['business_id'], $payload['branch_id'] ?? null);

                return;
            } catch (Exception) {
                // fall through to the direct insert below
            }
        }

        CdeObservation::create([
            'business_id' => $payload['business_id'],
            'branch_id' => $payload['branch_id'] ?? null,
            'client_id' => $payload['client_id'],
            'visit_id' => $payload['visit_id'] ?? null,
            'cde_code' => $payload['cde_code'],
            'captured_value_numeric' => $payload['value_numeric'],
            'base_value_numeric' => $payload['value_numeric'],
            'capture_method' => CdeObservation::METHOD_IMPORTED_DATA,
            'validation_status' => 'VALIDATED',
            'validated_by_user_id' => $payload['validated_by_user_id'],
            'captured_at' => now(),
        ]);
    }

    private function findWorkOrder(string $labOrderUuid): ?ClinicalWorkOrder
    {
        return ClinicalWorkOrder::query()
            ->where('external_module', 'lims')
            ->where('external_reference', $labOrderUuid)
            ->first();
    }
}
