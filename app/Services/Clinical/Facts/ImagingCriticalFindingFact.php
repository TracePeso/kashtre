<?php

namespace App\Services\Clinical\Facts;

/**
 * RIS -> Clinical, per the Clinical_to_RIS_PACS ICD's "critical-finding"
 * event. Dispatched from ImagingReportObserver::alertCriticalFinding() —
 * that method previously only sent an in-app notification because, per
 * its own comment, "no Clinical Module to call back to yet." This fact is
 * that callback; the notification stays as-is alongside it.
 */
class ImagingCriticalFindingFact extends Fact
{
    public function __construct(
        public readonly int $businessId,
        public readonly ?int $branchId,
        public readonly string $clientId,
        public readonly ?string $visitId,
        public readonly int $imagingOrderId,
        public readonly string $protocolCode,
        public readonly string $findingCode,
        public readonly ?int $reportingRadiologistId,
    ) {
    }

    public function targetModule(): string
    {
        return 'clinical';
    }

    public function factType(): string
    {
        return 'critical-finding';
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
            'finding_code' => $this->findingCode,
            'reporting_radiologist_id' => $this->reportingRadiologistId,
        ];
    }
}
