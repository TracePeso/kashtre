<?php

namespace App\Livewire\Inventory;

use App\Models\Item;
use App\Models\Store;
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

    public function table(Table $table): Table
    {
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
                    ->label('Current stock')
                    ->alignEnd()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('stock.quantity_suom', $direction);
                    })
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('stock_days_display')
                    ->label('Stock (days)')
                    ->alignEnd()
                    ->state(fn (Item $record): float => $this->stockDays($record))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 1)),

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
                    ->options(fn (): array => Store::query()
                        ->where('business_id', Auth::user()->business_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
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
                'stock.daily_usage_suom as stock_daily_usage_suom',
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
        if ($this->usesJoinedStock($item)) {
            return (float) $item->stock_quantity_suom;
        }

        return (float) ($item->inventoryStockLevel?->quantity_suom ?? 0);
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

    private function stockDays(Item $item): float
    {
        $usage = $this->dailyUsage($item);

        if ($usage <= 0) {
            return 0.0;
        }

        return round($this->currentStock($item) / $usage, 1);
    }

    private function valuation(Item $item): float
    {
        return round($this->currentStock($item) * $this->averageCost($item), 2);
    }
}
