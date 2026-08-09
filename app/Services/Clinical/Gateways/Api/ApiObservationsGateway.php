<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\ObservationsGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\CdeDefinition;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ObservationRecord;
use App\Support\Clinical\UnitOption;
use Illuminate\Support\Facades\Cache;

/**
 * CLINICAL_DRIVER=api: observations over HTTP — API Integration Guide §10.2.
 *
 * The Clinical Module owns the CDE registry, unit conversion and the
 * physiological guard; this class only shapes requests and unwraps responses.
 * Notably it does *not* pre-validate values before sending them. Duplicating
 * the physiological bounds here would mean two sources of truth for what is
 * compatible with life, and the local copy would drift.
 */
class ApiObservationsGateway implements ObservationsGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function activeCdes(ClinicalActor $actor, ?string $dataType = 'NUMERIC'): array
    {
        // The registry changes when an administrator edits it, which is rare,
        // and it is read on every render of the charting form. Cached per
        // tenant and data type; five minutes keeps an edit visible without a
        // deploy while keeping the form off the network.
        $key = "clinical:cdes:{$actor->businessId}:".($dataType ?? 'ALL');

        $payload = Cache::remember($key, now()->addMinutes(5), fn () => $this->client->get(
            'settings/cde-registry',
            array_filter([
                'data_type' => $dataType,
                'is_active' => 1,
            ]),
            ['business_id' => $actor->businessId],
        ));

        return array_map(
            fn (array $cde) => CdeDefinition::fromApi($cde),
            $this->rows($payload),
        );
    }

    public function unitsForCde(ClinicalActor $actor, string $cdeCode): array
    {
        $key = "clinical:cde-units:{$actor->businessId}:{$cdeCode}";

        $payload = Cache::remember($key, now()->addMinutes(5), fn () => $this->client->get(
            'settings/dictionaries/unit-conversions',
            ['cde_code' => $cdeCode],
            ['business_id' => $actor->businessId],
        ));

        $units = [];

        foreach ($this->rows($payload) as $row) {
            // Conversions are directional rows; both endpoints of each are a
            // unit the clinician may legitimately enter this CDE in.
            foreach (['from_unit', 'to_unit'] as $side) {
                if (is_array($row[$side] ?? null)) {
                    $unit = UnitOption::fromApi($row[$side]);
                    $units[$unit->id] = $unit;
                }
            }
        }

        uasort($units, fn (UnitOption $a, UnitOption $b) => strcmp($a->unit_label, $b->unit_label));

        return array_values($units);
    }

    public function capture(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        array $observation,
    ): ObservationRecord {
        $payload = array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'cde_code' => $observation['cde_code'],
            'value_numeric' => (float) $observation['value_numeric'],
            // Omitted rather than sent null: §10.2 says leaving input_uom_id
            // out means "use the CDE's base unit", whereas an explicit null is
            // a validation failure.
            'input_uom_id' => $observation['input_uom_id'] ?? null,
            'capture_method' => $observation['capture_method'] ?? 'MANUAL',
            'captured_at' => $observation['captured_at'] ?? now()->toIso8601String(),
        ], fn ($value) => $value !== null);

        $data = $this->client->post('clinical/observations', $payload, [
            'business_id' => $actor->businessId,
            // One charting of one CDE at one instant is one logical action.
            // A nurse who taps Save twice on a flaky connection charts one
            // reading, not two.
            'idempotency_key' => sprintf(
                'obs-%s-%s-%s',
                $patientId,
                $observation['cde_code'],
                $payload['captured_at'],
            ),
        ]);

        return ObservationRecord::fromApi($data + ['cde_code' => $observation['cde_code']]);
    }

    public function recentForPatient(
        ClinicalActor $actor,
        string $patientId,
        int $limit = 20,
        ?string $cdeCode = null,
        ?int $displayUomId = null,
    ): array {
        $data = $this->client->get(
            "clinical/patients/{$patientId}/observations",
            array_filter([
                'limit' => $limit,
                'cde_code' => $cdeCode,
                // Clinical re-scales the alert boundaries to match, so the
                // values and the thresholds a clinician sees are in the same
                // unit. That pairing is why this is a server concern.
                'display_uom_id' => $displayUomId,
            ], fn ($value) => $value !== null),
            ['business_id' => $actor->businessId],
        );

        return array_map(
            fn (array $observation) => ObservationRecord::fromApi($observation),
            $this->rows($data),
        );
    }

    /**
     * Collection endpoints return `data` as a bare array; a few nest it under
     * a named key. Tolerating both keeps us honest about §2's instruction to
     * treat unknown response shapes as forward compatibility.
     *
     * @param  array<mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $payload): array
    {
        $rows = array_is_list($payload) ? $payload : ($payload['items'] ?? $payload['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }
}
