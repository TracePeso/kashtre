<?php

namespace App\Services\Clinical\Integration;

use App\Contracts\LabResultsBroker;
use App\Contracts\ModuleDispatcher;
use App\Services\Clinical\Facts\LabCriticalResultFact;
use App\Services\Clinical\Facts\LabReagentConsumptionFact;
use App\Services\Clinical\Facts\LabResultValidatedFact;
use Illuminate\Support\Str;

/**
 * Stands in for LIMS, which doesn't exist yet — mirrors the
 * DicomWorklistBroker/StubPacsClient precedent (bound now, swapped for a
 * real HTTP-backed implementation later, no caller changes). Beyond the
 * LabResultsBroker contract, this also exposes simulate*() methods a real
 * LIMS integration wouldn't have: since there's no actual lab system to
 * click through, these let the ordering UI (or a test) trigger the
 * *inbound* side of the ICD (LIMS -> Clinical) on demand.
 */
class StubLimsClient implements LabResultsBroker
{
    /**
     * Common test codes mapped to already-seeded CDEs (Chunk 1/6) so a
     * simulated result lands somewhere meaningful; anything else falls
     * back to a generic LAB_RESULT_{test_code} CDE — a real LIMS
     * integration would carry its own equivalent local mapping.
     */
    private const KNOWN_TEST_CDE_MAP = [
        'GLUCOSE' => ['cde_code' => 'GLUCOSE_RANDOM', 'unit_label' => 'mmol/L'],
        'CREATININE' => ['cde_code' => 'CREATININE_SERUM', 'unit_label' => 'umol/L'],
    ];

    public function __construct(private readonly ModuleDispatcher $dispatcher)
    {
    }

    public function placeOrder(array $payload): array
    {
        return [
            'status' => 'ORDER_RECEIVED',
            'lab_order_uuid' => $payload['lab_order_uuid'],
            'lims_status' => 'ORDER_RECEIVED',
            'specimen_id' => 'SPEC-'.strtoupper(Str::random(8)),
        ];
    }

    public function cancelOrder(string $labOrderUuid, array $payload): array
    {
        return ['status' => 'CANCELLED'];
    }

    /**
     * Dev-only: simulates the lab validating and releasing a result.
     */
    public function simulateResultValidated(
        int $businessId,
        ?int $branchId,
        string $clientId,
        ?string $visitId,
        string $labOrderUuid,
        string $testCode,
        float $value,
        int $validatedByUserId,
        bool $isAbnormal = false,
    ): array {
        $mapping = self::KNOWN_TEST_CDE_MAP[strtoupper($testCode)] ?? null;

        return $this->dispatcher->dispatch(new LabResultValidatedFact(
            businessId: $businessId,
            branchId: $branchId,
            clientId: $clientId,
            visitId: $visitId,
            labOrderUuid: $labOrderUuid,
            testCode: $testCode,
            cdeCode: $mapping['cde_code'] ?? 'LAB_RESULT_'.strtoupper($testCode),
            valueNumeric: $value,
            unitLabel: $mapping['unit_label'] ?? null,
            isAbnormal: $isAbnormal,
            validatedByUserId: $validatedByUserId,
        ));
    }

    /**
     * Dev-only: simulates a panic-value alert.
     */
    public function simulateCriticalResult(
        int $businessId,
        ?int $branchId,
        string $clientId,
        ?string $visitId,
        string $labOrderUuid,
        string $testCode,
        float $observedValue,
        string $criticalType,
        ?int $authorizingPathologistId = null,
    ): array {
        $mapping = self::KNOWN_TEST_CDE_MAP[strtoupper($testCode)] ?? null;

        return $this->dispatcher->dispatch(new LabCriticalResultFact(
            businessId: $businessId,
            branchId: $branchId,
            clientId: $clientId,
            visitId: $visitId,
            labOrderUuid: $labOrderUuid,
            testCode: $testCode,
            cdeCode: $mapping['cde_code'] ?? 'LAB_RESULT_'.strtoupper($testCode),
            observedValue: $observedValue,
            criticalType: $criticalType,
            authorizingPathologistId: $authorizingPathologistId,
        ));
    }

    /**
     * Dev-only: simulates a reagent-consumption fact fired when a
     * workflow step (e.g. ANALYZER_RUN) completes.
     */
    public function simulateReagentConsumption(
        int $businessId,
        ?int $branchId,
        string $clientId,
        ?string $visitId,
        int $scientistUserId,
        string $itemCode,
        float $quantity,
    ): array {
        return $this->dispatcher->dispatch(new LabReagentConsumptionFact(
            businessId: $businessId,
            branchId: $branchId,
            clientId: $clientId,
            visitId: $visitId,
            scientistUserId: $scientistUserId,
            itemCode: $itemCode,
            quantity: $quantity,
        ));
    }
}
