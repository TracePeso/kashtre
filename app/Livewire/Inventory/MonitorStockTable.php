<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryModuleConfig;
use App\Models\Item;
use App\Models\Store;
use App\Services\Inventory\InventoryStockAgingService;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MonitorStockTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?InventoryModuleConfig $moduleConfig = null;

    public function mount(): void
    {
        $this->moduleConfig = InventoryModuleConfig::query()
            ->forBusiness((int) Auth::user()->business_id)
            ->active()
            ->first();
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
                    }),

                TextColumn::make('itemUnit.name')
                    ->label('SUOM')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('stock_quantity_suom')
                    ->label('System stock')
                    ->alignEnd()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('stock.quantity_suom', $direction);
                    })
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('stock_physical_quantity_suom')
                    ->label('Physical stock')
                    ->alignEnd()
                    ->placeholder('—')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('stock.physical_quantity_suom', $direction);
                    })
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 0) : '—'),

                TextColumn::make('usable_stock_display')
                    ->label('Physical usable stock')
                    ->alignEnd()
                    ->state(fn (Item $record): float => $this->physicalUsableStock($record))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('verifiable_shrinkage_display')
                    ->label('Verifiable shrinkage')
                    ->alignEnd()
                    ->tooltip('Damaged + expired quantities still on the shelf but unusable')
                    ->state(fn (Item $record): ?float => $this->verifiableShrinkage($record))
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 0) : '—'),

                TextColumn::make('unverified_shrinkage_display')
                    ->label('Unverified loss')
                    ->alignEnd()
                    ->tooltip('System stock minus physical count — units missing from the shelf')
                    ->state(fn (Item $record): ?float => $this->unverifiedShrinkage($record))
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 0) : '—'),

                TextColumn::make('shrinkage_display')
                    ->label('Total shrinkage')
                    ->alignEnd()
                    ->state(fn (Item $record): ?float => $this->shrinkagePercent($record))
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2).'%' : '—'),

                TextColumn::make('stock_days_display')
                    ->label('Stock (days)')
                    ->alignEnd()
                    ->state(fn (Item $record): float => $this->stockDays($record))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 1)),

                TextColumn::make('effective_daily_usage')
                    ->label('Daily avg (SUOM)')
                    ->alignEnd()
                    ->visible($config !== null)
                    ->state(fn (Item $record): float => $this->effectiveDailyUsage($record))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 4)),

                TextColumn::make('stock_ma_30_days')
                    ->label('30-day avg')
                    ->tooltip('30-day moving average daily consumption (SUOM)')
                    ->alignEnd()
                    ->state(fn (Item $record): float => $this->movingAverage($record, 30))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 4)),

                TextColumn::make('stock_aging_days')
                    ->label('Stock aging (days)')
                    ->tooltip('Days since last approved GRN delivery (Excel column U)')
                    ->alignEnd()
                    ->state(function (Item $record): ?int {
                        if (! $this->usesJoinedStock($record)) {
                            return null;
                        }

                        return app(InventoryStockAgingService::class)->agingDays(
                            (int) Auth::user()->business_id,
                            (int) $record->stock_store_id,
                            (int) $record->id
                        );
                    })
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((int) $state) : '—'),

                TextColumn::make('safety_stock_suom')
                    ->label('Safety stock (SUOM)')
                    ->alignEnd()
                    ->visible($config !== null)
                    ->state(fn (Item $record): float => $config->safetyStockSuom($this->dailyUsage($record)))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 4)),

                TextColumn::make('buffer_stock_suom')
                    ->label('Buffer stock (SUOM)')
                    ->alignEnd()
                    ->visible($config !== null)
                    ->state(fn (Item $record): float => $config->bufferStockSuom($this->dailyUsage($record)))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 4)),

                TextColumn::make('stock_last_purchase_price')
                    ->label('Last price')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2)),

                TextColumn::make('stock_weighted_avg_cost')
                    ->label('Avg cost')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, Item $record): string => 'UGX '.number_format(
                        (float) ($state ?? $record->stock_last_purchase_price ?? 0),
                        2
                    )),

                TextColumn::make('valuation_display')
                    ->label('Valuation')
                    ->alignEnd()
                    ->state(fn (Item $record): float => $this->valuation($record))
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2)),
            ])
            ->actions([
                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->url(fn (Item $record): string => route('inventory.monitor.history', $record)),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->options(fn (): array => Store::optionsForSelect((int) Auth::user()->business_id))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->where('stock.store_id', $data['value']);
                    }),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('No stock on hand')
            ->emptyStateDescription('Items appear here after goods are received and all GRN approvers have signed off.');
    }

    public function render(): View
    {
        return view('livewire.inventory.monitor-stock-table');
    }

    private function baseQuery(): Builder
    {
        $businessId = (int) Auth::user()->business_id;

        return Item::query()
            ->where('items.business_id', $businessId)
            ->where('items.type', 'good')
            ->join('inventory_stock_levels as stock', function ($join) use ($businessId) {
                $join->on('stock.item_id', '=', 'items.id')
                    ->where('stock.business_id', '=', $businessId);
            })
            ->leftJoin('stores', 'stores.id', '=', 'stock.store_id')
            ->where('stock.quantity_suom', '>', 0)
            ->select([
                'items.*',
                'stock.store_id as stock_store_id',
                'stock.quantity_suom as stock_quantity_suom',
                'stock.physical_quantity_suom as stock_physical_quantity_suom',
                'stock.damaged_quantity_suom as stock_damaged_quantity_suom',
                'stock.expired_quantity_suom as stock_expired_quantity_suom',
                'stock.daily_usage_suom as stock_daily_usage_suom',
                'stock.safety_stock_days as stock_safety_stock_days',
                'stock.buffer_stock_days as stock_buffer_stock_days',
                'stock.ma_30_days as stock_ma_30_days',
                'stock.last_purchase_price as stock_last_purchase_price',
                'stock.weighted_avg_cost as stock_weighted_avg_cost',
                'stores.name as store_name',
            ])
            ->with('itemUnit');
    }

    private function usesJoinedStock(Item $item): bool
    {
        return array_key_exists('stock_quantity_suom', $item->getAttributes());
    }

    private function currentStock(Item $item): float
    {
        return $this->physicalUsableStock($item);
    }

    private function physicalUsableStock(Item $item): float
    {
        if ($this->usesJoinedStock($item)) {
            $physical = $item->stock_physical_quantity_suom;
            $base = $physical !== null
                ? (float) $physical
                : (float) $item->stock_quantity_suom;
            $verifiable = (float) ($item->stock_damaged_quantity_suom ?? 0)
                + (float) ($item->stock_expired_quantity_suom ?? 0);

            return max(0, round($base - $verifiable, 4));
        }

        $level = $item->inventoryStockLevel;

        if (! $level) {
            return 0.0;
        }

        return $level->physicalUsableQuantitySuom();
    }

    /** @deprecated */
    private function usableStock(Item $item): float
    {
        return $this->physicalUsableStock($item);
    }

    private function verifiableShrinkage(Item $item): ?float
    {
        if (! $this->usesJoinedStock($item)) {
            $level = $item->inventoryStockLevel;

            return $level ? $level->verifiableLossSuom() : null;
        }

        return round(
            (float) ($item->stock_damaged_quantity_suom ?? 0) + (float) ($item->stock_expired_quantity_suom ?? 0),
            4
        );
    }

    private function unverifiedShrinkage(Item $item): ?float
    {
        if ($this->usesJoinedStock($item)) {
            if ($item->stock_physical_quantity_suom === null) {
                return null;
            }

            return max(0, round((float) $item->stock_quantity_suom - (float) $item->stock_physical_quantity_suom, 4));
        }

        return $item->inventoryStockLevel?->unverifiedShrinkageAmountSuom();
    }

    private function shrinkagePercent(Item $item): ?float
    {
        if ($this->usesJoinedStock($item)) {
            if ($item->stock_physical_quantity_suom === null) {
                return null;
            }

            $system = (float) $item->stock_quantity_suom;

            if ($system <= 0) {
                return null;
            }

            return round((($system - (float) $item->stock_physical_quantity_suom) / $system) * 100, 2);
        }

        return $item->inventoryStockLevel?->shrinkagePercent();
    }

    private function movingAverage(Item $item, int $days): float
    {
        if ($this->usesJoinedStock($item) && $days === 30 && $item->stock_ma_30_days !== null) {
            return (float) $item->stock_ma_30_days;
        }

        return $this->dailyUsage($item);
    }

    private function dailyUsage(Item $item): float
    {
        if ($this->usesJoinedStock($item)) {
            return (float) ($item->stock_daily_usage_suom ?? 0);
        }

        return (float) ($item->inventoryStockLevel?->daily_usage_suom ?? 0);
    }

    private function averageCost(Item $item): float
    {
        if ($this->usesJoinedStock($item)) {
            return (float) (
                $item->stock_weighted_avg_cost
                ?? $item->stock_last_purchase_price
                ?? $item->default_price
                ?? 0
            );
        }

        $level = $item->inventoryStockLevel;

        return (float) (
            $level?->weighted_avg_cost
            ?? $level?->last_purchase_price
            ?? $item->default_price
            ?? 0
        );
    }

    private function effectiveDailyUsage(Item $item): float
    {
        if (! $this->moduleConfig) {
            return $this->dailyUsage($item);
        }

        return $this->moduleConfig->effectiveDailyUsageSuom($this->dailyUsage($item));
    }

    private function stockDays(Item $item): float
    {
        $usage = $this->effectiveDailyUsage($item);

        if ($usage <= 0) {
            return 0.0;
        }

        return round($this->physicalUsableStock($item) / $usage, 1);
    }

    private function valuation(Item $item): float
    {
        return round($this->physicalUsableStock($item) * $this->averageCost($item), 2);
    }
}
