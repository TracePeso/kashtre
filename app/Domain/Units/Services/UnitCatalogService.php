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
        return $this->createUnit(
            tenantKey: $tenantKey,
            code: $code,
            name: $name,
            symbol: $symbol,
            unitClass: $unitClass,
            quantityKindCode: $quantityKindCode,
            isSystem: false,
            status: UnitStatus::DRAFT,
        );
    }

    /**
     * Create an immediately usable tenant packaging/count unit.
     */
    public function createTenantActiveUnit(
        string $tenantKey,
        string $code,
        string $name,
        string $symbol,
        string $unitClass = 'PACKAGING_CONTEXTUAL',
        string $quantityKindCode = 'COUNT',
    ): CoreUnit {
        $unit = $this->createUnit(
            tenantKey: $tenantKey,
            code: $code,
            name: $name,
            symbol: $symbol,
            unitClass: $unitClass,
            quantityKindCode: $quantityKindCode,
            isSystem: false,
            status: UnitStatus::ACTIVE,
        );
        $this->activateTenantUnit($unit);

        return $unit->fresh() ?? $unit;
    }

    /**
     * Kashtre-admin SYSTEM catalog unit (packaging/count). Not renamed after create.
     */
    public function createSystemUnit(
        string $code,
        string $name,
        string $symbol,
        string $unitClass = 'PACKAGING_CONTEXTUAL',
        string $quantityKindCode = 'COUNT',
        ?int $actorId = null,
    ): CoreUnit {
        $system = (string) config('units.system_tenant_key', 'SYSTEM');

        return $this->createUnit(
            tenantKey: $system,
            code: $code,
            name: $name,
            symbol: $symbol,
            unitClass: $unitClass,
            quantityKindCode: $quantityKindCode,
            isSystem: true,
            status: UnitStatus::ACTIVE,
            actorId: $actorId,
        );
    }

    public function activateTenantUnit(CoreUnit $unit): void
    {
        if ($unit->is_system && $unit->status === UnitStatus::ACTIVE->value) {
            return;
        }

        if ($unit->is_system) {
            throw new \InvalidArgumentException('SYSTEM units cannot be changed here.');
        }

        $unit->update(['status' => UnitStatus::ACTIVE->value]);
        $unit->versions()->where('version_no', 1)->update([
            'status' => UnitStatus::ACTIVE->value,
            'approved_at' => now(),
        ]);
    }

    /**
     * Hide a catalog unit from new picks. Existing stock still resolves (DEPRECATED stays convertible).
     */
    public function retireUnit(CoreUnit $unit, ?string $reason = null, ?int $actorId = null): CoreUnit
    {
        if ($unit->status === UnitStatus::DEPRECATED->value || $unit->status === UnitStatus::RETIRED->value) {
            return $unit;
        }

        $before = ['status' => $unit->status];
        $unit->update([
            'status' => UnitStatus::DEPRECATED->value,
            'updated_by' => $actorId,
        ]);
        \App\Domain\Units\Models\UnitVersion::query()
            ->where('unit_id', $unit->id)
            ->where('status', UnitStatus::ACTIVE->value)
            ->update(['status' => UnitStatus::DEPRECATED->value, 'effective_to' => now()]);

        app(UnitGovernanceService::class)->record(
            $unit->tenant_key,
            'UNIT_RETIRED',
            'CoreUnit',
            $unit->public_id,
            $before,
            ['status' => UnitStatus::DEPRECATED->value],
            $reason
        );

        return $unit->fresh() ?? $unit;
    }

    private function createUnit(
        string $tenantKey,
        string $code,
        string $name,
        string $symbol,
        string $unitClass,
        string $quantityKindCode,
        bool $isSystem,
        UnitStatus $status,
        ?int $actorId = null,
    ): CoreUnit {
        $system = (string) config('units.system_tenant_key', 'SYSTEM');
        $code = strtoupper(trim($code));
        $kind = \App\Domain\Units\Models\QuantityKind::query()
            ->whereIn('tenant_key', [$tenantKey, $system])
            ->where('code', strtoupper($quantityKindCode))
            ->first();

        if (! $kind) {
            throw new \InvalidArgumentException('Quantity kind not found: '.$quantityKindCode);
        }

        if (CoreUnit::query()->where('tenant_key', $tenantKey)->where('code', $code)->exists()) {
            throw new \InvalidArgumentException('A unit with code '.$code.' already exists in this catalog.');
        }

        $unit = CoreUnit::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'tenant_key' => $tenantKey,
            'code' => $code,
            'unit_class' => $unitClass,
            'canonical_name' => $name,
            'symbol' => $symbol,
            'ascii_symbol' => $symbol,
            'standard_verification_status' => $isSystem ? 'SEEDED' : 'LOCAL',
            'quantity_kind_id' => $kind->id,
            'dimension_vector' => $kind->dimension_vector ?? ['COUNT' => 1],
            'allows_prefix' => false,
            'allows_composition' => true,
            'is_system' => $isSystem,
            'status' => $status->value,
            'created_by' => $actorId,
            'updated_by' => $actorId,
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
            'status' => $status->value,
            'approved_at' => $status === UnitStatus::ACTIVE ? now() : null,
            'created_by' => $actorId,
        ]);

        return $unit;
    }
}
