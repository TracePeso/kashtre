<?php

namespace App\Services\Clinical\Facts;

/**
 * RIS -> Clinical, per the Clinical_to_RIS_PACS ICD's "report-validated"
 * event. Dispatched from ImagingReportObserver::handleVerified() — the
 * point the doc's `RADIOLOGY_REPORT_VALIDATED` webhook would fire, here a
 * same-process call instead.
 */
class ImagingReportValidatedFact extends Fact
{
    public function __construct(
        public readonly int $businessId,
        public readonly ?int $branchId,
        public readonly string $clientId,
        public readonly ?string $visitId,
        public readonly int $imagingOrderId,
        public readonly string $protocolCode,
        public readonly array $structuredDataPayload,
        public readonly int $verifiedByUserId,
    ) {
    }

    public function targetModule(): string
    {
        return 'clinical';
    }

    public function factType(): string
    {
        return 'report-validated';
    }

    public function toPayload(): array
    {
        return [
            'business_id' => $this->businessId,
            'branch_id' => $this->branchId,
            'client_id' => $this->clientId,
            'visit_id' => $this->visitId,
            'imaging_order_id' => $this->imagingOrderId,
            'protocol_code' => $this->protocolCode,
            'structured_data_payload' => $this->structuredDataPayload,
            'verified_by_user_id' => $this->verifiedByUserId,
        ];
    }
}
