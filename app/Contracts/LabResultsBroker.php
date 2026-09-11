<?php

namespace App\Contracts;

/**
 * Clinical-to-LIMS ICD's Clinical-facing operations. Bound to
 * StubLimsClient in ClinicalLimsIntegrationServiceProvider until a real
 * LIMS exists — swap the binding for an HTTP-backed implementation
 * (same idiom as DicomWorklistBroker/OrthancDicomWorklistBroker) at that
 * point; no caller of this contract needs to change.
 */
interface LabResultsBroker
{
    /**
     * @param array{business_id: int, branch_id: ?int, global_client_id: string, visit_id: ?string, lab_order_uuid: string, ordering_clinician_id: int, test_code: string, clinical_indication: ?string, urgency: ?string} $payload
     * @return array{status: string, lab_order_uuid: string, lims_status: string, specimen_id: ?string}
     */
    public function placeOrder(array $payload): array;

    /**
     * @param array{cancellation_reason_code: string, justification_note: ?string, cancelled_by_user_id: int} $payload
     * @return array{status: string}
     */
    public function cancelOrder(string $labOrderUuid, array $payload): array;
}
