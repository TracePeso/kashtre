<?php

namespace App\Services\Clinical;

use App\Models\CdeObservation;
use App\Models\CdeRegistry;
use App\Models\ClinicalUomConversion;
use Exception;

/**
 * Atomic CDE observation capture: physiological-range input safety shield,
 * base-unit normalization, and persistence. Adapted from the Clinical
 * Module Engineering doc §4, generalized to use CdeRegistry's
 * physiological_min/physiological_max columns instead of the doc's
 * hardcoded GLUCOSE_RANDOM example — the SRD's own zero-hardcoding mandate
 * (§1) argues against baking any one CDE code into this engine.
 */
class CdeExecutionEngine
{
    /**
     * @param array{client_id: string, visit_id?: ?string, cde_code: string, value_numeric: float, input_uom_id: int, capture_method?: string, validation_status?: string} $payload
     * @return array{observation_id: int, captured_value: float, base_value_normalized: ?float, is_panic_high: bool, is_panic_low: bool}
     *
     * @throws Exception if the CDE code is unknown or the value fails the physiological safety shield
     */
    public function captureObservation(array $payload, int $userId, int $businessId, ?int $branchId = null): array
    {
        $cde = CdeRegistry::resolve($businessId, $payload['cde_code']);

        if (! $cde) {
            throw new Exception("Invalid CDE Code: {$payload['cde_code']}");
        }

        $inputValue = (float) $payload['value_numeric'];
        $inputUomId = (int) $payload['input_uom_id'];
        $baseUomId = $cde->base_uom_id;

        $baseValue = $baseUomId
            ? $this->normalizeToBaseUnit($cde->cde_code, $inputValue, $inputUomId, (int) $baseUomId, $businessId)
            : $inputValue;

        // normal_range/critical_*/physiological_* are all defined in the
        // CDE's base unit, so the shield runs against the normalized value
        // — not the raw input, which may be in a different display unit
        // (e.g. 126.1 mg/dL is a normal glucose reading; only ~7.0 mmol/L
        // is meaningful against a mmol/L-scaled bound). When the clinician
        // enters a value with the unit selector already on the base unit,
        // normalizeToBaseUnit() is a no-op, so a genuinely implausible
        // number (180 typed straight into a mmol/L field) still fails here.
        $this->validatePhysiologicalHeuristic($cde, $baseValue);

        $observation = CdeObservation::create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'client_id' => $payload['client_id'],
            'visit_id' => $payload['visit_id'] ?? null,
            'cde_code' => $cde->cde_code,
            'captured_value_numeric' => $inputValue,
            'input_uom_id' => $inputUomId,
            'base_uom_id' => $baseUomId,
            'base_value_numeric' => $baseValue,
            'capture_method' => $payload['capture_method'] ?? CdeObservation::METHOD_MANUAL,
            'validation_status' => $payload['validation_status'] ?? 'VALIDATED',
            'validated_by_user_id' => $userId,
            'captured_at' => now(),
        ]);

        return [
            'observation_id' => $observation->id,
            'captured_value' => $inputValue,
            'base_value_normalized' => $baseValue,
            'is_panic_high' => $cde->critical_high !== null && $baseValue > (float) $cde->critical_high,
            'is_panic_low' => $cde->critical_low !== null && $baseValue < (float) $cde->critical_low,
        ];
    }

    /**
     * Input Context Safety Shield (SRD §1.6.3): blocks values outside a
     * CDE's configured physiological bounds, regardless of which CDE it
     * is — e.g. catches "180" typed into a glucose field expecting
     * mmol/L when the nurse meant 180 mg/dL (~10.0 mmol/L).
     *
     * @throws Exception
     */
    private function validatePhysiologicalHeuristic(CdeRegistry $cde, float $value): void
    {
        if ($cde->physiological_min !== null && $value < (float) $cde->physiological_min) {
            throw new Exception("HEURISTIC_SAFETY_BLOCK: Value {$value} is below the physiological minimum ({$cde->physiological_min}) configured for {$cde->cde_code}.");
        }

        if ($cde->physiological_max !== null && $value > (float) $cde->physiological_max) {
            throw new Exception("HEURISTIC_SAFETY_BLOCK: Value {$value} exceeds the physiological maximum ({$cde->physiological_max}) configured for {$cde->cde_code}.");
        }
    }

    public function normalizeToBaseUnit(string $cdeCode, float $value, int $fromUomId, int $toUomId, int $businessId): float
    {
        if ($fromUomId === $toUomId) {
            return $value;
        }

        $conversion = ClinicalUomConversion::query()
            ->where('cde_code', $cdeCode)
            ->where('from_unit_id', $fromUomId)
            ->where('to_unit_id', $toUomId)
            ->where('is_active', true)
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)->orWhereNull('business_id');
            })
            ->orderByRaw('business_id IS NULL')
            ->first();

        if (! $conversion) {
            return $value;
        }

        return match ($conversion->conversion_type) {
            'MULTIPLIER' => round($value * (float) $conversion->factor, $conversion->decimal_precision),
            'DIVISOR' => round($value / (float) $conversion->factor, $conversion->decimal_precision),
            'POLYNOMIAL' => round($this->applyNamedFormula($conversion->formula_expression, $value), $conversion->decimal_precision),
            default => $value,
        };
    }

    /**
     * Deliberately not a generic expression evaluator (no eval()) — matches
     * a small named set of formulas. Add a new arm here (and a matching
     * seeded row) before introducing another POLYNOMIAL conversion.
     */
    private function applyNamedFormula(?string $formulaName, float $value): float
    {
        return match ($formulaName) {
            'C_TO_F' => ($value * 1.8) + 32,
            'F_TO_C' => ($value - 32) / 1.8,
            default => throw new Exception("Unknown POLYNOMIAL conversion formula: {$formulaName}"),
        };
    }
}
