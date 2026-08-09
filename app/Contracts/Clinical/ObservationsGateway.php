<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\CdeDefinition;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ObservationRecord;
use App\Support\Clinical\UnitOption;

/**
 * Atomic CDE observations — API Integration Guide §10.2.
 *
 * Bound to either the in-process CdeExecutionEngine or HTTP calls to the
 * Clinical Module, per CLINICAL_DRIVER. Callers must not care which.
 *
 * One behaviour is guaranteed by both implementations and relied on by the
 * UI: a value outside the CDE's physiological bounds is *refused*, not
 * clamped or flagged. That check exists to catch unit confusion, which is the
 * commonest cause of a dangerous number reaching a chart.
 */
interface ObservationsGateway
{
    /**
     * CDEs available for charting, for building the dynamic capture form.
     * Never hardcode a vitals field — the registry is the source of truth.
     *
     * @return array<int, CdeDefinition>
     */
    public function activeCdes(ClinicalActor $actor, ?string $dataType = 'NUMERIC'): array;

    /**
     * Units a value for this CDE may legitimately be entered in — the base
     * unit plus anything reachable by a configured conversion. Keeps the
     * selector from offering 'kg' for a glucose reading.
     *
     * @return array<int, UnitOption>
     */
    public function unitsForCde(ClinicalActor $actor, string $cdeCode): array;

    /**
     * Charts one observation, normalising it to the CDE's base unit.
     *
     * @param  array{cde_code: string, value_numeric: float, input_uom_id?: ?int, capture_method?: string, captured_at?: ?string}  $observation
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException when the value fails the physiological guard (API driver)
     * @throws \Exception when the value fails the physiological guard (local driver)
     */
    public function capture(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        array $observation,
    ): ObservationRecord;

    /**
     * Flowsheet for a patient, newest first. `displayUomId` asks Clinical to
     * convert values *and* re-scale the alert boundaries to match, so a
     * clinician reading in mg/dL sees mg/dL thresholds.
     *
     * @return array<int, ObservationRecord>
     */
    public function recentForPatient(
        ClinicalActor $actor,
        string $patientId,
        int $limit = 20,
        ?string $cdeCode = null,
        ?int $displayUomId = null,
    ): array;
}
