<?php

namespace App\Livewire\Inventory;

use App\Domain\Units\Enums\ComponentOperator;
use App\Domain\Units\Enums\ConversionRuleType;
use App\Domain\Units\Models\ConversionRule;
use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\Models\LegacyUnitMapping;
use App\Domain\Units\Models\ModuleUnitPolicy;
use App\Domain\Units\Models\UnitAudit;
use App\Domain\Units\Services\CompositionService;
use App\Domain\Units\Services\InventoryUnitGateway;
use App\Domain\Units\Services\UnitCatalogService;
use App\Domain\Units\Services\UnitGovernanceService;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Support\InventoryBusinessContext;
use Database\Seeders\Units\CoreUnitSeedPackSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Inventory console for Shared Unit Engine.
 */
class UnitEngineConsole extends Component
{
    public string $activeTab = 'catalog';

    public string $catalogQuery = '';

    public string $draftCode = '';

    public string $draftName = '';

    public string $draftSymbol = '';

    public string $draftClass = 'PACKAGING_CONTEXTUAL';

    public ?string $draftMessage = null;

    public ?string $draftError = null;

    public string $mappingFilter = 'ALL';

    public ?int $mapTargetUnitId = null;

    public ?int $editingRuleId = null;

    public string $editingFactor = '';

    public ?string $rulesMessage = null;

    public ?string $rulesError = null;

    public string $compositeCode = '';

    public string $compositeName = '';

    public string $compositeNumeratorPublicId = '';

    public string $compositeDenominatorPublicId = '';

    public ?string $compositePreview = null;

    public ?string $compositeMessage = null;

    public ?string $compositeError = null;

    public string $policyModule = 'CLINICAL';

    public string $policyObjectType = 'ANALYTE';

    public string $policyObjectId = '';

    public string $policyRole = 'REPORTING';

    public ?int $policyUnitId = null;

    public ?string $policyMessage = null;

    public ?string $policyError = null;

    public ?string $govMessage = null;

    public ?string $govError = null;

    public ?string $installMessage = null;

    public ?string $installError = null;

    public function mount(): void
    {
        $catalog = app(UnitCatalogService::class);
        $this->compositeNumeratorPublicId = $catalog->findByCode('SYSTEM', 'MG')?->public_id ?? '';
        $this->compositeDenominatorPublicId = $catalog->findByCode('SYSTEM', 'ML')?->public_id ?? '';
    }

    /**
     * Seed SYSTEM catalog (packaging, count, composites, scale rules) and map this business’s Item Units.
     */
    public function installSeedPack(): void
    {
        $this->installMessage = null;
        $this->installError = null;

        if (! config('units.enabled')) {
            $this->installError = 'Unit engine is disabled. Set UNIT_ENGINE_ENABLED=true first.';

            return;
        }

        try {
            (new CoreUnitSeedPackSeeder())->run();

            $businessId = InventoryBusinessContext::effectiveBusinessId();
            $gateway = app(InventoryUnitGateway::class);
            $mapped = 0;
            $linked = 0;
            $rules = 0;

            ItemUnit::query()
                ->where('business_id', $businessId)
                ->orderBy('id')
                ->each(function (ItemUnit $unit) use ($gateway, &$mapped) {
                    $gateway->mapLegacyName((string) $unit->business_id, (string) $unit->name);
                    $mapped++;
                });

            Item::query()
                ->where('business_id', $businessId)
                ->where('type', 'good')
                ->orderBy('id')
                ->each(function (Item $item) use ($gateway, &$linked, &$rules) {
                    $before = [$item->sale_unit_public_id, $item->order_unit_public_id, $item->packaging_rule_public_id];
                    $gateway->ensureItemUnitLinks($item->fresh());
                    $item->refresh();
                    if ($item->sale_unit_public_id && $item->order_unit_public_id
                        && $item->sale_unit_public_id !== $item->order_unit_public_id
                        && (float) ($item->suom_per_ouom ?? 0) > 0) {
                        $gateway->ensurePackagingRule($item);
                        $item->refresh();
                    }
                    if ($before[0] !== $item->sale_unit_public_id
                        || $before[1] !== $item->order_unit_public_id
                        || $before[2] !== $item->packaging_rule_public_id) {
                        $linked++;
                    }
                    if ($item->packaging_rule_public_id) {
                        $rules++;
                    }
                });

            $catalog = app(UnitCatalogService::class);
            $this->compositeNumeratorPublicId = $catalog->findByCode('SYSTEM', 'MG')?->public_id ?? '';
            $this->compositeDenominatorPublicId = $catalog->findByCode('SYSTEM', 'ML')?->public_id ?? '';

            $unitCount = CoreUnit::query()->forTenant('SYSTEM')->active()->count();
            $this->installMessage = "Seeded {$unitCount} system units. Mapped {$mapped} Item Unit name(s); updated {$linked} item link(s); {$rules} packaging rule(s) present for this business.";

            app(UnitGovernanceService::class)->record(
                $this->tenantKey(),
                'SEED_PACK_INSTALLED',
                'CoreUnitSeedPack',
                'KASHTRE_CORE_UNITS',
                null,
                ['business_id' => $businessId, 'mapped' => $mapped, 'linked' => $linked]
            );
        } catch (\Throwable $e) {
            $this->installError = $e->getMessage();
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['catalog', 'mappings', 'rules', 'composites', 'governance', 'policies', 'audit'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->draftMessage = $this->draftError = null;
        $this->rulesMessage = $this->rulesError = null;
        $this->compositeMessage = $this->compositeError = null;
        $this->policyMessage = $this->policyError = null;
        $this->govMessage = $this->govError = null;
    }

    public function createDraftUnit(): void
    {
        $this->draftMessage = null;
        $this->draftError = null;

        if (! config('units.enabled')) {
            $this->draftError = 'Unit engine is disabled.';

            return;
        }

        $this->validate([
            'draftCode' => ['required', 'string', 'max:64'],
            'draftName' => ['required', 'string', 'max:180'],
            'draftSymbol' => ['required', 'string', 'max:80'],
            'draftClass' => ['required', 'in:PACKAGING_CONTEXTUAL,COUNT_CONTEXTUAL'],
        ]);

        try {
            $catalog = app(UnitCatalogService::class);
            $unit = $catalog->createTenantDraftUnit(
                $this->tenantKey(),
                $this->draftCode,
                $this->draftName,
                $this->draftSymbol,
                $this->draftClass,
            );
            app(UnitGovernanceService::class)->activateUnit($unit, 'Created from Units console');
            $this->draftMessage = 'Created '.$unit->code.' ('.$unit->symbol.').';
            $this->draftCode = $this->draftName = $this->draftSymbol = '';
        } catch (\Throwable $e) {
            $this->draftError = $e->getMessage();
        }
    }

    public function mapLegacy(int $mappingId): void
    {
        $this->validate(['mapTargetUnitId' => ['required', 'integer']]);

        $mapping = LegacyUnitMapping::query()
            ->where('tenant_key', $this->tenantKey())
            ->whereKey($mappingId)
            ->first();
        $unit = CoreUnit::query()->forTenant($this->tenantKey())->whereKey($this->mapTargetUnitId)->first();

        if (! $mapping || ! $unit) {
            return;
        }

        app(InventoryUnitGateway::class)->assignMapping($mapping, $unit, Auth::id());
        app(UnitGovernanceService::class)->record(
            $this->tenantKey(),
            'LEGACY_MAPPING_ASSIGNED',
            'LegacyUnitMapping',
            (string) $mapping->id,
            null,
            ['unit' => $unit->public_id, 'source' => $mapping->source_value]
        );
        $this->mapTargetUnitId = null;
    }

    public function ignoreLegacy(int $mappingId): void
    {
        $mapping = LegacyUnitMapping::query()
            ->where('tenant_key', $this->tenantKey())
            ->whereKey($mappingId)
            ->first();

        if (! $mapping) {
            return;
        }

        app(InventoryUnitGateway::class)->ignoreMapping($mapping, Auth::id());
    }

    public function startEditRule(int $ruleId, string $factor): void
    {
        $this->editingRuleId = $ruleId;
        $this->editingFactor = $factor;
        $this->rulesMessage = null;
        $this->rulesError = null;
    }

    public function saveRuleFactor(): void
    {
        $this->rulesMessage = null;
        $this->rulesError = null;

        $this->validate([
            'editingRuleId' => ['required', 'integer'],
            'editingFactor' => ['required', 'regex:/^\d+(\.\d+)?$/'],
        ]);

        $rule = ConversionRule::query()
            ->with('contexts')
            ->where('tenant_key', $this->tenantKey())
            ->whereKey($this->editingRuleId)
            ->first();

        if (! $rule) {
            $this->rulesError = 'Rule not found.';

            return;
        }

        try {
            app(InventoryUnitGateway::class)->updatePackagingFactor($rule, $this->editingFactor);
            $this->rulesMessage = 'Factor updated (item master synced).';
            $this->editingRuleId = null;
            $this->editingFactor = '';
        } catch (\Throwable $e) {
            $this->rulesError = $e->getMessage();
        }
    }

    public function previewComposite(): void
    {
        $this->compositeError = null;
        $this->compositePreview = null;

        try {
            $preview = app(CompositionService::class)->preview($this->tenantKey(), $this->compositeComponents());
            $this->compositePreview = $preview['symbol'].' · '.json_encode($preview['dimension']);
        } catch (\Throwable $e) {
            $this->compositeError = $e->getMessage();
        }
    }

    public function saveComposite(): void
    {
        $this->compositeMessage = null;
        $this->compositeError = null;

        if (! config('units.enabled')) {
            $this->compositeError = 'Unit engine is disabled.';

            return;
        }

        $this->validate([
            'compositeCode' => ['required', 'string', 'max:64'],
            'compositeName' => ['required', 'string', 'max:180'],
            'compositeNumeratorPublicId' => ['required', 'string'],
            'compositeDenominatorPublicId' => ['required', 'string'],
        ]);

        try {
            $unit = app(CompositionService::class)->createComposite(
                $this->tenantKey(),
                $this->compositeCode,
                $this->compositeName,
                $this->compositeComponents(),
                'CONCENTRATION',
            );
            app(UnitGovernanceService::class)->record(
                $this->tenantKey(),
                'COMPOSITE_CREATED',
                'CoreUnit',
                $unit->public_id,
                null,
                ['code' => $unit->code, 'symbol' => $unit->symbol]
            );
            $this->compositeMessage = 'Created '.$unit->code.' ('.$unit->symbol.')';
            $this->compositeCode = '';
            $this->compositeName = '';
            $this->compositePreview = null;
        } catch (\Throwable $e) {
            $this->compositeError = $e->getMessage();
        }
    }

    public function verifyUnit(string $publicId): void
    {
        $this->govMessage = null;
        $this->govError = null;

        try {
            $unit = CoreUnit::query()->forTenant($this->tenantKey())->where('public_id', $publicId)->firstOrFail();
            app(UnitGovernanceService::class)->markUcumVerified($unit, 'Verified from Units console');
            $this->govMessage = 'Marked '.$unit->code.' as UCUM verified.';
        } catch (\Throwable $e) {
            $this->govError = $e->getMessage();
        }
    }

    public function deprecateUnit(string $publicId): void
    {
        $this->govMessage = null;
        $this->govError = null;

        try {
            $unit = CoreUnit::query()->forTenant($this->tenantKey())->where('public_id', $publicId)->firstOrFail();
            app(UnitGovernanceService::class)->deprecateUnit($unit, 'Deprecated from Units console');
            $this->govMessage = 'Deprecated '.$unit->code.'.';
        } catch (\Throwable $e) {
            $this->govError = $e->getMessage();
        }
    }

    public function activateUnit(string $publicId): void
    {
        $this->govMessage = null;
        $this->govError = null;

        try {
            $unit = CoreUnit::query()->forTenant($this->tenantKey())->where('public_id', $publicId)->firstOrFail();
            app(UnitGovernanceService::class)->activateUnit($unit, 'Activated from Units console');
            $this->govMessage = 'Activated '.$unit->code.'.';
        } catch (\Throwable $e) {
            $this->govError = $e->getMessage();
        }
    }

    public function savePolicy(): void
    {
        $this->policyMessage = null;
        $this->policyError = null;

        $this->validate([
            'policyModule' => ['required', 'in:CLINICAL,LIMS,INVENTORY'],
            'policyObjectType' => ['required', 'string', 'max:64'],
            'policyObjectId' => ['required', 'string', 'max:64'],
            'policyRole' => ['required', 'string', 'max:24'],
            'policyUnitId' => ['required', 'integer'],
        ]);

        try {
            $unit = CoreUnit::query()->forTenant($this->tenantKey())->whereKey($this->policyUnitId)->firstOrFail();
            $policy = app(UnitGovernanceService::class)->upsertModulePolicy(
                $this->tenantKey(),
                $this->policyModule,
                $this->policyObjectType,
                $this->policyObjectId,
                $unit,
                $this->policyRole,
            );
            $this->policyMessage = 'Policy saved: '.$policy->module_code.' / '.$policy->usage_role.' → '.$unit->code;
            $this->policyObjectId = '';
        } catch (\Throwable $e) {
            $this->policyError = $e->getMessage();
        }
    }

    /**
     * @return list<array{operator: string, component_unit_public_id: string, exponent: int}>
     */
    protected function compositeComponents(): array
    {
        return [
            [
                'operator' => ComponentOperator::NUMERATOR->value,
                'component_unit_public_id' => $this->compositeNumeratorPublicId,
                'exponent' => 1,
            ],
            [
                'operator' => ComponentOperator::DENOMINATOR->value,
                'component_unit_public_id' => $this->compositeDenominatorPublicId,
                'exponent' => 1,
            ],
        ];
    }

    protected function tenantKey(): string
    {
        return app(UnitCatalogService::class)->tenantKeyForBusiness(
            InventoryBusinessContext::effectiveBusinessId()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalogRows(): array
    {
        $units = app(UnitCatalogService::class)->search(
            $this->tenantKey(),
            $this->catalogQuery !== '' ? $this->catalogQuery : null,
            100
        );

        return collect($units)->map(fn (CoreUnit $u) => [
            'public_id' => $u->public_id,
            'code' => $u->code,
            'name' => $u->canonical_name,
            'symbol' => $u->symbol,
            'class' => $u->unit_class,
            'ucum' => $u->ucum_code,
            'tenant' => $u->tenant_key,
            'status' => $u->status,
            'verification' => $u->standard_verification_status,
            'is_system' => (bool) $u->is_system,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function governanceRows(): array
    {
        return CoreUnit::query()
            ->where('tenant_key', $this->tenantKey())
            ->where('is_system', false)
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn (CoreUnit $u) => [
                'public_id' => $u->public_id,
                'code' => $u->code,
                'name' => $u->canonical_name,
                'symbol' => $u->symbol,
                'status' => $u->status,
                'verification' => $u->standard_verification_status,
                'class' => $u->unit_class,
            ])
            ->all();
    }

    public function catalogStats(): array
    {
        $tenant = $this->tenantKey();
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        return [
            'units' => CoreUnit::query()->forTenant($tenant)->active()->count(),
            'mappings_pending' => LegacyUnitMapping::query()
                ->where('tenant_key', $tenant)->where('source_module', 'INVENTORY')->where('status', 'PENDING')->count(),
            'mappings_mapped' => LegacyUnitMapping::query()
                ->where('tenant_key', $tenant)->where('source_module', 'INVENTORY')->where('status', 'MAPPED')->count(),
            'packaging_rules' => ConversionRule::query()
                ->where('tenant_key', $tenant)
                ->where('rule_type', ConversionRuleType::PRODUCT_SPECIFIC->value)
                ->where('named_algorithm', 'ITEM_PACKAGE_CHAIN')
                ->where('status', 'ACTIVE')
                ->count(),
            'linked_items' => Item::query()
                ->where('business_id', $businessId)
                ->where('type', 'good')
                ->whereNotNull('sale_unit_public_id')
                ->whereNotNull('order_unit_public_id')
                ->count(),
            'strict' => (bool) config('units.strict'),
        ];
    }

    public function mappingRows()
    {
        $query = LegacyUnitMapping::query()
            ->with('unit')
            ->where('tenant_key', $this->tenantKey())
            ->where('source_module', 'INVENTORY')
            ->orderBy('status')
            ->orderBy('source_value')
            ->limit(150);

        if ($this->mappingFilter !== 'ALL') {
            $query->where('status', $this->mappingFilter);
        }

        return $query->get();
    }

    public function coreUnitIdOptions(): array
    {
        return CoreUnit::query()
            ->forTenant($this->tenantKey())
            ->active()
            ->orderBy('canonical_name')
            ->get(['id', 'canonical_name', 'symbol', 'code', 'public_id'])
            ->mapWithKeys(fn (CoreUnit $u) => [
                $u->id => $u->code.' — '.$u->canonical_name.' ('.$u->symbol.')',
            ])
            ->all();
    }

    public function unitPublicIdOptions(): array
    {
        return CoreUnit::query()
            ->forTenant($this->tenantKey())
            ->active()
            ->orderBy('canonical_name')
            ->get(['public_id', 'canonical_name', 'symbol', 'code'])
            ->mapWithKeys(fn (CoreUnit $u) => [
                $u->public_id => $u->code.' — '.$u->canonical_name.' ('.$u->symbol.')',
            ])
            ->all();
    }

    public function packagingRuleRows(): array
    {
        return ConversionRule::query()
            ->with(['fromUnit', 'toUnit', 'contexts'])
            ->where('tenant_key', $this->tenantKey())
            ->where('rule_type', ConversionRuleType::PRODUCT_SPECIFIC->value)
            ->where('named_algorithm', 'ITEM_PACKAGE_CHAIN')
            ->where('status', 'ACTIVE')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (ConversionRule $rule) {
                $ctx = $rule->contexts->firstWhere('context_type', 'ITEM');

                return [
                    'id' => $rule->id,
                    'from' => $rule->fromUnit?->symbol,
                    'to' => $rule->toUnit?->symbol,
                    'factor' => (string) $rule->scale_decimal,
                    'item_key' => $ctx?->context_public_id,
                ];
            })
            ->all();
    }

    public function policyRows(): array
    {
        return ModuleUnitPolicy::query()
            ->with('unit')
            ->where('tenant_key', $this->tenantKey())
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn (ModuleUnitPolicy $p) => [
                'module' => $p->module_code,
                'type' => $p->domain_object_type,
                'object' => $p->domain_object_public_id,
                'role' => $p->usage_role,
                'unit' => $p->unit?->code,
                'status' => $p->status,
            ])
            ->all();
    }

    public function auditRows(): array
    {
        return UnitAudit::query()
            ->where('tenant_key', $this->tenantKey())
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn (UnitAudit $a) => [
                'at' => optional($a->created_at)->format('Y-m-d H:i'),
                'action' => $a->action,
                'object' => $a->object_type.' '.$a->object_public_id,
                'reason' => $a->reason,
                'actor' => $a->actor_user_id,
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.inventory.unit-engine-console', [
            'catalogRows' => $this->catalogRows(),
            'catalogStats' => $this->catalogStats(),
            'mappingRows' => $this->mappingRows(),
            'coreUnitIdOptions' => $this->coreUnitIdOptions(),
            'unitPublicIdOptions' => $this->unitPublicIdOptions(),
            'packagingRuleRows' => $this->packagingRuleRows(),
            'governanceRows' => $this->governanceRows(),
            'policyRows' => $this->policyRows(),
            'auditRows' => $this->auditRows(),
            'engineEnabled' => (bool) config('units.enabled'),
        ]);
    }
}
