<?php

namespace App\Services\Clinical\Facts;

use Illuminate\Support\Str;

/**
 * Clinical -> LIMS, per "KashTre Clinical-to-LIMS Interface Control
 * Document v1.0" section on POST /api/v1/lab/orders. `tenant_id` in the
 * ICD maps to this app's real business_id/branch_id convention. Scoped
 * to a single test_code per order (the ICD's test_requests[] array is a
 * later extension — this app's ordering UI issues one test per order,
 * matching how Chunk 3's imaging ordering issues one protocol per order).
 */
class LabOrderPlacedFact extends Fact
{
    public readonly string $labOrderUuid;

    public function __construct(
        public readonly int $businessId,
        public readonly ?int $branchId,
        public readonly string $globalClientId,
        public readonly ?string $visitId,
        public readonly int $orderingClinicianId,
        public readonly string $testCode,
        public readonly ?string $clinicalIndication = null,
        public readonly ?string $urgency = null,
        ?string $labOrderUuid = null,
    ) {
        $this->labOrderUuid = $labOrderUuid ?? (string) Str::uuid();
    }

    public function targetModule(): string
    {
        return 'lims';
    }

    public function factType(): string
    {
        return 'lab-order-placed';
    }

    public function toPayload(): array
    {
        return [
            'business_id' => $this->businessId,
            'branch_id' => $this->branchId,
            'global_client_id' => $this->globalClientId,
            'visit_id' => $this->visitId,
            'lab_order_uuid' => $this->labOrderUuid,
            'ordering_clinician_id' => $this->orderingClinicianId,
            'test_code' => $this->testCode,
            'clinical_indication' => $this->clinicalIndication,
            'urgency' => $this->urgency,
        ];
    }
}
