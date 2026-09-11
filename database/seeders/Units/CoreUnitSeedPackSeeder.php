<?php

namespace Database\Seeders\Units;

use App\Domain\Units\Enums\ConversionRuleType;
use App\Domain\Units\Enums\UnitStatus;
use App\Domain\Units\Models\ConversionRule;
use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\Models\QuantityKind;
use App\Domain\Units\Models\UnitAlias;
use App\Domain\Units\Models\UnitPrefix;
use App\Domain\Units\Models\UnitVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoreUnitSeedPackSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/Units/core-units-v1.json');
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $tenant = (string) config('units.system_tenant_key', 'SYSTEM');
        $now = now();

        DB::transaction(function () use ($manifest, $tenant, $now) {
            $kinds = [];
            foreach ($manifest['quantityKinds'] as $kind) {
                $row = QuantityKind::query()->updateOrCreate(
                    ['tenant_key' => $tenant, 'code' => $kind['code']],
                    [
                        'public_id' => $this->stableUlid('KIND', $kind['code']),
                        'name' => $kind['name'],
                        'dimension_vector' => $kind['dimension'] ?? [],
                        'is_system' => true,
                        'status' => UnitStatus::ACTIVE->value,
                        'effective_from' => $now,
                    ]
                );
                $kinds[$kind['code']] = $row;
            }

            foreach ($manifest['prefixes'] as $prefix) {
                UnitPrefix::query()->updateOrCreate(
                    ['tenant_key' => $tenant, 'code' => $prefix['code']],
                    [
                        'public_id' => $this->stableUlid('PFX', $prefix['code']),
                        'name' => $prefix['name'],
                        'symbol' => $prefix['symbol'],
                        'radix' => 10,
                        'exponent' => (int) $prefix['exponent'],
                        'factor_decimal' => (string) $prefix['factor'],
                        'is_binary' => false,
                        'is_system' => true,
                        'status' => UnitStatus::ACTIVE->value,
                        'effective_from' => $now,
                    ]
                );
            }

            $units = [];
            foreach ($manifest['units'] as $unit) {
                $kind = $kinds[$unit['quantityKind']] ?? null;
                if (! $kind) {
                    continue;
                }

                $row = CoreUnit::query()->updateOrCreate(
                    ['tenant_key' => $tenant, 'code' => $unit['code']],
                    [
                        'public_id' => $this->stableUlid('UNIT', $unit['code']),
                        'unit_class' => $unit['class'],
                        'canonical_name' => $unit['name'],
                        'symbol' => $unit['symbol'],
                        'ascii_symbol' => $unit['symbol'],
                        'standard_system_uri' => $manifest['standardSystem'] ?? null,
                        'ucum_code' => $unit['ucum'] ?? null,
                        'standard_verification_status' => isset($unit['ucum']) ? 'SEEDED' : 'LOCAL',
                        'quantity_kind_id' => $kind->id,
                        'dimension_vector' => $unit['dimension'] ?? [],
                        'allows_prefix' => (bool) ($unit['allowsPrefix'] ?? false),
                        'allows_composition' => true,
                        'is_system' => true,
                        'status' => UnitStatus::ACTIVE->value,
                    ]
                );

                UnitVersion::query()->updateOrCreate(
                    ['unit_id' => $row->id, 'version_no' => 1],
                    [
                        'public_id' => $this->stableUlid('UVER', $unit['code'].'_1'),
                        'scale_decimal' => (string) ($unit['scale'] ?? '1'),
                        'offset_decimal' => (string) ($unit['offset'] ?? '0'),
                        'reference_unit_public_id' => isset($unit['reference'])
                            ? $this->stableUlid('UNIT', $unit['reference'])
                            : null,
                        'calculation_scale' => 18,
                        'display_precision' => 4,
                        'rounding_mode' => 'HALF_UP',
                        'effective_from' => $now,
                        'status' => UnitStatus::ACTIVE->value,
                        'approved_at' => $now,
                    ]
                );

                $units[$unit['code']] = $row;
            }

            // Second pass: composite component rows (need all units present).
            foreach ($manifest['units'] as $unit) {
                $components = $unit['components'] ?? null;
                if (! is_array($components) || $components === []) {
                    continue;
                }

                $row = $units[$unit['code']] ?? null;
                if (! $row) {
                    continue;
                }

                $version = UnitVersion::query()
                    ->where('unit_id', $row->id)
                    ->where('version_no', 1)
                    ->first();
                if (! $version) {
                    continue;
                }

                foreach ($components as $i => $component) {
                    $componentUnit = $units[$component['unit']] ?? null;
                    if (! $componentUnit) {
                        continue;
                    }

                    \App\Domain\Units\Models\UnitComponent::query()->updateOrCreate(
                        [
                            'unit_version_id' => $version->id,
                            'sequence' => $i + 1,
                        ],
                        [
                            'operator' => $component['operator'],
                            'component_unit_id' => $componentUnit->id,
                            'exponent' => (int) ($component['exponent'] ?? 1),
                        ]
                    );
                }
            }

            foreach ($manifest['scaleRules'] as $rule) {
                $from = $units[$rule['from']] ?? null;
                $to = $units[$rule['to']] ?? null;
                if (! $from || ! $to) {
                    continue;
                }

                ConversionRule::query()->updateOrCreate(
                    [
                        'tenant_key' => $tenant,
                        'from_unit_id' => $from->id,
                        'to_unit_id' => $to->id,
                        'version_no' => 1,
                    ],
                    [
                        'public_id' => $this->stableUlid('RULE', $rule['from'].'_'.$rule['to']),
                        'rule_type' => ConversionRuleType::SCALE->value,
                        'scale_decimal' => (string) $rule['scale'],
                        'offset_decimal' => '0',
                        'is_bidirectional' => (bool) ($rule['bidirectional'] ?? false),
                        'calculation_scale' => 18,
                        'display_precision' => 4,
                        'rounding_mode' => 'HALF_UP',
                        'status' => UnitStatus::ACTIVE->value,
                        'effective_from' => $now,
                        'approved_at' => $now,
                    ]
                );
            }

            foreach ($manifest['affineRules'] ?? [] as $rule) {
                $from = $units[$rule['from']] ?? null;
                $to = $units[$rule['to']] ?? null;
                if (! $from || ! $to) {
                    continue;
                }

                ConversionRule::query()->updateOrCreate(
                    [
                        'tenant_key' => $tenant,
                        'from_unit_id' => $from->id,
                        'to_unit_id' => $to->id,
                        'version_no' => 1,
                    ],
                    [
                        'public_id' => $this->stableUlid('RULE', $rule['from'].'_'.$rule['to']),
                        'rule_type' => ConversionRuleType::AFFINE->value,
                        'scale_decimal' => (string) $rule['scale'],
                        'offset_decimal' => (string) ($rule['offset'] ?? '0'),
                        'is_bidirectional' => (bool) ($rule['bidirectional'] ?? false),
                        'calculation_scale' => 18,
                        'display_precision' => 2,
                        'rounding_mode' => 'HALF_UP',
                        'status' => UnitStatus::ACTIVE->value,
                        'effective_from' => $now,
                        'approved_at' => $now,
                    ]
                );
            }

            foreach ($manifest['aliases'] ?? [] as $code => $aliases) {
                $unit = $units[$code] ?? null;
                if (! $unit) {
                    continue;
                }

                foreach ($aliases as $alias) {
                    UnitAlias::query()->updateOrCreate(
                        [
                            'tenant_key' => $tenant,
                            'alias' => strtolower($alias),
                            'locale' => 'en',
                            'scope_module' => 'INVENTORY',
                        ],
                        [
                            'public_id' => $this->stableUlid('ALIAS', $code.'_'.strtolower($alias)),
                            'unit_id' => $unit->id,
                            'is_preferred' => false,
                            'status' => UnitStatus::ACTIVE->value,
                        ]
                    );
                }
            }
        });
    }

    /**
     * Deterministic ULID-like public IDs so re-seeding does not churn identities.
     * Format: 01SEED + 20 hex chars from sha1 (26 chars total, ULID length).
     */
    protected function stableUlid(string $namespace, string $code): string
    {
        $hex = substr(hash('sha1', 'KASHTRE_CORE_UNIT|'.$namespace.'|'.$code), 0, 20);

        return '01SEED'.strtoupper($hex);
    }
}
