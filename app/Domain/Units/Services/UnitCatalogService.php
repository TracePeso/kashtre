<?php

namespace App\Domain\Units\Services;

use App\Domain\Units\Enums\UnitStatus;
use App\Domain\Units\Exceptions\ConversionException;
use App\Domain\Units\Models\CoreUnit;
use DateTimeImmutable;

final class UnitCatalogService
{
    public function tenantKeyForBusiness(?int $businessId): string
    {
        if (! $businessId) {
            return (string) config('units.system_tenant_key', 'SYSTEM');
        }

        return (string) $businessId;
    }

    public function findByPublicId(string $tenantKey, string $publicId): ?CoreUnit
    {
        return CoreUnit::query()
            ->forTenant($tenantKey)
            ->where('public_id', $publicId)
            ->first();
    }

    public function findByCode(string $tenantKey, string $code): ?CoreUnit
    {
        return CoreUnit::query()
            ->forTenant($tenantKey)
            ->where('code', strtoupper($code))
            ->orderByRaw("CASE WHEN tenant_key = ? THEN 0 ELSE 1 END", [$tenantKey])
            ->first();
    }

    public function resolvable(string $tenantKey, string $publicId, ?DateTimeImmutable $at = null): CoreUnit
    {
        $unit = $this->findByPublicId($tenantKey, $publicId);
        if (! $unit) {
            throw ConversionException::unknownUnit($publicId);
        }

        if ($unit->status !== UnitStatus::ACTIVE->value && $unit->status !== UnitStatus::DEPRECATED->value) {
            throw ConversionException::inactiveUnit($publicId);
        }

        return $unit;
    }

    /**
     * @return list<CoreUnit>
     */
    public function search(string $tenantKey, ?string $q = null, int $limit = 50): array
    {
        $query = CoreUnit::query()
            ->forTenant($tenantKey)
            ->active()
            ->orderBy('canonical_name')
            ->limit($limit);

        if ($q) {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('canonical_name', 'like', $like)
                    ->orWhere('symbol', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('ucum_code', 'like', $like);
            });
        }

        return $query->get()->all();
    }

    /**
     * Create a tenant-scoped DRAFT packaging/count unit (not SYSTEM).
     */
    public function createTenantDraftUnit(
        string $tenantKey,
        string $code,
        string $name,
        string $symbol,
        string $unitClass = 'PACKAGING_CONTEXTUAL',
        string $quantityKindCode = 'COUNT',
    ): CoreUnit {
        $system = (string) config('units.system_tenant_key', 'SYSTEM');
        $kind = \App\Domain\Units\Models\QuantityKind::query()
            ->whereIn('tenant_key', [$tenantKey, $system])
            ->where('code', strtoupper($quantityKindCode))
            ->first();

        if (! $kind) {
            throw new \InvalidArgumentException('Quantity kind not found: '.$quantityKindCode);
        }

        $unit = CoreUnit::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'tenant_key' => $tenantKey,
            'code' => strtoupper($code),
            'unit_class' => $unitClass,
            'canonical_name' => $name,
            'symbol' => $symbol,
            'ascii_symbol' => $symbol,
            'standard_verification_status' => 'LOCAL',
            'quantity_kind_id' => $kind->id,
            'dimension_vector' => $kind->dimension_vector ?? ['COUNT' => 1],
            'allows_prefix' => false,
            'allows_composition' => true,
            'is_system' => false,
            'status' => UnitStatus::DRAFT->value,
        ]);

        \App\Domain\Units\Models\UnitVersion::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'unit_id' => $unit->id,
            'version_no' => 1,
            'scale_decimal' => '1',
            'offset_decimal' => '0',
            'calculation_scale' => 18,
            'display_precision' => 4,
            'rounding_mode' => 'HALF_UP',
            'effective_from' => now(),
            'status' => UnitStatus::DRAFT->value,
        ]);

        return $unit;
    }

    public function activateTenantUnit(CoreUnit $unit): void
    {
        if ($unit->is_system) {
            throw new \InvalidArgumentException('SYSTEM units cannot be changed here.');
        }

        $unit->update(['status' => UnitStatus::ACTIVE->value]);
        $unit->versions()->where('version_no', 1)->update([
            'status' => UnitStatus::ACTIVE->value,
            'approved_at' => now(),
        ]);
    }
}
