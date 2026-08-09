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
}
