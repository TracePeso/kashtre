<?php

namespace App\Services;

use App\Models\InsuranceCompany;
use App\Models\ThirdPartyVendorServiceCharge;
use App\Models\ThirdPartyVendorServiceChargeDefault;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ThirdPartyVendorServiceChargeService
{
    /**
     * @return array<int, array{lower_bound: float, upper_bound: ?float, amount: float, type: string}>
     */
    public function recommendedDefaults(): array
    {
        $saved = ThirdPartyVendorServiceChargeDefault::activeOrdered();
        if ($saved !== []) {
            return collect($saved)
                ->map(fn (ThirdPartyVendorServiceChargeDefault $row) => $this->normalizeTierShape([
                    'lower_bound' => $row->lower_bound,
                    'upper_bound' => $row->upper_bound,
                    'amount' => $row->amount,
                    'type' => $row->type,
                ]))
                ->values()
                ->all();
        }

        $tiers = config('third_party_vendor_service_charges.default_tiers', []);

        return collect($tiers)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => $this->normalizeTierShape($row))
            ->values()
            ->all();
    }

    public function hasSavedRecommendedDefaults(): bool
    {
        return ThirdPartyVendorServiceChargeDefault::query()->exists();
    }

    /**
     * Replace system-wide default tiers (Kashtre admin).
     *
     * @param  array<int, array{lower_bound: float, upper_bound: ?float, amount: float, type: string}>  $rows
     */
    public function saveRecommendedDefaults(array $rows, ?int $updatedBy = null): void
    {
        DB::transaction(function () use ($rows, $updatedBy): void {
            ThirdPartyVendorServiceChargeDefault::query()->delete();

            foreach ($rows as $index => $row) {
                ThirdPartyVendorServiceChargeDefault::create([
                    'lower_bound' => $row['lower_bound'],
                    'upper_bound' => $row['upper_bound'],
                    'amount' => $row['amount'],
                    'type' => $row['type'],
                    'sort_order' => $index,
                    'is_active' => true,
                    'updated_by' => $updatedBy,
                ]);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawRows
     * @return array<int, array{lower_bound: float, upper_bound: ?float, amount: float, type: string}>
     */
    public function normalizeTierRowsFromRequest(array $rawRows): array
    {
        $out = [];
        foreach ($rawRows as $row) {
            $upper = $row['upper_bound'] ?? null;
            if ($upper === '' || $upper === null) {
                $upper = null;
            } else {
                $upper = (float) $upper;
            }

            $out[] = [
                'lower_bound' => (float) ($row['lower_bound'] ?? 0),
                'upper_bound' => $upper,
                'amount' => (float) ($row['amount'] ?? 0),
                'type' => (string) ($row['type'] ?? 'percentage'),
            ];
        }

        return $out;
    }

    /**
     * Saved active tiers for a clinic schedule (clinic-wide or one vendor).
     *
     * @return Collection<int, ThirdPartyVendorServiceCharge>
     */
    public function savedTiers(int $businessId, ?int $insuranceCompanyId = null): Collection
    {
        return ThirdPartyVendorServiceCharge::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->when(
                $insuranceCompanyId !== null,
                fn ($q) => $q->where('insurance_company_id', $insuranceCompanyId),
                fn ($q) => $q->whereNull('insurance_company_id')
            )
            ->orderBy('sort_order')
            ->orderBy('lower_bound')
            ->get();
    }

    /**
     * Effective tiers: vendor-specific schedule if configured, else clinic-wide; if none saved, recommended defaults.
     *
     * @return array{
     *     source: string,
     *     insurance_company_id: ?int,
     *     tiers: array<int, array<string, mixed>>
     * }
     */
    public function effectiveSchedule(int $businessId, ?int $insuranceCompanyId = null): array
    {
        if ($insuranceCompanyId !== null) {
            $vendorTiers = $this->savedTiers($businessId, $insuranceCompanyId);
            if ($vendorTiers->isNotEmpty()) {
                return [
                    'source' => 'vendor_saved',
                    'insurance_company_id' => $insuranceCompanyId,
                    'tiers' => $this->serializeTierCollection($vendorTiers),
                ];
            }
        }

        $clinicTiers = $this->savedTiers($businessId, null);
        if ($clinicTiers->isNotEmpty()) {
            return [
                'source' => 'clinic_saved',
                'insurance_company_id' => $insuranceCompanyId,
                'tiers' => $this->serializeTierCollection($clinicTiers),
            ];
        }

        return [
            'source' => 'recommended_defaults',
            'insurance_company_id' => $insuranceCompanyId,
            'tiers' => $this->recommendedDefaults(),
        ];
    }

    /**
     * Resolve Kashtre insurance_company.id from third-party vendor business id.
     */
    public function resolveLocalInsuranceCompanyId(int $businessId, int $thirdPartyVendorId): ?int
    {
        $id = InsuranceCompany::query()
            ->where('business_id', $businessId)
            ->where('third_party_business_id', $thirdPartyVendorId)
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        $fallback = InsuranceCompany::query()
            ->where('business_id', $businessId)
            ->where('id', $thirdPartyVendorId)
            ->value('id');

        return $fallback ? (int) $fallback : null;
    }

    /**
     * @return array{
     *     subtotal: float,
     *     service_charge: float,
     *     schedule_source: string,
     *     tier: ?array<string, mixed>
     * }
     */
    public function calculate(int $businessId, float $subtotal, ?int $insuranceCompanyId = null): array
    {
        $subtotal = max(0, round($subtotal, 2));
        $schedule = $this->effectiveSchedule($businessId, $insuranceCompanyId);
        $tierModel = ThirdPartyVendorServiceCharge::tierForSubtotal($businessId, $subtotal, $insuranceCompanyId);

        if ($tierModel !== null) {
            return [
                'subtotal' => $subtotal,
                'service_charge' => round($tierModel->amountForSubtotal($subtotal), 2),
                'schedule_source' => $insuranceCompanyId && $tierModel->insurance_company_id
                    ? 'vendor_saved'
                    : 'clinic_saved',
                'tier' => $this->serializeTierModel($tierModel),
            ];
        }

        $tierFromDefaults = $this->tierFromTierArrays($schedule['tiers'], $subtotal);

        return [
            'subtotal' => $subtotal,
            'service_charge' => $tierFromDefaults
                ? round($this->amountFromTierShape($tierFromDefaults, $subtotal), 2)
                : 0.0,
            'schedule_source' => (string) $schedule['source'],
            'tier' => $tierFromDefaults,
        ];
    }

    /**
     * @param  Collection<int, ThirdPartyVendorServiceCharge>  $tiers
     * @return array<int, array<string, mixed>>
     */
    public function serializeTierCollection(Collection $tiers): array
    {
        return $tiers->map(fn (ThirdPartyVendorServiceCharge $t) => $this->serializeTierModel($t))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTierModel(ThirdPartyVendorServiceCharge $tier): array
    {
        return [
            'id' => $tier->id,
            'lower_bound' => (float) $tier->lower_bound,
            'upper_bound' => $tier->upper_bound !== null ? (float) $tier->upper_bound : null,
            'amount' => (float) $tier->amount,
            'type' => (string) $tier->type,
            'formatted_amount' => $tier->formatted_amount,
            'insurance_company_id' => $tier->insurance_company_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{lower_bound: float, upper_bound: ?float, amount: float, type: string}
     */
    protected function normalizeTierShape(array $row): array
    {
        $upper = $row['upper_bound'] ?? null;

        return [
            'lower_bound' => (float) ($row['lower_bound'] ?? 0),
            'upper_bound' => ($upper === null || $upper === '') ? null : (float) $upper,
            'amount' => (float) ($row['amount'] ?? 0),
            'type' => (string) ($row['type'] ?? 'percentage'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<string, mixed>|null
     */
    protected function tierFromTierArrays(array $tiers, float $subtotal): ?array
    {
        $match = null;
        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }
            $lower = (float) ($tier['lower_bound'] ?? 0);
            $upper = $tier['upper_bound'] ?? null;
            if ($subtotal < $lower) {
                continue;
            }
            if ($upper !== null && $upper !== '' && $subtotal > (float) $upper) {
                continue;
            }
            if ($match === null || $lower > (float) ($match['lower_bound'] ?? 0)) {
                $match = $tier;
            }
        }

        return $match;
    }

    /**
     * @param  array<string, mixed>  $tier
     */
    protected function amountFromTierShape(array $tier, float $subtotal): float
    {
        $amount = (float) ($tier['amount'] ?? 0);
        $type = (string) ($tier['type'] ?? 'percentage');

        if ($type === 'fixed') {
            return $amount;
        }

        return ($subtotal * $amount) / 100;
    }
}
