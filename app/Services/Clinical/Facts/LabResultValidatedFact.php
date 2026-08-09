<?php

namespace App\Services\Clinical\Facts;

/**
 * LIMS -> Clinical, per the Clinical-to-LIMS ICD's "result-validated"
 * event.
 */
class LabResultValidatedFact extends Fact
{
    public function __construct(
        public readonly int $businessId,
        public readonly ?int $branchId,
        public readonly string $clientId,
        public readonly ?string $visitId,
        public readonly string $labOrderUuid,
        public readonly string $testCode,
        public readonly string $cdeCode,
        public readonly float $valueNumeric,
        public readonly ?string $unitLabel,
        public readonly bool $isAbnormal,
        public readonly int $validatedByUserId,
    ) {
    }

    public function targetModule(): string
    {
        return 'clinical';
    }

    public function factType(): string
    {
        return 'lab-result-validated';
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
            'value_numeric' => $this->valueNumeric,
            'unit_label' => $this->unitLabel,
            'is_abnormal' => $this->isAbnormal,
            'validated_by_user_id' => $this->validatedByUserId,
        ];
    }
}
