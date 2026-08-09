<?php

namespace App\Services\Clinical;

use App\Models\CdeObservation;
use App\Models\Client;
use App\Models\ClinicalDdiDictionary;
use App\Models\ClinicalMedicationOrder;

/**
 * Engineering doc §5. All patient safety enforcement here is deterministic
 * (no AI) — every check reads real recorded data (CDE observations, active
 * orders) against configured thresholds, never a hardcoded drug name.
 */
class DeterministicCdssShield
{
    /**
     * @param array{drug_code: ?string, dose_mg: float, max_mg_per_kg?: float, is_nephrotoxic?: bool} $orderPayload
     * @return array{is_safe: bool, hard_blocks: array<int, array{type: string, message: string}>, warnings: array<int, array{type: string, message: string}>}
     */
    public function evaluateMedicationSafety(array $orderPayload, string $clientId, int $businessId): array
    {
        $warnings = [];
        $hardBlocks = [];

        $this->checkPediatricWeightDose($orderPayload, $clientId, $businessId, $hardBlocks);
        $this->checkRenalFunction($orderPayload, $clientId, $businessId, $warnings);
        $this->checkAllergies($orderPayload, $clientId, $businessId, $hardBlocks);
        $this->checkDrugInteractions($orderPayload, $clientId, $businessId, $hardBlocks, $warnings);

        return [
            'is_safe' => count($hardBlocks) === 0,
            'hard_blocks' => $hardBlocks,
            'warnings' => $warnings,
        ];
    }

    private function checkPediatricWeightDose(array $orderPayload, string $clientId, int $businessId, array &$hardBlocks): void
    {
        $client = Client::where('business_id', $businessId)->where('client_id', $clientId)->first();

        if (! $client || $client->age === null || $client->age >= 12) {
            return;
        }

        $weight = $this->latestObservation($businessId, $clientId, 'BODY_WEIGHT');

        if (! $weight) {
            return;
        }

        $maxMgPerKg = $orderPayload['max_mg_per_kg'] ?? 15.0;
        $allowedMaxDose = $weight * $maxMgPerKg;
        $requestedMg = $orderPayload['dose_mg'];

        if ($requestedMg > $allowedMaxDose * 1.5) {
            $hardBlocks[] = [
                'type' => 'PEDIATRIC_WEIGHT_OVERDOSE',
                'message' => "Requested dose ({$requestedMg}mg) exceeds 150% of the maximum safe weight-based limit ({$allowedMaxDose}mg for {$weight}kg).",
            ];
        }
    }

    private function checkRenalFunction(array $orderPayload, string $clientId, int $businessId, array &$warnings): void
    {
        $egfr = $this->latestObservation($businessId, $clientId, 'EGFR_CALCULATED');

        if ($egfr && $egfr < 50.0 && ($orderPayload['is_nephrotoxic'] ?? false)) {
            $warnings[] = [
                'type' => 'RENAL_DOSE_REDUCTION_RECOMMENDED',
                'message' => "Patient eGFR is reduced ({$egfr} mL/min/1.73m²). Recommend dose reduction or interval extension.",
            ];
        }
    }

    private function checkAllergies(array $orderPayload, string $clientId, int $businessId, array &$hardBlocks): void
    {
        if (empty($orderPayload['drug_code'])) {
            return;
        }

        $item = \App\Models\Item::where('business_id', $businessId)->where('code', $orderPayload['drug_code'])->first();

        if (! $item) {
            return;
        }

        $searchable = strtolower($item->generic_name.' '.$item->name.' '.$item->other_names);

        $allergies = CdeObservation::where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->where('cde_code', 'ALLERGY_MEDICATION')
            ->pluck('captured_value_text')
            ->filter();

        foreach ($allergies as $allergy) {
            if ($allergy && str_contains($searchable, strtolower(trim($allergy)))) {
                $hardBlocks[] = [
                    'type' => 'DRUG_ALLERGY',
                    'message' => "Patient has a recorded allergy to {$allergy}, which matches {$item->name}.",
                ];
            }
        }
    }

    private function checkDrugInteractions(array $orderPayload, string $clientId, int $businessId, array &$hardBlocks, array &$warnings): void
    {
        if (empty($orderPayload['drug_code'])) {
            return;
        }

        $activeDrugCodes = ClinicalMedicationOrder::where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->where('status', ClinicalMedicationOrder::STATUS_ACTIVE)
            ->whereNotNull('drug_code')
            ->where('drug_code', '!=', $orderPayload['drug_code'])
            ->pluck('drug_code')
            ->unique();

        foreach ($activeDrugCodes as $activeDrug) {
            $ddi = ClinicalDdiDictionary::findInteraction($businessId, $orderPayload['drug_code'], $activeDrug);

            if (! $ddi) {
                continue;
            }

            $entry = ['type' => 'DDI_'.$ddi->severity, 'message' => "Interaction between {$orderPayload['drug_code']} and {$activeDrug}: {$ddi->description}"];

            if ($ddi->severity === ClinicalDdiDictionary::SEVERITY_HARD_BLOCK) {
                $hardBlocks[] = $entry;
            } elseif ($ddi->severity === ClinicalDdiDictionary::SEVERITY_WARNING) {
                $warnings[] = $entry;
            }
        }
    }

    private function latestObservation(int $businessId, string $clientId, string $cdeCode): ?float
    {
        $value = CdeObservation::where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->where('cde_code', $cdeCode)
            ->orderByDesc('captured_at')
            ->value('base_value_numeric');

        return $value !== null ? (float) $value : null;
    }
}
