<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\ObservationsGateway;
use App\Models\CdeObservation;
use App\Models\CdeRegistry;
use App\Models\ClinicalUomConversion;
use App\Models\ClinicalUomMaster;
use App\Services\Clinical\CdeExecutionEngine;
use App\Support\Clinical\CdeDefinition;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ObservationRecord;
use App\Support\Clinical\UnitOption;

/**
 * CLINICAL_DRIVER=local: today's behaviour, unchanged. Reads the local
 * cde_registry / cde_observations tables and writes through the in-process
 * CdeExecutionEngine, which owns unit normalisation and the physiological
 * safety shield.
 *
 * This class exists so the Livewire components can stop touching Eloquent
 * directly. Nothing here is new logic — it is the queries that used to live
 * in CaptureObservations and BedsideScratchpad, moved behind the interface so
 * the API implementation can take their place without the UI noticing.
 */
class LocalObservationsGateway implements ObservationsGateway
{
    public function __construct(private readonly CdeExecutionEngine $engine)
    {
    }

    public function activeCdes(ClinicalActor $actor, ?string $dataType = 'NUMERIC'): array
    {
        return CdeRegistry::query()
            ->where('is_active', true)
            ->when($dataType, fn ($query) => $query->where('data_type', $dataType))
            // A tenant-specific row overrides the global one of the same code;
            // ordering nulls last then de-duplicating on cde_code picks the
            // override without a correlated subquery.
            ->where(function ($query) use ($actor) {
                $query->where('business_id', $actor->businessId)->orWhereNull('business_id');
            })
            ->orderByRaw('business_id IS NULL')
            ->get()
            ->unique('cde_code')
            ->sortBy('cde_name')
            ->map(fn (CdeRegistry $cde) => CdeDefinition::fromModel($cde))
            ->values()
            ->all();
    }

    public function unitsForCde(ClinicalActor $actor, string $cdeCode): array
    {
        $cde = CdeRegistry::resolve($actor->businessId, $cdeCode);

        if (! $cde) {
            return [];
        }

        $conversions = ClinicalUomConversion::query()
            ->where('cde_code', $cdeCode)
            ->where('is_active', true);

        $unitIds = (clone $conversions)->pluck('from_unit_id')
            ->merge((clone $conversions)->pluck('to_unit_id'))
            ->push($cde->base_uom_id)
            ->filter()
            ->unique();

        return ClinicalUomMaster::whereIn('id', $unitIds)
            ->orderBy('unit_label')
            ->get()
            ->map(fn (ClinicalUomMaster $unit) => UnitOption::fromModel($unit))
            ->all();
    }

    public function capture(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        array $observation,
    ): ObservationRecord {
        $result = $this->engine->captureObservation([
            'client_id' => $patientId,
            'visit_id' => $visitId,
            'cde_code' => $observation['cde_code'],
            'value_numeric' => (float) $observation['value_numeric'],
            'input_uom_id' => (int) ($observation['input_uom_id'] ?? 0),
            'capture_method' => $observation['capture_method'] ?? CdeObservation::METHOD_MANUAL,
        ], $actor->userId, $actor->businessId, $actor->branchId);

        return new ObservationRecord(
            id: $result['observation_id'],
            cde_code: $observation['cde_code'],
            captured_value_numeric: $result['captured_value'],
            base_value_numeric: $result['base_value_normalized'],
            captured_at: now(),
            is_panic_high: $result['is_panic_high'],
            is_panic_low: $result['is_panic_low'],
        );
    }

    public function recentForPatient(
        ClinicalActor $actor,
        string $patientId,
        int $limit = 20,
        ?string $cdeCode = null,
        ?int $displayUomId = null,
    ): array {
        // $displayUomId is honoured by the API driver, which re-scales the
        // alert boundaries along with the values. Doing half that here — the
        // conversion without the boundary re-scaling — would show a clinician
        // mg/dL numbers against mmol/L thresholds, so the local driver
        // deliberately returns base units and ignores the argument.
        return CdeObservation::query()
            ->where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->when($cdeCode, fn ($query) => $query->where('cde_code', $cdeCode))
            ->orderByDesc('captured_at')
            ->limit($limit)
            ->get()
            ->map(fn (CdeObservation $observation) => ObservationRecord::fromModel($observation))
            ->all();
    }

    /**
     * Local has no clinical_scoring_dictionaries table — a tenant cannot
     * revise a guideline's bounds/coefficients here the way they can under
     * the API driver. Only the two closed-form formulas that need no
     * configurable matrix at all are implemented, mirroring Clinical's own
     * ScoreCalculationService::bodyMassIndex()/renalClearance() (CKD-EPI 2021,
     * confirmed against clinical_module's ScoringDictionariesSeeder
     * 2026-08-26) so the two drivers agree on the arithmetic. Anything band-
     * or option-based (NEWS2, GCS, APGAR, SATS) needs that dictionary and is
     * refused outright rather than silently reimplemented with invented bounds.
     */
    public function calculateScore(
        ClinicalActor $actor,
        string $scoreCode,
        array $inputs,
        ?string $version = null,
    ): array {
        return match ($scoreCode) {
            'BMI' => $this->bodyMassIndex($inputs),
            'EGFR_CKD_EPI' => $this->renalClearance($inputs),
            default => throw new \Exception(
                "Score calculation for [{$scoreCode}] needs Clinical Module's own scoring dictionary — not available under CLINICAL_DRIVER=local."
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function bodyMassIndex(array $inputs): array
    {
        if (! isset($inputs['weight_kg'], $inputs['height_m'])) {
            throw new \Exception('BMI requires weight_kg and height_m.');
        }

        $height = (float) $inputs['height_m'];

        if ($height <= 0) {
            throw new \Exception('Height must be greater than zero.');
        }

        return ['score' => round((float) $inputs['weight_kg'] / ($height ** 2), 1)];
    }

    /**
     * CKD-EPI 2021 race-free equation — same coefficients as Clinical's own
     * seeded EGFR_CKD_EPI matrix (base=142, k: F=0.7/M=0.9, alpha: F=-0.241/
     * M=-0.302, max_exponent=-1.200, age_factor=0.9938, sex_multiplier:
     * F=1.012/M=1.000). Duplicated here rather than shared because the two
     * drivers have no common dependency to hold it — if Clinical's tenant
     * settings ever revise these, only the API driver picks that up.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function renalClearance(array $inputs): array
    {
        if (! isset($inputs['Scr'], $inputs['age'], $inputs['sex'])) {
            throw new \Exception('eGFR requires Scr, age and sex.');
        }

        $sex = strtoupper((string) $inputs['sex']);
        $coefficients = [
            'k' => ['FEMALE' => 0.7, 'MALE' => 0.9],
            'alpha' => ['FEMALE' => -0.241, 'MALE' => -0.302],
            'sex_multiplier' => ['FEMALE' => 1.012, 'MALE' => 1.000],
        ];

        if (! isset($coefficients['k'][$sex])) {
            throw new \Exception("No renal coefficients configured for sex [{$sex}].");
        }

        $creatinine = (float) $inputs['Scr'];

        if ($creatinine <= 0) {
            throw new \Exception('Serum creatinine must be greater than zero.');
        }

        $ratio = $creatinine / $coefficients['k'][$sex];

        $result = 142
            * min($ratio, 1) ** $coefficients['alpha'][$sex]
            * max($ratio, 1) ** -1.200
            * 0.9938 ** (float) $inputs['age']
            * $coefficients['sex_multiplier'][$sex];

        return ['score' => round($result, 1)];
    }

    /**
     * SRD v6.1 Phase 7's 8-state clinical-standing lifecycle is Clinical-
     * owned, with no local equivalent — cde_observations here has no status
     * column beyond the pre-existing validation_status (device-import
     * review, a different concept, unchanged). Refused outright rather than
     * fabricated.
     */
    public function correct(ClinicalActor $actor, string $observationId, string $reason, array $correctedValues = []): ObservationRecord
    {
        $this->refuseStatusChange();
    }

    public function markEnteredInError(ClinicalActor $actor, string $observationId, string $reason): ObservationRecord
    {
        $this->refuseStatusChange();
    }

    public function cancel(ClinicalActor $actor, string $observationId, string $reason): ObservationRecord
    {
        $this->refuseStatusChange();
    }

    private function refuseStatusChange(): never
    {
        throw new \RuntimeException('Observation status corrections (SRD v6.1 Phase 7) are only available under CLINICAL_DRIVER=api.');
    }
}
