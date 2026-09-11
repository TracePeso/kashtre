<?php

namespace App\Domain\Units\Services;

use App\Domain\Units\Enums\ConversionRuleType;
use App\Domain\Units\Enums\UnitStatus;
use App\Domain\Units\Exceptions\ConversionException;
use App\Domain\Units\Models\ConversionRule;
use App\Domain\Units\Models\ConversionRuleContext;
use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\Models\LegacyUnitMapping;
use App\Domain\Units\Models\UnitAlias;
use App\Domain\Units\ValueObjects\ConversionContext;
use App\Domain\Units\ValueObjects\ConversionResult;
use App\Domain\Units\ValueObjects\Quantity;
use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inventory dual-run adapter: packaging conversion via Shared Unit Engine when enabled,
 * otherwise falls back to item.suom_per_ouom.
 */
final class InventoryUnitGateway
{
    public function __construct(
        private readonly ConversionEngine $engine,
        private readonly UnitCatalogService $catalog,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('units.enabled', false);
    }

    public function tenantKey(int $businessId): string
    {
        return $this->catalog->tenantKeyForBusiness($businessId);
    }

    /**
     * Convert order/packaging quantity → sale units (SUOM).
     *
     * @param  string|float|int|null  $factorOverride  Receipt-level factor (GRN). Falls back to item.suom_per_ouom.
     * @return array{quantity: string, source: string, result?: ConversionResult}
     */
    public function orderToSale(Item $item, string|float|int $orderQty, string|float|int|null $factorOverride = null): array
    {
        $qty = $this->decimalString($orderQty);
        $factor = $factorOverride !== null && (float) $factorOverride > 0
            ? (float) $factorOverride
            : (float) ($item->suom_per_ouom ?? 0);

        if (! $this->enabled()) {
            $sale = $factor > 0 ? (string) round(((float) $qty) * $factor, 4) : $qty;

            return ['quantity' => $sale, 'source' => 'legacy_suom_per_ouom'];
        }

        $this->ensureItemUnitLinks($item);

        if (! $item->order_unit_public_id || ! $item->sale_unit_public_id) {
            if (config('units.strict')) {
                throw ConversionException::contextRequired('ITEM_UNIT_LINKS');
            }

            $sale = $factor > 0 ? (string) round(((float) $qty) * $factor, 4) : $qty;

            return ['quantity' => $sale, 'source' => 'legacy_fallback_unmapped'];
        }

        if ($item->order_unit_public_id === $item->sale_unit_public_id) {
            return ['quantity' => $qty, 'source' => 'identity'];
        }

        if ($factor <= 0) {
            if (config('units.strict')) {
                throw ConversionException::ruleMissing($item->order_unit_public_id, $item->sale_unit_public_id);
            }

            return ['quantity' => $qty, 'source' => 'legacy_fallback_no_factor'];
        }

        try {
            $this->ensurePackagingRule($item, $factor);
            $result = $this->engine->convert(
                new Quantity($qty, $item->order_unit_public_id),
                $item->sale_unit_public_id,
                new ConversionContext(
                    tenantKey: $this->tenantKey((int) $item->business_id),
                    moduleCode: 'INVENTORY',
                    itemPublicId: (string) ($item->uuid ?: $item->id),
                )
            );

            return [
                'quantity' => $result->targetValue,
                'source' => 'unit_engine',
                'result' => $result,
            ];
        } catch (ConversionException $e) {
            if (config('units.strict')) {
                throw $e;
            }

            return [
                'quantity' => (string) round(((float) $qty) * $factor, 4),
                'source' => 'legacy_fallback_'.$e->errorCode,
            ];
        }
    }

    /**
     * Convert sale units (SUOM) → order/packaging quantity (OUOM).
     *
     * @param  string|float|int|null  $factorOverride  Falls back to item.suom_per_ouom.
     * @return array{quantity: string, source: string, result?: ConversionResult}
     */
    public function saleToOrder(Item $item, string|float|int $saleQty, string|float|int|null $factorOverride = null): array
    {
        $qty = $this->decimalString($saleQty);
        $factor = $factorOverride !== null && (float) $factorOverride > 0
            ? (float) $factorOverride
            : (float) ($item->suom_per_ouom ?? 0);

        if (! $this->enabled()) {
            if ($factor <= 0) {
                return ['quantity' => $qty, 'source' => 'legacy_no_factor'];
            }

            return [
                'quantity' => (string) round(((float) $qty) / $factor, 4),
                'source' => 'legacy_suom_per_ouom',
            ];
        }

        $this->ensureItemUnitLinks($item);

        if (! $item->order_unit_public_id || ! $item->sale_unit_public_id) {
            if (config('units.strict')) {
                throw ConversionException::contextRequired('ITEM_UNIT_LINKS');
            }

            if ($factor <= 0) {
                return ['quantity' => $qty, 'source' => 'legacy_fallback_unmapped'];
            }

            return [
                'quantity' => (string) round(((float) $qty) / $factor, 4),
                'source' => 'legacy_fallback_unmapped',
            ];
        }

        if ($item->order_unit_public_id === $item->sale_unit_public_id) {
            return ['quantity' => $qty, 'source' => 'identity'];
        }

        if ($factor <= 0) {
            if (config('units.strict')) {
                throw ConversionException::ruleMissing($item->sale_unit_public_id, $item->order_unit_public_id);
            }

            return ['quantity' => $qty, 'source' => 'legacy_fallback_no_factor'];
        }

        try {
            $this->ensurePackagingRule($item, $factor);
            $result = $this->engine->convert(
                new Quantity($qty, $item->sale_unit_public_id),
                $item->order_unit_public_id,
                new ConversionContext(
                    tenantKey: $this->tenantKey((int) $item->business_id),
                    moduleCode: 'INVENTORY',
                    itemPublicId: (string) ($item->uuid ?: $item->id),
                )
            );

            return [
                'quantity' => $result->targetValue,
                'source' => 'unit_engine',
                'result' => $result,
            ];
        } catch (ConversionException $e) {
            if (config('units.strict')) {
                throw $e;
            }

            return [
                'quantity' => (string) round(((float) $qty) / $factor, 4),
                'source' => 'legacy_fallback_'.$e->errorCode,
            ];
        }
    }

    /**
     * After item create/update: map unit public IDs and refresh packaging rule.
     */
    public function syncItem(Item $item): void
    {
        if (! $this->enabled() || $item->type !== 'good') {
            return;
        }

        $this->ensureItemUnitLinks($item->fresh(['itemUnit', 'orderUnit']));
        $item->refresh();
        $this->ensurePackagingRule($item);
    }

    public function ensureItemUnitLinks(Item $item): void
    {
        $tenant = $this->tenantKey((int) $item->business_id);
        $dirty = false;

        if ($item->uom_id) {
            $sale = ItemUnit::query()->find($item->uom_id);
            if ($sale) {
                $mapped = $this->mapLegacyName($tenant, $sale->name);
                if ($mapped && $item->sale_unit_public_id !== $mapped->public_id) {
                    $item->sale_unit_public_id = $mapped->public_id;
                    $dirty = true;
                }
            }
        }

        $orderUnitId = $item->order_unit_id ?: $item->uom_id;
        if ($orderUnitId) {
            $order = ItemUnit::query()->find($orderUnitId);
            if ($order) {
                $mapped = $this->mapLegacyName($tenant, $order->name);
                if ($mapped && $item->order_unit_public_id !== $mapped->public_id) {
                    $item->order_unit_public_id = $mapped->public_id;
                    $dirty = true;
                }
            }
        }

        if ($dirty) {
            $item->save();
        }
    }

    public function mapLegacyName(string $tenantKey, string $name): ?CoreUnit
    {
        $normalized = strtolower(trim($name));
        if ($normalized === '') {
            return null;
        }

        $existing = LegacyUnitMapping::query()
            ->with('unit')
            ->where('tenant_key', $tenantKey)
            ->where('source_module', 'INVENTORY')
            ->where('source_table', 'item_units')
            ->where('source_value', $normalized)
            ->where('status', 'MAPPED')
            ->first();

        if ($existing?->unit) {
            return $existing->unit;
        }

        $system = (string) config('units.system_tenant_key', 'SYSTEM');

        $alias = UnitAlias::query()
            ->whereIn('tenant_key', [$tenantKey, $system])
            ->where('alias', $normalized)
            ->where('status', UnitStatus::ACTIVE->value)
            ->with('unit')
            ->first();

        $unit = $alias?->unit
            ?? CoreUnit::query()
                ->forTenant($tenantKey)
                ->active()
                ->where(function ($q) use ($normalized) {
                    $q->whereRaw('LOWER(symbol) = ?', [$normalized])
                        ->orWhereRaw('LOWER(canonical_name) = ?', [$normalized])
                        ->orWhereRaw('LOWER(code) = ?', [strtoupper($normalized)]);
                })
                ->first();

        LegacyUnitMapping::query()->updateOrCreate(
            [
                'tenant_key' => $tenantKey,
                'source_module' => 'INVENTORY',
                'source_table' => 'item_units',
                'source_value' => $normalized,
            ],
            [
                'unit_id' => $unit?->id,
                'match_method' => $unit ? ($alias ? 'ALIAS' : 'EXACT') : 'UNMATCHED',
                'status' => $unit ? 'MAPPED' : 'PENDING',
            ]
        );

        return $unit;
    }

    public function assignMapping(LegacyUnitMapping $mapping, CoreUnit $unit, ?int $reviewedBy = null): void
    {
        $mapping->update([
            'unit_id' => $unit->id,
            'match_method' => 'MANUAL',
            'status' => 'MAPPED',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
    }

    public function ignoreMapping(LegacyUnitMapping $mapping, ?int $reviewedBy = null): void
    {
        $mapping->update([
            'status' => 'UNMATCHED',
            'match_method' => 'IGNORED',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
    }

    public function updatePackagingFactor(ConversionRule $rule, string|float|int $factor): void
    {
        $factorDecimal = $this->decimalString($factor);
        if ((float) $factorDecimal <= 0) {
            throw new \InvalidArgumentException('Packaging factor must be positive.');
        }

        $rule->loadMissing('contexts');
        $rule->update(['scale_decimal' => $factorDecimal]);

        foreach ($rule->contexts as $ctx) {
            $params = $ctx->parameters ?? [];
            $params['factor_decimal'] = $factorDecimal;
            $params['source'] = 'manual_console';
            $ctx->update(['parameters' => $params]);

            if ($ctx->context_type === 'ITEM' && $ctx->context_public_id) {
                $item = Item::query()
                    ->where(function ($q) use ($ctx) {
                        $q->where('uuid', $ctx->context_public_id);
                        if (ctype_digit((string) $ctx->context_public_id)) {
                            $q->orWhere('id', (int) $ctx->context_public_id);
                        }
                    })
                    ->first();

                if ($item) {
                    $item->forceFill(['suom_per_ouom' => $factorDecimal])->save();
                }
            }
        }
    }

    /**
     * Effective sale-units-per-order-unit factor (rule scale when linked, else item field).
     */
    public function packagingFactor(Item $item): float
    {
        if ($item->packaging_rule_public_id) {
            $rule = ConversionRule::query()
                ->where('public_id', $item->packaging_rule_public_id)
                ->where('status', UnitStatus::ACTIVE->value)
                ->first();
            if ($rule && (float) $rule->scale_decimal > 0) {
                return (float) $rule->scale_decimal;
            }
        }

        return (float) ($item->suom_per_ouom ?? 0);
    }

    /**
     * Ensure a PRODUCT_SPECIFIC packaging rule exists for this item (order → sale).
     */
    public function ensurePackagingRule(Item $item, string|float|int|null $factorOverride = null): ?ConversionRule
    {
        if (! $item->order_unit_public_id || ! $item->sale_unit_public_id) {
            return null;
        }

        if ($item->order_unit_public_id === $item->sale_unit_public_id) {
            return null;
        }

        $tenant = $this->tenantKey((int) $item->business_id);
        $from = $this->catalog->findByPublicId($tenant, $item->order_unit_public_id);
        $to = $this->catalog->findByPublicId($tenant, $item->sale_unit_public_id);
        $factor = $factorOverride !== null && (float) $factorOverride > 0
            ? (float) $factorOverride
            : (float) ($item->suom_per_ouom ?? 0);

        if (! $from || ! $to || $factor <= 0) {
            return null;
        }

        $itemKey = (string) ($item->uuid ?: $item->id);
        $factorDecimal = $this->decimalString($factor);

        return DB::transaction(function () use ($tenant, $from, $to, $factorDecimal, $item, $itemKey) {
            $rule = ConversionRule::query()
                ->where('tenant_key', $tenant)
                ->where('from_unit_id', $from->id)
                ->where('to_unit_id', $to->id)
                ->where('named_algorithm', 'ITEM_PACKAGE_CHAIN')
                ->whereHas('contexts', function ($q) use ($itemKey) {
                    $q->where('context_type', 'ITEM')->where('context_public_id', $itemKey);
                })
                ->orderByDesc('version_no')
                ->first();

            if (! $rule) {
                $rule = ConversionRule::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'tenant_key' => $tenant,
                    'from_unit_id' => $from->id,
                    'to_unit_id' => $to->id,
                    'rule_type' => ConversionRuleType::PRODUCT_SPECIFIC->value,
                    'scale_decimal' => $factorDecimal,
                    'named_algorithm' => 'ITEM_PACKAGE_CHAIN',
                    'is_bidirectional' => true,
                    'calculation_scale' => 18,
                    'display_precision' => 4,
                    'rounding_mode' => 'HALF_UP',
                    'version_no' => 1,
                    'status' => UnitStatus::ACTIVE->value,
                    'effective_from' => now(),
                    'approved_at' => now(),
                ]);

                ConversionRuleContext::query()->create([
                    'conversion_rule_id' => $rule->id,
                    'context_type' => 'ITEM',
                    'context_public_id' => $itemKey,
                    'parameters' => [
                        'factor_decimal' => $factorDecimal,
                        'source' => 'suom_per_ouom',
                    ],
                ]);
            } elseif ((string) $rule->scale_decimal !== $factorDecimal) {
                $rule->update(['scale_decimal' => $factorDecimal]);
                $ctx = $rule->contexts()
                    ->where('context_type', 'ITEM')
                    ->where('context_public_id', $itemKey)
                    ->first();
                if ($ctx) {
                    $ctx->update([
                        'parameters' => [
                            'factor_decimal' => $factorDecimal,
                            'source' => 'suom_per_ouom',
                        ],
                    ]);
                }
            }

            if ($item->packaging_rule_public_id !== $rule->public_id) {
                $item->forceFill(['packaging_rule_public_id' => $rule->public_id])->save();
            }

            return $rule->load('contexts');
        });
    }

    protected function decimalString(string|float|int $value): string
    {
        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            return trim($value);
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
