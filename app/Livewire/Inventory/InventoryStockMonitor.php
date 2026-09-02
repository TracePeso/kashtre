<?php

namespace App\Livewire\Inventory;

use App\Livewire\Inventory\Concerns\InteractsWithInventoryMetrics;
use App\Livewire\Inventory\Concerns\WarmsInventoryFilamentTable;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Services\Inventory\InventoryExpiredEscrowService;
use App\Services\Inventory\InventoryStockAgingService;
use App\Services\Inventory\InventoryStockCountShrinkageService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class InventoryStockMonitor extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithInventoryMetrics;
    use WarmsInventoryFilamentTable {
        paginateTableQuery as warmPaginateTableQuery;
    }

    public const VIEW_LOCAL = 'local';

    public const VIEW_NETWORK = 'network';

    public string $stockView = self::VIEW_LOCAL;

    public ?int $storeId = null;

    public ?InventoryModuleConfig $moduleConfig = null;

    /** @var array<int, string> */
    public array $storeOptions = [];

    public function mount(): void
    {
        $requestedView = request()->query('view');

        if ($requestedView === self::VIEW_NETWORK) {
            if (! \App\Support\InventoryBusinessContext::multiStoreNetworkEnabled()) {
                $this->stockView = self::VIEW_LOCAL;
            } else {
                $this->stockView = self::VIEW_NETWORK;
            }
        }

        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $this->moduleConfig = $this->moduleConfigFor($businessId);
        $this->storeOptions = Store::optionsForSelect($businessId);
        $this->storeId = $this->resolveDefaultStoreId();
    }

    public function setStockView(string $view): void
    {
        if (! in_array($view, [self::VIEW_LOCAL, self::VIEW_NETWORK], true) || $this->stockView === $view) {
            return;
        }

        if ($view === self::VIEW_NETWORK && ! \App\Support\InventoryBusinessContext::multiStoreNetworkEnabled()) {
            session()->flash('error', 'Multi-store network view is disabled for this organisation.');

            return;
        }

        $this->stockView = $view;
        $this->shrinkageStoreIds = null;
        $this->resetTable();
    }

    public function updatedStoreId(): void
    {
        $this->shrinkageStoreIds = null;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $this->stockView === self::VIEW_NETWORK
            ? $this->networkTable($table)
            : $this->localTable($table);
    }

    protected function paginateTableQuery(Builder $query): Paginator
    {
        $paginator = $this->stockView === self::VIEW_LOCAL
            ? $this->warmPaginateTableQuery($query)
            : $this->filamentPaginateTableQuery($query);

        $this->warmCumulativeShrinkageForPage($paginator);

        return $paginator;
    }

    protected function stockLevelsFromPaginator(Paginator $paginator): \Illuminate\Support\Collection
    {
        if ($this->stockView !== self::VIEW_LOCAL) {
            return $paginator->getCollection();
        }

        return $paginator->getCollection()->map(fn (Item $item): InventoryStockLevel => $this->stockLevel($item));
    }

    protected function warmAgingMetricsForStocks(iterable $stockLevels): void
    {
        app(InventoryStockAgingService::class)->warmPageAging(
            (int) \App\Support\InventoryBusinessContext::effectiveBusinessId(),
            $stockLevels
        );
    }

    public function render(): View
    {
        return view('livewire.inventory.inventory-stock-monitor', [
            'stores' => $this->storeOptions,
        ]);
    }

    private function localTable(Table $table): Table
    {
        $config = $this->moduleConfig;

        return $table
            ->query($this->localBaseQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->url(fn (Item $record): string => route('inventory.monitor.history', $record))
                    ->color('primary'),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('itemUnit.name')
                    ->label('Sale unit')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('system_stock_ar')
                    ->label('System stock')
                    ->alignEnd()
                    ->state(fn (Item $record): float => (float) $this->mForItem($record, 'system_ar'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('current_stock_m')
                    ->label('Current stock')
                    ->alignEnd()
                    ->state(fn (Item $record): float => (float) $this->mForItem($record, 'current_m'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('physical_stock_as')
                    ->label('Physical count (AS)')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Item $record): float => (float) ($record->stock_physical_quantity_suom ?? $record->stock_quantity_suom ?? 0))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('cumulative_shrinkage_suom')
                    ->label('Cumulative shrinkage')
                    ->alignEnd()
                    ->state(fn (Item $record): float => $this->cumulativeShrinkageForItem($record)['qty'])
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0))
                    ->color(fn (Item $record): ?string => $this->cumulativeShrinkageForItem($record)['qty'] > 0 ? 'danger' : null),

                TextColumn::make('cumulative_shrinkage_ugx')
                    ->label('Cumulative shrinkage (UGX)')
                    ->alignEnd()
                    ->state(fn (Item $record): float => $this->cumulativeShrinkageForItem($record)['ugx'])
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2))
                    ->color(fn (Item $record): ?string => $this->cumulativeShrinkageForItem($record)['ugx'] > 0 ? 'danger' : null),

                TextColumn::make('stock_days_n')
                    ->label('Stock days')
                    ->alignEnd()
                    ->state(fn (Item $record): ?float => $this->mForItem($record, 'stock_days'))
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 1) : '—'),

                TextColumn::make('days_left_am')
                    ->label('Days left')
                    ->alignEnd()
                    ->state(fn (Item $record): ?float => $this->mForItem($record, 'days_left'))
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 1) : '—')
                    ->color(fn ($state) => $state !== null && (float) $state <= 0 ? 'danger' : null),

                TextColumn::make('order_notify_ay')
                    ->label('Order notify')
                    ->state(fn (Item $record): ?string => $this->mForItem($record, 'notify_date'))
                    ->placeholder('—'),

                TextColumn::make('safety_stock_suom')
                    ->label('Safety stock')
                    ->alignEnd()
                    ->visible($config !== null)
                    ->state(fn (Item $record): float => (float) $this->mForItem($record, 'safety_stock_suom'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('buffer_stock_suom')
                    ->label('Buffer stock')
                    ->alignEnd()
                    ->visible($config !== null)
                    ->state(fn (Item $record): float => (float) $this->mForItem($record, 'buffer_stock_suom'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('stock_aging_days')
                    ->label('Stock aging')
                    ->alignEnd()
                    ->state(fn (Item $record): ?int => app(InventoryStockAgingService::class)->pageAgingDays(
                        (int) \App\Support\InventoryBusinessContext::effectiveBusinessId(),
                        (int) $record->stock_store_id,
                        (int) $record->id
                    ))
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((int) $state) : '—'),

                TextColumn::make('valuation_o')
                    ->label('Valuation')
                    ->alignEnd()
                    ->state(fn (Item $record): float => (float) $this->mForItem($record, 'valuation'))
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2)),

                TextColumn::make('expired_escrow')
                    ->label('Expired escrow')
                    ->alignEnd()
                    ->state(fn (Item $record): float => (float) ($record->stock_expired_quantity_suom ?? 0))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0))
                    ->color(fn ($state): ?string => (float) $state > 0 ? 'warning' : null),
            ])
            ->actions([
                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->url(fn (Item $record): string => route('inventory.monitor.history', $record)),
                Action::make('writeOffEscrow')
                    ->label('Write off escrow')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Item $record): bool => (float) ($record->stock_expired_quantity_suom ?? 0) > 0
                        && ! \App\Support\InventoryBusinessContext::isAdminBrowsing())
                    ->form(fn (Item $record): array => [
                        TextInput::make('quantity')
                            ->label('Quantity to write off')
                            ->numeric()
                            ->placeholder('Enter quantity to write off')
                            ->required()
                            ->minValue(0.0001)
                            ->maxValue((float) ($record->stock_expired_quantity_suom ?? 0))
                            ->default((float) ($record->stock_expired_quantity_suom ?? 0)),
                    ])
                    ->action(function (Item $record, array $data) {
                        \App\Support\InventoryBusinessContext::assertWritable();

                        try {
                            app(InventoryExpiredEscrowService::class)->writeOffEscrow(
                                (int) $record->business_id,
                                (int) $record->stock_store_id,
                                (int) $record->id,
                                (float) ($data['quantity'] ?? 0),
                                Auth::user()
                            );
                            Notification::make()->title('Expired escrow written off')->success()->send();
                            $this->resetTable();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title(collect($e->errors())->flatten()->first() ?? 'Could not write off escrow')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('name')
            ->striped()
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('No inventory activity')
            ->emptyStateDescription('No stock or consumption recorded at this store yet.');
    }

    private function networkTable(Table $table): Table
    {
        return $table
            ->query($this->networkBaseQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->url(fn (Item $record): string => route('inventory.monitor.history', $record))
                    ->color('primary'),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('itemUnit.name')
                    ->label('Sale unit')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('rollup_store_count')
                    ->label('Stores')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('rollup_physical_quantity_suom')
                    ->label('Physical stock')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('rollup_damaged_quantity_suom')
                    ->label('Damaged')
                    ->alignEnd()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('rollup_expired_quantity_suom')
                    ->label('Expired')
                    ->alignEnd()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('cumulative_shrinkage_suom')
                    ->label('Cumulative shrinkage')
                    ->alignEnd()
                    ->state(fn (Item $record): float => $this->cumulativeShrinkageForItem($record)['qty'])
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0))
                    ->color(fn (Item $record): ?string => $this->cumulativeShrinkageForItem($record)['qty'] > 0 ? 'danger' : null),

                TextColumn::make('cumulative_shrinkage_ugx')
                    ->label('Cumulative shrinkage (UGX)')
                    ->alignEnd()
                    ->state(fn (Item $record): float => $this->cumulativeShrinkageForItem($record)['ugx'])
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2))
                    ->color(fn (Item $record): ?string => $this->cumulativeShrinkageForItem($record)['ugx'] > 0 ? 'danger' : null),
            ])
            ->actions([
                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->url(fn (Item $record): string => route('inventory.monitor.history', $record)),
            ])
            ->defaultSort('name')
            ->striped()
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('No inventory activity')
            ->emptyStateDescription('No stock or consumption recorded in this store network yet.');
    }

    protected function mForItem(Item $item, string $field): mixed
    {
        return $this->m($this->stockLevel($item), $field);
    }

    private function localBaseQuery(): Builder
    {
        if (! $this->storeId) {
            return Item::query()->whereRaw('0 = 1');
        }

        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();

        return Item::query()
            ->where('items.business_id', $businessId)
            ->where('items.type', 'good')
            ->join('inventory_stock_levels as stock', function ($join) use ($businessId) {
                $join->on('stock.item_id', '=', 'items.id')
                    ->where('stock.business_id', '=', $businessId)
                    ->where('stock.store_id', '=', $this->storeId);
            })
            ->where(function (Builder $query) {
                $query->where('stock.quantity_suom', '>', 0)
                    ->orWhere('stock.quantity_in_transit_suom', '>', 0)
                    ->orWhere('stock.ma_15_days', '>', 0)
                    ->orWhere('stock.ma_30_days', '>', 0);
            })
            ->select([
                'items.id',
                'items.business_id',
                'items.name',
                'items.code',
                'items.uom_id',
                'items.purchase_price',
                'items.default_price',
                'stock.store_id as stock_store_id',
                'stock.quantity_suom as stock_quantity_suom',
                'stock.quantity_in_transit_suom as stock_quantity_in_transit_suom',
                'stock.physical_quantity_suom as stock_physical_quantity_suom',
                'stock.physical_counted_at as stock_physical_counted_at',
                'stock.opening_quantity_suom as stock_opening_quantity_suom',
                'stock.damaged_quantity_suom as stock_damaged_quantity_suom',
                'stock.expired_quantity_suom as stock_expired_quantity_suom',
                'stock.daily_usage_suom as stock_daily_usage_suom',
                'stock.safety_stock_days as stock_safety_stock_days',
                'stock.buffer_stock_days as stock_buffer_stock_days',
                'stock.ma_15_days as stock_ma_15_days',
                'stock.ma_30_days as stock_ma_30_days',
                'stock.ma_90_days as stock_ma_90_days',
                'stock.ma_180_days as stock_ma_180_days',
                'stock.ma_360_days as stock_ma_360_days',
                'stock.last_purchase_price as stock_last_purchase_price',
                'stock.weighted_avg_cost as stock_weighted_avg_cost',
            ])
            ->with('itemUnit:id,name');
    }

    private function networkBaseQuery(): Builder
    {
        if (! $this->storeId) {
            return Item::query()->whereRaw('0 = 1');
        }

        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $storeIds = Store::descendantIds($this->storeId);

        $rollupSub = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->whereIn('store_id', $storeIds)
            ->where(function (Builder $query) {
                $query->where('quantity_suom', '>', 0)
                    ->orWhere('quantity_in_transit_suom', '>', 0)
                    ->orWhere('ma_15_days', '>', 0)
                    ->orWhere('ma_30_days', '>', 0);
            })
            ->groupBy('item_id')
            ->selectRaw('item_id,
                SUM(quantity_suom) as rollup_physical_quantity_suom,
                SUM(COALESCE(quantity_in_transit_suom, 0)) as rollup_in_transit_suom,
                SUM(COALESCE(damaged_quantity_suom, 0)) as rollup_damaged_quantity_suom,
                SUM(COALESCE(expired_quantity_suom, 0)) as rollup_expired_quantity_suom,
                COUNT(DISTINCT store_id) as rollup_store_count');

        return Item::query()
            ->where('items.business_id', $businessId)
            ->where('items.type', 'good')
            ->joinSub($rollupSub, 'rollup', fn ($join) => $join->on('rollup.item_id', '=', 'items.id'))
            ->select([
                'items.id',
                'items.business_id',
                'items.name',
                'items.code',
                'items.uom_id',
                'rollup.rollup_physical_quantity_suom',
                'rollup.rollup_in_transit_suom',
                'rollup.rollup_damaged_quantity_suom',
                'rollup.rollup_expired_quantity_suom',
                'rollup.rollup_store_count',
            ])
            ->with('itemUnit:id,name');
    }

    private function stockLevel(Item $item): InventoryStockLevel
    {
        $level = new InventoryStockLevel([
            'business_id' => \App\Support\InventoryBusinessContext::effectiveBusinessId(),
            'store_id' => $item->stock_store_id,
            'item_id' => $item->id,
            'quantity_suom' => $item->stock_quantity_suom,
            'quantity_in_transit_suom' => $item->stock_quantity_in_transit_suom ?? 0,
            'physical_quantity_suom' => $item->stock_physical_quantity_suom,
            'physical_counted_at' => $item->stock_physical_counted_at,
            'opening_quantity_suom' => $item->stock_opening_quantity_suom,
            'damaged_quantity_suom' => $item->stock_damaged_quantity_suom,
            'expired_quantity_suom' => $item->stock_expired_quantity_suom,
            'daily_usage_suom' => $item->stock_daily_usage_suom,
            'safety_stock_days' => $item->stock_safety_stock_days,
            'buffer_stock_days' => $item->stock_buffer_stock_days,
            'ma_15_days' => $item->stock_ma_15_days,
            'ma_30_days' => $item->stock_ma_30_days,
            'ma_90_days' => $item->stock_ma_90_days,
            'ma_180_days' => $item->stock_ma_180_days,
            'ma_360_days' => $item->stock_ma_360_days,
            'last_purchase_price' => $item->stock_last_purchase_price,
            'weighted_avg_cost' => $item->stock_weighted_avg_cost,
        ]);
        $level->exists = true;
        $level->setRelation('item', $item);

        return $level;
    }

    private function resolveDefaultStoreId(): ?int
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $user = Auth::user();

        if ($user->default_store_id) {
            $exists = isset($this->storeOptions[(string) $user->default_store_id]);

            if ($exists) {
                return (int) $user->default_store_id;
            }
        }

        if ($user->branch_id) {
            $branchStoreId = Store::query()
                ->forBusiness($businessId)
                ->where('branch_id', $user->branch_id)
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->value('id');

            if ($branchStoreId && isset($this->storeOptions[(string) $branchStoreId])) {
                return (int) $branchStoreId;
            }
        }

        $first = array_key_first($this->storeOptions);

        return $first ? (int) $first : null;
    }

    /** @var list<int>|null */
    private ?array $shrinkageStoreIds = null;

    private function shrinkageStoreIds(): array
    {
        if ($this->shrinkageStoreIds !== null) {
            return $this->shrinkageStoreIds;
        }

        if (! $this->storeId) {
            return $this->shrinkageStoreIds = [];
        }

        return $this->shrinkageStoreIds = $this->stockView === self::VIEW_NETWORK
            ? Store::descendantIds($this->storeId)
            : [(int) $this->storeId];
    }

    private function warmCumulativeShrinkageForPage(Paginator $paginator): void
    {
        if (! $this->storeId) {
            return;
        }

        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $itemIds = $paginator->getCollection()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        app(InventoryStockCountShrinkageService::class)
            ->warmPageCumulativeShrinkage($businessId, $this->shrinkageStoreIds(), $itemIds);
    }

    /**
     * @return array{qty: float, ugx: float}
     */
    private function cumulativeShrinkageForItem(Item $item): array
    {
        if (! $this->storeId) {
            return ['qty' => 0.0, 'ugx' => 0.0];
        }

        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();

        return app(InventoryStockCountShrinkageService::class)
            ->cumulativeForItem($businessId, $this->shrinkageStoreIds(), (int) $item->id);
    }
}
