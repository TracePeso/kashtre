<?php

namespace App\Domain\Units\Services;

use App\Domain\Units\Contracts\DecimalMath;
use App\Domain\Units\Enums\ComponentOperator;
use App\Domain\Units\Enums\UnitClass;
use App\Domain\Units\Enums\UnitStatus;
use App\Domain\Units\Exceptions\ConversionException;
use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\Models\QuantityKind;
use App\Domain\Units\Models\UnitComponent;
use App\Domain\Units\Models\UnitVersion;
use App\Domain\Units\ValueObjects\DimensionVector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resolves composite unit versions, dimensions, and scale-to-canonical factors.
 */
final class CompositionService
{
    public function __construct(
        private readonly DecimalMath $math,
        private readonly UnitCatalogService $catalog,
    ) {}

    public function activeVersion(CoreUnit $unit): ?UnitVersion
    {
        return UnitVersion::query()
            ->with(['components.componentUnit'])
            ->where('unit_id', $unit->id)
            ->where('status', UnitStatus::ACTIVE->value)
            ->orderByDesc('version_no')
            ->first();
    }

    /**
     * @param  list<array{operator: string, component_unit_public_id: string, exponent?: int}>  $components
     * @return array{dimension: array<string, int>, symbol: string, scale: string}
     */
    public function preview(string $tenantKey, array $components): array
    {
        $max = (int) config('units.max_composite_components', 12);
        if (count($components) === 0 || count($components) > $max) {
            throw new \InvalidArgumentException(
                'Composite must have between 1 and '.$max.' components.'
            );
        }

        $dimension = new DimensionVector([]);
        $symbolParts = ['num' => [], 'den' => []];
        $scale = '1';
        $calc = (int) config('units.calculation_scale', 18);

        foreach ($components as $i => $row) {
            $unit = $this->catalog->findByPublicId($tenantKey, $row['component_unit_public_id']);
            if (! $unit || ! $unit->allows_composition) {
                throw ConversionException::unknownUnit($row['component_unit_public_id'] ?? '');
            }

            $exp = (int) ($row['exponent'] ?? 1);
            $op = ComponentOperator::from($row['operator']);
            $compDim = DimensionVector::from($unit->dimension_vector)->pow($exp);
            $compScale = $this->math->pow($this->canonicalScale($unit), (string) $exp, $calc);

            if ($op === ComponentOperator::DENOMINATOR) {
                $dimension = $dimension->divide($compDim);
                $scale = $this->math->divide($scale, $compScale, $calc);
                $symbolParts['den'][] = $this->formatSymbol($unit->symbol, $exp);
            } else {
                $dimension = $dimension->multiply($compDim);
                $scale = $this->math->multiply($scale, $compScale, $calc);
                $symbolParts['num'][] = $this->formatSymbol($unit->symbol, $exp);
            }
        }

        $num = implode('·', $symbolParts['num']) ?: '1';
        $den = implode('·', $symbolParts['den']);
        $symbol = $den === '' ? $num : $num.'/'.$den;

        return [
            'dimension' => $dimension->normalized(),
            'symbol' => $symbol,
            'scale' => $scale,
        ];
    }

    /**
     * Persist a tenant composite unit + active version + components.
     *
     * @param  list<array{operator: string, component_unit_public_id: string, exponent?: int}>  $components
     */
    public function createComposite(
        string $tenantKey,
        string $code,
        string $name,
        array $components,
        ?string $quantityKindCode = null,
    ): CoreUnit {
        $preview = $this->preview($tenantKey, $components);
        $kind = $this->resolveQuantityKind($tenantKey, $quantityKindCode, $preview['dimension']);

        return DB::transaction(function () use ($tenantKey, $code, $name, $components, $preview, $kind) {
            $unit = CoreUnit::query()->create([
                'public_id' => (string) Str::ulid(),
                'tenant_key' => $tenantKey,
                'code' => strtoupper($code),
                'unit_class' => UnitClass::COMPOSITE_RATIO->value,
                'canonical_name' => $name,
                'symbol' => $preview['symbol'],
                'ascii_symbol' => $preview['symbol'],
                'ucum_code' => null,
                'standard_verification_status' => 'LOCAL',
                'quantity_kind_id' => $kind->id,
                'dimension_vector' => $preview['dimension'],
                'allows_prefix' => false,
                'allows_composition' => true,
                'is_system' => false,
                'status' => UnitStatus::ACTIVE->value,
            ]);

            $version = UnitVersion::query()->create([
                'public_id' => (string) Str::ulid(),
                'unit_id' => $unit->id,
                'version_no' => 1,
                'scale_decimal' => $preview['scale'],
                'offset_decimal' => '0',
                'calculation_scale' => 18,
                'display_precision' => 4,
                'rounding_mode' => 'HALF_UP',
                'effective_from' => now(),
                'status' => UnitStatus::ACTIVE->value,
                'approved_at' => now(),
                'metadata' => ['source' => 'composition_builder'],
            ]);

            foreach ($components as $i => $row) {
                $component = $this->catalog->findByPublicId($tenantKey, $row['component_unit_public_id']);
                UnitComponent::query()->create([
                    'unit_version_id' => $version->id,
                    'sequence' => $i + 1,
                    'operator' => $row['operator'],
                    'component_unit_id' => $component?->id,
                    'exponent' => (int) ($row['exponent'] ?? 1),
                ]);
            }

            return $unit->fresh();
        });
    }

    /**
     * Scale factor that converts 1 of this unit into the catalog's canonical SI-like base
     * for its dimension (e.g. mg → 0.001 g-equivalent mass).
     */
    public function canonicalScale(CoreUnit $unit): string
    {
        $version = $this->activeVersion($unit);
        if (! $version) {
            return '1';
        }

        $calc = (int) ($version->calculation_scale ?: config('units.calculation_scale', 18));

        if ($version->components->isNotEmpty()) {
            $scale = '1';
            foreach ($version->components->sortBy('sequence') as $component) {
                if (! $component->componentUnit) {
                    continue;
                }
                $exp = (int) $component->exponent;
                $part = $this->math->pow($this->canonicalScale($component->componentUnit), (string) $exp, $calc);
                if ($component->operator === ComponentOperator::DENOMINATOR->value) {
                    $scale = $this->math->divide($scale, $part, $calc);
                } else {
                    $scale = $this->math->multiply($scale, $part, $calc);
                }
            }

            return $scale;
        }

        return (string) ($version->scale_decimal ?: '1');
    }

    public function computeDimension(CoreUnit $unit): DimensionVector
    {
        $version = $this->activeVersion($unit);
        if (! $version || $version->components->isEmpty()) {
            return DimensionVector::from($unit->dimension_vector);
        }

        $dimension = new DimensionVector([]);
        foreach ($version->components->sortBy('sequence') as $component) {
            if (! $component->componentUnit) {
                continue;
            }
            $compDim = DimensionVector::from($component->componentUnit->dimension_vector)
                ->pow((int) $component->exponent);
            if ($component->operator === ComponentOperator::DENOMINATOR->value) {
                $dimension = $dimension->divide($compDim);
            } else {
                $dimension = $dimension->multiply($compDim);
            }
        }

        return $dimension;
    }

    /**
     * Convert when dimensions match by canonical scale ratio (no explicit rule required).
     */
    public function convertViaCanonical(
        string $value,
        CoreUnit $from,
        CoreUnit $to,
        int $displayPrecision = 4,
        string $roundingMode = 'HALF_UP',
    ): ?string {
        $fromDim = $this->computeDimension($from);
        $toDim = $this->computeDimension($to);
        if (! $fromDim->equals($toDim)) {
            return null;
        }

        $calc = (int) config('units.calculation_scale', 18);
        $fromScale = $this->canonicalScale($from);
        $toScale = $this->canonicalScale($to);
        if ($this->math->compare($toScale, '0') === 0) {
            return null;
        }

        $raw = $this->math->divide(
            $this->math->multiply($value, $fromScale, $calc),
            $toScale,
            $calc
        );

        return $this->math->round($raw, $displayPrecision, $roundingMode);
    }

    /**
     * @param  array<string, int>  $dimension
     */
    protected function resolveQuantityKind(string $tenantKey, ?string $code, array $dimension): QuantityKind
    {
        $system = (string) config('units.system_tenant_key', 'SYSTEM');

        if ($code) {
            $kind = QuantityKind::query()
                ->whereIn('tenant_key', [$tenantKey, $system])
                ->where('code', strtoupper($code))
                ->first();
            if ($kind) {
                return $kind;
            }
        }

        $existing = QuantityKind::query()
            ->whereIn('tenant_key', [$tenantKey, $system])
            ->get()
            ->first(fn (QuantityKind $k) => DimensionVector::from($k->dimension_vector)->equals(DimensionVector::from($dimension)));

        if ($existing) {
            return $existing;
        }

        return QuantityKind::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_key' => $tenantKey,
            'code' => 'COMPOSITE_'.strtoupper(Str::random(6)),
            'name' => 'Composite quantity',
            'dimension_vector' => $dimension,
            'is_system' => false,
            'status' => UnitStatus::ACTIVE->value,
            'effective_from' => now(),
        ]);
    }

    protected function formatSymbol(string $symbol, int $exp): string
    {
        return $exp === 1 ? $symbol : $symbol.'^'.$exp;
    }
}
