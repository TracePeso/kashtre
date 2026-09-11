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

    /**
     * A server-computed clinical indicator (BMI, eGFR, NEWS2, GCS, ...) —
     * SRD §1.3/§12.1. Every bound, weight and coefficient lives in Clinical's
     * own scoring dictionary (clinical_scoring_dictionaries /
     * ScoreCalculationService) — never reimplement a formula in a caller.
     *
     * $inputs is keyed exactly as that scoring model's own dictionary names
     * them, which is *not* always the same as the contributing CDE's own
     * code or unit — confirmed against ScoringDictionariesSeeder 2026-08-26:
     *   BMI          — weight_kg (from BODY_WEIGHT, kg), height_m (from
     *                   BODY_HEIGHT — already stored in metres, no conversion).
     *   EGFR_CKD_EPI — Scr in mg/dL (CREATININE_SERUM's own base unit is
     *                   umol/L — divide by 88.4 before calling), age (years),
     *                   sex ('MALE'|'FEMALE', from the patient's own record,
     *                   not a CDE at all).
     * A required input missing or out of range refuses rather than guessing.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed> always includes 'score'; shape beyond that
     *                               varies by score (risk tier, classification
     *                               band, breakdown, ...).
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException on the API driver
     * @throws \Exception on the local driver, or for any score this driver does not implement
     */
    public function calculateScore(
        ClinicalActor $actor,
        string $scoreCode,
        array $inputs,
        ?string $version = null,
    ): array;

    /**
     * SRD v6.1 Phase 7 — the 8-state clinical-standing lifecycle
     * (REGISTERED/PRELIMINARY/FINAL/AMENDED/CORRECTED/CANCELLED/
     * ENTERED_IN_ERROR/UNKNOWN), layered on top of capture() above, which is
     * unchanged and still always lands FINAL. The original observation is
     * never rewritten: correct() preserves it (marked AMENDED) and creates a
     * new CORRECTED row superseding it. Every one of these three is
     * terminal — a second status change on an already-terminal observation
     * refuses (OBSERVATION_STATUS_TERMINAL).
     *
     * @param  array<string, mixed>  $correctedValues  e.g. value_numeric
     * @return ObservationRecord the new CORRECTED observation
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException on the API driver
     */
    public function correct(ClinicalActor $actor, string $observationId, string $reason, array $correctedValues = []): ObservationRecord;

    public function markEnteredInError(ClinicalActor $actor, string $observationId, string $reason): ObservationRecord;

    public function cancel(ClinicalActor $actor, string $observationId, string $reason): ObservationRecord;
}
