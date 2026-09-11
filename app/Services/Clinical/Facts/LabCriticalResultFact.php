<?php

namespace App\Services\Clinical\Facts;

/**
 * LIMS -> Clinical, per the Clinical-to-LIMS ICD's "critical-result"
 * event (a panic-value alert, separate from and faster than the full
 * result-validated payload).
 */
class LabCriticalResultFact extends Fact
{
    public function __construct(
        public readonly int $businessId,
        public readonly ?int $branchId,
        public readonly string $clientId,
        public readonly ?string $visitId,
        public readonly string $labOrderUuid,
        public readonly string $testCode,
        public readonly string $cdeCode,
        public readonly float $observedValue,
        public readonly string $criticalType,
        public readonly ?int $authorizingPathologistId,
    ) {
    }

    public function targetModule(): string
    {
        return 'clinical';
    }

    public function factType(): string
    {
        return 'lab-critical-result';
    }

    public function toPayload(): array
    {
        return [
            'business_id' => $this->businessId,
            'branch_id' => $this->branchId,
            'client_id' => $this->clientId,
            'visit_id' => $this->visitId,
            'lab_order_uuid' => $this->labOrderUuid,
            'test_code' => $this->testCode,
            'cde_code' => $this->cdeCode,
            'observed_value' => $this->observedValue,
            'critical_type' => $this->criticalType,
            'authorizing_pathologist_id' => $this->authorizingPathologistId,
        ];
    }
}
