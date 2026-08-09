<?php

namespace App\Services\Clinical\Facts;

use Illuminate\Support\Str;

/**
 * Clinical -> RIS, per "KashTre Clinical_to_RIS_PACS Interface Control
 * Document v1.0" section on POST /api/v1/imaging/orders. `tenant_id` in
 * the ICD maps to this app's real business_id/branch_id convention.
 */
class DiagnosticOrderPlacedFact extends Fact
{
    public readonly string $imagingStudyUuid;

    public function __construct(
        public readonly int $businessId,
        public readonly ?int $branchId,
        public readonly string $globalClientId,
        public readonly ?string $visitId,
        public readonly string $protocolCode,
        public readonly int $orderingClinicianId,
        public readonly ?int $spaceId = null,
        public readonly ?string $modality = null,
        public readonly ?bool $creatinineCleared = null,
        public readonly ?string $pregnancyStatus = null,
        public readonly ?string $clinicalIndication = null,
        ?string $imagingStudyUuid = null,
    ) {
        $this->imagingStudyUuid = $imagingStudyUuid ?? (string) Str::uuid();
    }

    public function targetModule(): string
    {
        return 'imaging';
    }

    public function factType(): string
    {
        return 'diagnostic-order-placed';
    }

    public function toPayload(): array
    {
        return [
            'business_id' => $this->businessId,
            'branch_id' => $this->branchId,
            'global_client_id' => $this->globalClientId,
            'visit_id' => $this->visitId,
            'imaging_study_uuid' => $this->imagingStudyUuid,
            'protocol_code' => $this->protocolCode,
            'ordering_clinician_id' => $this->orderingClinicianId,
            'space_id' => $this->spaceId,
            'modality' => $this->modality,
            'creatinine_cleared' => $this->creatinineCleared,
            'pregnancy_status' => $this->pregnancyStatus,
            'clinical_indication' => $this->clinicalIndication,
        ];
    }
}
