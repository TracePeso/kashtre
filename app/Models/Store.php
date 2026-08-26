<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Store extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** Customer-facing POS or dispensing pharmacy. */
    public const DISTRIBUTION_END = 'end_store';

    /** Warehouse / interim distribution point. */
    public const DISTRIBUTION_INTERIM = 'interim_distribution';

    /** Floor-stock / ward / crash-cart node under an End Store. */
    public const DISTRIBUTION_SATELLITE = 'satellite_store';

    /** Main → Distribution → End → Satellite */
    public const MAX_HIERARCHY_DEPTH = 3;

    public const CRASH_CART_READY = 'ready';

    public const CRASH_CART_DEPLOYED = 'deployed';

    public const CRASH_CART_RECONCILING = 'reconciling';

    /** Floor-stock satellite under an End Store (default). */
    public const SATELLITE_ROLE_NORMAL = 'normal';

    /** Emergency crash-cart satellite under an End Store. */
    public const SATELLITE_ROLE_CRASH_CART = 'crash_cart';

    protected $fillable = [
        'uuid',
        'business_id',
        'branch_id',
        'parent_id',
        'name',
        'description',
        'location_layer_labels',
        'reorder_level_days',
        'max_stock_days',
        'distribution_type',
        'default_fulfillment_strategy',
        'supports_approved_pool',
        'satellite_role',
        'is_crash_cart',
        'crash_cart_status',
        'crash_cart_seal_number',
        'crash_cart_sealed_at',
        'crash_cart_deployed_at',
        'crash_cart_last_replenishment_order_id',
    ];

    protected $casts = [
        'is_crash_cart' => 'boolean',
        'supports_approved_pool' => 'boolean',
        'crash_cart_sealed_at' => 'datetime',
        'crash_cart_deployed_at' => 'datetime',
        'location_layer_labels' => 'array',
        'reorder_level_days' => 'decimal:2',
        'max_stock_days' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            if (empty($store->uuid)) {
                $store->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (Store $store) {
            $store->applyParentHierarchy();
            $store->enforceDistributionTypeRules();
            $store->normalizeCrashCartFields();
        });

        static::saved(function (Store $store) {
            // Only End Stores under a parent force that parent to become a Distribution Store.
            // Satellite children must not promote an End Store into a Distribution Store.
            if ($store->parent_id && $store->isEndStore()) {
                static::promoteToDistributionStore((int) $store->parent_id);
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Store::class, 'parent_id');
    }

    public function isChild(): bool
    {
        return $this->parent_id !== null;
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * @return array{layer_3: string, layer_2: string, layer_1: string}
     */
    public function locationLayerLabels(): array
    {
        $defaults = self::defaultLocationLabels($this->distribution_type);
        $custom = is_array($this->location_layer_labels) ? $this->location_layer_labels : [];

        return [
            'layer_3' => (string) ($custom['layer_3'] ?? $defaults['layer_3']),
            'layer_2' => (string) ($custom['layer_2'] ?? $defaults['layer_2']),
            'layer_1' => (string) ($custom['layer_1'] ?? $defaults['layer_1']),
        ];
    }

    /**
     * @return array{layer_3: string, layer_2: string, layer_1: string}
     */
    public static function defaultLocationLabels(?string $distributionType): array
    {
        if ($distributionType === self::DISTRIBUTION_END || $distributionType === self::DISTRIBUTION_SATELLITE) {
            return ['layer_3' => 'Wall', 'layer_2' => 'Cabinet', 'layer_1' => 'Bin'];
        }

        return ['layer_3' => 'Aisle', 'layer_2' => 'Rack', 'layer_1' => 'Pallet'];
    }

    /** @deprecated Use isRoot() */
    public function isParent(): bool
    {
        return $this->isRoot();
    }

    public function hasChildren(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }

        return $this->children()->exists();
    }

    public function canAcceptChildStores(): bool
    {
        if ($this->isSatelliteStore()) {
            return false;
        }

        return $this->depth() < self::MAX_HIERARCHY_DEPTH;
    }

    public function canAcceptEndStoreChildren(): bool
    {
        return $this->isInterimDistributionStore()
            && $this->canAcceptChildStores();
    }

    public function canAcceptSatelliteChildren(): bool
    {
        return $this->isEndStore()
            && $this->canAcceptChildStores();
    }

    public function canTransferStockTo(self $to): bool
    {
        if ((int) $this->id === (int) $to->id) {
            return false;
        }

        if ((int) $this->business_id !== (int) $to->business_id) {
            return false;
        }

        // End Stores cannot transfer directly to other End Stores — move via Distribution (or to own satellites).
        if ($this->isEndStore() && $to->isEndStore()) {
            return false;
        }

        if ((int) $this->parent_id === (int) $to->id) {
            return true;
        }

        if ((int) $to->parent_id === (int) $this->id) {
            return true;
        }

        // Root distribution hubs may transfer between each other.
        if ($this->isRoot() && $to->isRoot()
            && $this->isInterimDistributionStore()
            && $to->isInterimDistributionStore()) {
            return true;
        }

        return false;
    }

    public function transferDenialReason(self $to): ?string
    {
        if ((int) $this->id === (int) $to->id) {
            return 'Dispatch and receiving stores must be different.';
        }

        if ((int) $this->business_id !== (int) $to->business_id) {
            return 'Stores must belong to the same organisation.';
        }

        if ($this->isEndStore() && $to->isEndStore()) {
            return 'End Stores cannot transfer stock directly to other End Stores. Move stock through a Distribution store first (or to a Satellite under the same End Store).';
        }

        if ($this->canTransferStockTo($to)) {
            return null;
        }

        return 'Stock can only move between a store and its parent/child in the hierarchy (or between Distribution hubs).';
    }

    /**
     * @return array<int, int>
     */
    public static function transferDestinationIdsFor(int $fromStoreId, int $businessId): array
    {
        $from = static::query()->forBusiness($businessId)->find($fromStoreId);

        if (! $from) {
            return [];
        }

        return static::query()
            ->forBusiness($businessId)
            ->orderBy('name')
            ->get()
            ->filter(fn (self $to) => $from->canTransferStockTo($to))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public static function promoteToDistributionStore(int $storeId): void
    {
        $store = static::query()->find($storeId);

        if (! $store || $store->distribution_type === self::DISTRIBUTION_INTERIM) {
            return;
        }

        $store->updateQuietly(['distribution_type' => self::DISTRIBUTION_INTERIM]);
    }

    public function depth(): int
    {
        if (! $this->parent_id) {
            return 0;
        }

        $parent = $this->relationLoaded('parent') ? $this->parent : static::query()->find($this->parent_id);

        return $parent ? 1 + $parent->depth() : 0;
    }

    public function hierarchyLabel(): string
    {
        return match ($this->distribution_type) {
            self::DISTRIBUTION_INTERIM => $this->isRoot() ? 'Main store' : 'Distribution store',
            self::DISTRIBUTION_END => 'End store',
            self::DISTRIBUTION_SATELLITE => 'Satellite store',
            default => match ($this->depth()) {
                0 => 'Main store',
                1 => 'Branch store',
                2 => 'Unit store',
                default => 'Store',
            },
        };
    }

    /**
     * @return array<string, string>
     */
    public static function distributionTypeOptions(): array
    {
        return [
            self::DISTRIBUTION_INTERIM => 'Distribution store',
            self::DISTRIBUTION_END => 'End store',
            self::DISTRIBUTION_SATELLITE => 'Satellite store',
        ];
    }

    public function distributionTypeLabel(): string
    {
        return self::distributionTypeOptions()[$this->distribution_type ?? self::DISTRIBUTION_END]
            ?? 'End store';
    }

    public function distributionTypeBadgeColor(): string
    {
        return match ($this->distribution_type) {
            self::DISTRIBUTION_INTERIM => 'warning',
            self::DISTRIBUTION_SATELLITE => 'success',
            default => 'primary',
        };
    }

    public function isEndStore(): bool
    {
        return ($this->distribution_type ?? self::DISTRIBUTION_END) === self::DISTRIBUTION_END;
    }

    public function defaultFulfillmentStrategy(): string
    {
        $strategy = (string) ($this->default_fulfillment_strategy ?? '');

        return in_array($strategy, [
            \App\Support\InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE,
            \App\Support\InventoryFulfillmentStrategy::BATCH_AND_STAGE,
        ], true)
            ? $strategy
            : \App\Support\InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE;
    }

    public function supportsApprovedPool(): bool
    {
        return (bool) ($this->supports_approved_pool ?? true);
    }

    public function isInterimDistributionStore(): bool
    {
        return $this->distribution_type === self::DISTRIBUTION_INTERIM;
    }

    public function isSatelliteStore(): bool
    {
        return $this->distribution_type === self::DISTRIBUTION_SATELLITE;
    }

    public function isCrashCart(): bool
    {
        return $this->isSatelliteStore()
            && $this->satellite_role === self::SATELLITE_ROLE_CRASH_CART;
    }

    /**
     * @return array<string, string>
     */
    public static function satelliteRoleOptions(): array
    {
        return [
            self::SATELLITE_ROLE_NORMAL => 'Normal floor stock',
            self::SATELLITE_ROLE_CRASH_CART => 'Crash cart',
        ];
    }

    public function satelliteRoleLabel(): ?string
    {
        if (! $this->isSatelliteStore()) {
            return null;
        }

        if ($this->isCrashCart()) {
            return 'Satellite · Crash cart';
        }

        return 'Satellite';
    }

    /**
     * Query helpers for crash-cart satellites (role + legacy flag during transition).
     */
    public function scopeCrashCarts(Builder $query): Builder
    {
        return $query->where('distribution_type', self::DISTRIBUTION_SATELLITE)
            ->where(function (Builder $q) {
                $q->where('satellite_role', self::SATELLITE_ROLE_CRASH_CART)
                    ->orWhere(function (Builder $legacy) {
                        $legacy->whereNull('satellite_role')->where('is_crash_cart', true);
                    });
            });
    }

    /**
     * @return array<string, string>
     */
    public static function crashCartStatusOptions(): array
    {
        return [
            self::CRASH_CART_READY => 'Ready',
            self::CRASH_CART_DEPLOYED => 'Deployed',
            self::CRASH_CART_RECONCILING => 'Reconciling',
        ];
    }

    public function crashCartStatusLabel(): ?string
    {
        if (! $this->isCrashCart()) {
            return null;
        }

        return self::crashCartStatusOptions()[$this->crash_cart_status] ?? $this->crash_cart_status;
    }

    public function crashCartStatusBadgeColor(): string
    {
        return match ($this->crash_cart_status) {
            self::CRASH_CART_DEPLOYED => 'danger',
            self::CRASH_CART_RECONCILING => 'warning',
            self::CRASH_CART_READY => 'success',
            default => 'gray',
        };
    }

    public function lastCrashCartReplenishmentOrder(): BelongsTo
    {
        return $this->belongsTo(InventoryOrder::class, 'crash_cart_last_replenishment_order_id');
    }

    /**
     * @var array<int, array<int, int>>
     */
    private static array $descendantIdsCache = [];

    /**
     * @return array<int, int>
     */
    public static function descendantIds(int $storeId): array
    {
        if (isset(self::$descendantIdsCache[$storeId])) {
            return self::$descendantIdsCache[$storeId];
        }

        $ids = [$storeId];

        foreach (static::query()->where('parent_id', $storeId)->pluck('id') as $childId) {
            $ids = array_merge($ids, static::descendantIds((int) $childId));
        }

        return self::$descendantIdsCache[$storeId] = array_values(array_unique($ids));
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    public function networkScopeDescription(): string
    {
        $storeIds = self::descendantIds((int) $this->id);
        $childCount = count($storeIds) - 1;

        if ($childCount === 0) {
            return $this->selectLabel().' only (no linked end stores).';
        }

        return $this->selectLabel().' + '.$childCount.' end store'.($childCount === 1 ? '' : 's');
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSelect(int $businessId): array
    {
        static $cache = [];

        if (isset($cache[$businessId])) {
            return $cache[$businessId];
        }

        return $cache[$businessId] = static::query()
            ->forBusiness($businessId)
            ->select(['id', 'name', 'parent_id', 'business_id'])
            ->with('parent:id,name')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Store $store) => [
                $store->id => $store->selectLabel(),
            ])
            ->all();
    }

    public function selectLabel(): string
    {
        if ($this->isChild() && $this->relationLoaded('parent') && $this->parent) {
            return '↳ ' . $this->name . ' (' . $this->parent->name . ')';
        }

        if ($this->isChild() && $this->parent_id) {
            $parentName = static::query()->whereKey($this->parent_id)->value('name');

            return '↳ ' . $this->name . ($parentName ? ' (' . $parentName . ')' : '');
        }

        return $this->name;
    }

    protected function applyParentHierarchy(): void
    {
        if (! $this->parent_id) {
            return;
        }

        if ((int) $this->parent_id === (int) $this->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A store cannot be its own parent.',
            ]);
        }

        $parent = static::query()->find($this->parent_id);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent store is invalid.',
            ]);
        }

        if ($parent->depth() >= self::MAX_HIERARCHY_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => 'Stores support up to four levels: Main → Distribution → End → Satellite.',
            ]);
        }

        if ($this->business_id && (int) $this->business_id !== (int) $parent->business_id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent store must belong to the same business.',
            ]);
        }

        $this->business_id = $parent->business_id;
        $this->branch_id = $parent->branch_id;

        $ancestorId = $parent->id;
        while ($ancestorId) {
            if ((int) $ancestorId === (int) $this->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Invalid parent store hierarchy.',
                ]);
            }
            $ancestorId = static::query()->whereKey($ancestorId)->value('parent_id');
        }
    }

    protected function enforceDistributionTypeRules(): void
    {
        $type = $this->distribution_type ?? self::DISTRIBUTION_END;

        if ($type === self::DISTRIBUTION_SATELLITE) {
            $alreadySatellite = $this->exists
                && $this->getOriginal('distribution_type') === self::DISTRIBUTION_SATELLITE;

            if (! $alreadySatellite && ! $this->businessAllowsFloorStock()) {
                throw ValidationException::withMessages([
                    'distribution_type' => 'Floor stock management is disabled. Enable it under Inventory settings → Capabilities before creating Satellite stores.',
                ]);
            }

            if (! $this->parent_id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Satellite stores must sit under an End Store.',
                ]);
            }

            $parent = $this->relationLoaded('parent')
                ? $this->parent
                : static::query()->find($this->parent_id);

            if (! $parent || ! $parent->isEndStore()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Satellite stores must be linked under an End Store (pharmacy / dispensary).',
                ]);
            }

            return;
        }

        if ($type === self::DISTRIBUTION_END && $this->parent_id) {
            $parent = $this->relationLoaded('parent')
                ? $this->parent
                : static::query()->find($this->parent_id);

            if ($parent && $parent->isSatelliteStore()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'End stores cannot sit under a Satellite store.',
                ]);
            }

            if ($parent && $parent->isEndStore()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'End stores must sit under a Distribution store, not another End store. Use Create Satellite Store for floor stock under an End Store.',
                ]);
            }
        }

        // Distribution only when this store has End Store (or other distribution) children — not satellites alone.
        if ($this->exists && $this->nonSatelliteChildren()->exists()) {
            $this->distribution_type = self::DISTRIBUTION_INTERIM;
        }
    }

    public function nonSatelliteChildren(): HasMany
    {
        return $this->children()->where('distribution_type', '!=', self::DISTRIBUTION_SATELLITE);
    }

    public function satelliteChildren(): HasMany
    {
        return $this->children()->where('distribution_type', self::DISTRIBUTION_SATELLITE);
    }

    public function endStoreChildren(): HasMany
    {
        return $this->children()->where('distribution_type', self::DISTRIBUTION_END);
    }

    protected function businessAllowsFloorStock(): bool
    {
        if (! $this->business_id) {
            return true;
        }

        $config = InventoryModuleConfig::query()
            ->where('business_id', $this->business_id)
            ->first();

        return $config?->floorStockEnabled() ?? true;
    }

    protected function businessAllowsCrashCart(): bool
    {
        if (! $this->business_id) {
            return false;
        }

        $config = InventoryModuleConfig::query()
            ->where('business_id', $this->business_id)
            ->first();

        return $config?->crashCartEnabled() ?? false;
    }

    protected function normalizeCrashCartFields(): void
    {
        if (! $this->isSatelliteStore()) {
            $this->satellite_role = null;
            $this->is_crash_cart = false;
            $this->crash_cart_status = null;
            $this->crash_cart_seal_number = null;
            $this->crash_cart_sealed_at = null;
            $this->crash_cart_deployed_at = null;
            $this->crash_cart_last_replenishment_order_id = null;

            return;
        }

        // Dual-write transition: accept either satellite_role or legacy is_crash_cart.
        $role = $this->satellite_role;
        if ($role === self::SATELLITE_ROLE_CRASH_CART || $this->is_crash_cart) {
            $role = self::SATELLITE_ROLE_CRASH_CART;
        } elseif ($role === self::SATELLITE_ROLE_NORMAL) {
            $role = self::SATELLITE_ROLE_NORMAL;
        } else {
            // New satellite with neither set → normal floor stock.
            $role = self::SATELLITE_ROLE_NORMAL;
        }

        if ($role === self::SATELLITE_ROLE_CRASH_CART) {
            if (! $this->businessAllowsCrashCart()) {
                throw ValidationException::withMessages([
                    'satellite_role' => 'Crash cart management is disabled. Enable it under Inventory settings → Capabilities.',
                ]);
            }

            $this->satellite_role = self::SATELLITE_ROLE_CRASH_CART;
            $this->is_crash_cart = true;

            if (! in_array($this->crash_cart_status, [
                self::CRASH_CART_READY,
                self::CRASH_CART_DEPLOYED,
                self::CRASH_CART_RECONCILING,
            ], true)) {
                $this->crash_cart_status = self::CRASH_CART_READY;
            }

            return;
        }

        $this->satellite_role = self::SATELLITE_ROLE_NORMAL;
        $this->is_crash_cart = false;
        $this->crash_cart_status = null;
        $this->crash_cart_seal_number = null;
        $this->crash_cart_sealed_at = null;
        $this->crash_cart_deployed_at = null;
        $this->crash_cart_last_replenishment_order_id = null;
    }
}
