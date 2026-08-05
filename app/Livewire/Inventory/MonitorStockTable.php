<?php

namespace App\Livewire\Inventory;

use App\Livewire\Inventory\Concerns\InteractsWithInventoryMetrics;
use App\Livewire\Inventory\Concerns\WarmsInventoryFilamentTable;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Services\Inventory\InventoryStockAgingService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MonitorStockTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithInventoryMetrics;
    use WarmsInventoryFilamentTable;

    public ?\App\Models\InventoryModuleConfig $moduleConfig = null;

    public ?int $storeId = null;

    /** @var array<int, string> */
    public array $storeFilterOptions = [];

    public function mount(?int $storeId = null): void
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $this->moduleConfig = $this->moduleConfigFor($businessId);
        $this->storeId = $storeId;

        if ($storeId === null) {
            $this->storeFilterOptions = Store::optionsForSelect($businessId);
        }
    }

    public function table(Table $table): Table
    {
        $config = $this->moduleConfig;

        return $table
            ->query($this->baseQuery())
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

                TextColumn::make('store_name')
                    ->label('Store')
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('stores.name', $direction);
                    })
                    ->visible($this->storeId === null),

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

                TextColumn::make('shrinkage_excel_pct')
                    ->label('Shrinkage %')
                    ->alignEnd()
                    ->state(fn (Item $record): ?float => $this->mForItem($record, 'shrinkage_pct'))
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 4).'%' : '—'),

                TextColumn::make('shrinkage_excel_ugx')
                    ->label('Shrinkage UGX')
                    ->alignEnd()
                    ->state(fn (Item $record): ?float => $this->mForItem($record, 'shrinkage_ugx'))
                    ->formatStateUsing(fn ($state): string => $state !== null ? 'UGX '.number_format((float) $state, 2) : '—'),

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
                    ->state(function (Item $record): ?int {
                        return app(InventoryStockAgingService::class)->pageAgingDays(
                            (int) \App\Support\InventoryBusinessContext::effectiveBusinessId(),
                            (int) $record->stock_store_id,
                            (int) $record->id
                        );
                    })
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((int) $state) : '—'),

                TextColumn::make('valuation_o')
                    ->label('Valuation')
                    ->alignEnd()
                    ->state(fn (Item $record): float => (float) $this->mForItem($record, 'valuation'))
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2)),
            ])
            ->actions([
                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->url(fn (Item $record): string => route('inventory.monitor.history', $record)),
            ])
            ->filters($this->storeId === null ? [
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->options($this->storeFilterOptions)
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->where('stock.store_id', $data['value']);
                    }),
            ] : [])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('No inventory activity')
            ->emptyStateDescription($this->storeId
                ? 'No stock or consumption recorded at this store yet.'
                : 'Items appear after goods are received or consumption is recorded.');
    }

    protected function stockLevelsFromPaginator(Paginator $paginator): Collection
    {
        return $paginator->getCollection()->map(fn (Item $item): InventoryStockLevel => $this->stockLevel($item));
    }

    protected function warmAgingMetricsForStocks(iterable $stockLevels): void
    {
        app(InventoryStockAgingService::class)->warmPageAging(
            (int) \App\Support\InventoryBusinessContext::effectiveBusinessId(),
            $stockLevels
        );
    }

    protected function mForItem(Item $item, string $field): mixed
    {
        return $this->m($this->stockLevel($item), $field);
    }

    public function render(): View
    {
        return view('livewire.inventory.monitor-stock-table');
    }

    private function baseQuery(): Builder
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();

        return Item::query()
            ->where('items.business_id', $businessId)
            ->where('items.type', 'good')
            ->join('inventory_stock_levels as stock', function ($join) use ($businessId) {
                $join->on('stock.item_id', '=', 'items.id')
                    ->where('stock.business_id', '=', $businessId);
            })
            ->leftJoin('stores', 'stores.id', '=', 'stock.store_id')
            ->where(function (Builder $query) {
                $query->where('stock.quantity_suom', '>', 0)
                    ->orWhere('stock.quantity_in_transit_suom', '>', 0)
                    ->orWhere('stock.ma_15_days', '>', 0)
                    ->orWhere('stock.ma_30_days', '>', 0);
            })
            ->when($this->storeId, fn (Builder $query) => $query->where('stock.store_id', $this->storeId))
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
                'stores.name as store_name',
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
}
