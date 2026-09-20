<?php

namespace App\Support;

use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\Models\LegacyUnitMapping;
use App\Domain\Units\Services\InventoryUnitGateway;
use App\Domain\Units\Services\UnitCatalogService;
use App\Models\ItemUnit;
use Database\Seeders\Units\CoreUnitSeedPackSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Product accessors for the shared unit catalog.
 * Inventory still stores item_units ids on items; this keeps those rows in sync with the catalog.
 */
final class SharedUnits
{
    /** Quantity kinds that belong on item sale/order unit pickers. */
    public const INVENTORY_QUANTITY_KINDS = ['MASS', 'VOLUME', 'COUNT'];

    public static function enabled(): bool
    {
        return (bool) config('units.enabled', false);
    }

    public static function catalog(): UnitCatalogService
    {
        return app(UnitCatalogService::class);
    }

    public static function gateway(): InventoryUnitGateway
    {
        return app(InventoryUnitGateway::class);
    }

    public static function ensureSeedPack(): void
    {
        if (! Schema::hasTable('core_units')) {
            return;
        }

        if (CoreUnit::query()->where('tenant_key', config('units.system_tenant_key', 'SYSTEM'))->exists()) {
            return;
        }

        (new CoreUnitSeedPackSeeder())->run();
    }

    public static function catalogLabel(CoreUnit $unit): string
    {
        $name = $unit->canonical_name !== '' ? ucfirst($unit->canonical_name) : $unit->code;

        return $unit->symbol ? $name.' ('.$unit->symbol.')' : $name;
    }

    public static function itemUnitLabel(ItemUnit $unit): string
    {
        $name = ucfirst((string) $unit->name);
        $mapped = self::mappedCoreUnit($unit);
        if ($mapped?->symbol) {
            return $name.' ('.$mapped->symbol.')';
        }

        return $name;
    }

    public static function mappedCoreUnit(ItemUnit $unit): ?CoreUnit
    {
        if (! Schema::hasTable('core_legacy_unit_mappings')) {
            return null;
        }

        $tenant = self::catalog()->tenantKeyForBusiness((int) $unit->business_id);

        return LegacyUnitMapping::query()
            ->with('unit')
            ->where('tenant_key', $tenant)
            ->where('source_module', 'INVENTORY')
            ->where('source_table', 'item_units')
            ->where('source_value', strtolower(trim((string) $unit->name)))
            ->where('status', 'MAPPED')
            ->first()
            ?->unit;
    }

    /**
     * Item-form / bulk-upload options: catalog units this business can pick, as ItemUnit rows.
     *
     * @return Collection<int, ItemUnit>
     */
    public static function itemUnitsForBusiness(int $businessId): Collection
    {
        if ($businessId < 1) {
            return collect();
        }

        if (self::enabled()) {
            self::ensureSeedPack();
            foreach (self::inventoryCatalogUnits($businessId) as $unit) {
                self::adoptCatalogUnit($businessId, $unit);
            }
        }

        return ItemUnit::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, CoreUnit>
     */
    public static function inventoryCatalogUnits(int $businessId): Collection
    {
        if (! Schema::hasTable('core_units')) {
            return collect();
        }

        self::ensureSeedPack();
        $tenant = self::catalog()->tenantKeyForBusiness($businessId);

        return CoreUnit::query()
            ->with('quantityKind')
            ->forTenant($tenant)
            ->active()
            ->whereHas('quantityKind', function ($query) {
                $query->whereIn('code', self::INVENTORY_QUANTITY_KINDS);
            })
            ->orderBy('canonical_name')
            ->get();
    }

    /**
     * @return array<string, string> public_id => label
     */
    public static function unusedCatalogOptions(int $businessId): array
    {
        $adopted = ItemUnit::query()
            ->where('business_id', $businessId)
            ->pluck('name')
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->all();

        return self::inventoryCatalogUnits($businessId)
            ->filter(fn (CoreUnit $unit) => ! in_array(strtolower($unit->canonical_name), $adopted, true))
            ->mapWithKeys(fn (CoreUnit $unit) => [$unit->public_id => self::catalogLabel($unit)])
            ->all();
    }

    public static function adoptCatalogUnit(int $businessId, CoreUnit $unit): ItemUnit
    {
        $name = trim($unit->canonical_name);
        $existing = ItemUnit::withTrashed()
            ->where('business_id', $businessId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            self::linkItemUnit($existing, $unit);

            return $existing;
        }

        $itemUnit = ItemUnit::query()->create([
            'business_id' => $businessId,
            'name' => $name,
            'description' => $unit->is_system ? 'Shared catalog' : 'Local packaging unit',
        ]);

        self::linkItemUnit($itemUnit, $unit);

        return $itemUnit;
    }

    public static function addLocalPackagingUnit(
        int $businessId,
        string $name,
        string $symbol,
        ?string $code = null,
        ?string $description = null,
    ): ItemUnit {
        self::ensureSeedPack();
        $name = trim($name);
        $symbol = trim($symbol) !== '' ? trim($symbol) : $name;
        $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($code ?: $name)) ?: 'UNIT');
        $tenant = self::catalog()->tenantKeyForBusiness($businessId);

        $duplicate = ItemUnit::query()
            ->where('business_id', $businessId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'This business already has a unit named "'.$name.'". Pick it on items instead.',
            ]);
        }

        try {
            $core = self::catalog()->createTenantActiveUnit(
                $tenant,
                $code,
                $name,
                $symbol,
                'PACKAGING_CONTEXTUAL',
                'COUNT',
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        $itemUnit = ItemUnit::query()->create([
            'business_id' => $businessId,
            'name' => $name,
            'description' => $description ?: 'Local packaging unit',
        ]);

        self::linkItemUnit($itemUnit, $core);

        return $itemUnit;
    }

    public static function sourceLabel(ItemUnit $unit): string
    {
        $core = self::mappedCoreUnit($unit);
        if (! $core) {
            return 'Custom name';
        }

        if ($core->is_system || $core->tenant_key === config('units.system_tenant_key', 'SYSTEM')) {
            return 'Shared catalog';
        }

        return 'Local';
    }

    private static function linkItemUnit(ItemUnit $itemUnit, CoreUnit $unit): void
    {
        if (! self::enabled()) {
            return;
        }

        $gateway = self::gateway();
        $tenant = self::catalog()->tenantKeyForBusiness((int) $itemUnit->business_id);
        $gateway->mapLegacyName($tenant, (string) $itemUnit->name);

        $mapping = LegacyUnitMapping::query()
            ->where('tenant_key', $tenant)
            ->where('source_module', 'INVENTORY')
            ->where('source_table', 'item_units')
            ->where('source_value', strtolower(trim((string) $itemUnit->name)))
            ->first();

        if ($mapping && ($mapping->status !== 'MAPPED' || (int) $mapping->unit_id !== (int) $unit->id)) {
            $gateway->assignMapping($mapping, $unit);
        }
    }
}
