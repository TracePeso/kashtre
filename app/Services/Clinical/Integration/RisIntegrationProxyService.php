<?php

namespace App\Services\Clinical\Integration;

use App\Models\CdeObservation;
use App\Models\ClinicalWorkOrder;

/**
 * Local receivers for the 'clinical'.'report-validated' and
 * 'clinical'.'critical-finding' facts (Clinical-to-RIS/PACS ICD). Finds
 * the originating work order by (external_module, external_reference) —
 * the imaging_order_id handed back when the order was first placed.
 */
class RisIntegrationProxyService
{
    /**
     * @param array<string, mixed> $payload ImagingReportValidatedFact::toPayload()
     * @return array{status: string}
     */
    public function handleReportValidated(array $payload): array
    {
        $workOrder = $this->findWorkOrder($payload['imaging_order_id']);

        CdeObservation::create([
            'business_id' => $payload['business_id'],
            'branch_id' => $payload['branch_id'],
            'client_id' => $payload['client_id'],
            'visit_id' => $payload['visit_id'],
            'cde_code' => 'RAD_IMPRESSION_'.$payload['protocol_code'],
            'captured_value_text' => json_encode($payload['structured_data_payload']),
            'capture_method' => CdeObservation::METHOD_IMPORTED_DATA,
            'validation_status' => 'VALIDATED',
            'validated_by_user_id' => $payload['verified_by_user_id'],
            'captured_at' => now(),
        ]);

        if ($workOrder) {
            $workOrder->update([
                'status' => ClinicalWorkOrder::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        return ['status' => 'RECORDED'];
    }

    /**
     * @param array<string, mixed> $payload ImagingCriticalFindingFact::toPayload()
     * @return array{status: string}
     */
    public function handleCriticalFinding(array $payload): array
    {
        CdeObservation::create([
            'business_id' => $payload['business_id'],
            'branch_id' => $payload['branch_id'],
            'client_id' => $payload['client_id'],
            'visit_id' => $payload['visit_id'],
            'cde_code' => 'RAD_CRITICAL_FINDING_'.$payload['protocol_code'],
            'captured_value_text' => $payload['finding_code'],
            'capture_method' => CdeObservation::METHOD_IMPORTED_DATA,
            'validation_status' => 'VALIDATED',
            'validated_by_user_id' => $payload['reporting_radiologist_id'],
            'captured_at' => now(),
        ]);

        // Full escalation-tier routing (siren/modal/target-role alerting
        // per clinical_escalation_rules) is Chunk 9's CDSS/escalation work
        // — this records the fact so it's on the chart now rather than
        // waiting, without building the alert UI ahead of that chunk.
        return ['status' => 'RECORDED'];
    }

    private function findWorkOrder(int $imagingOrderId): ?ClinicalWorkOrder
    {
        return ClinicalWorkOrder::query()
            ->where('external_module', 'imaging')
            ->where('external_reference', (string) $imagingOrderId)
            ->first();
    }
}
