<?php

namespace App\Livewire\Inventory;

use App\Models\Item;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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
                TextColumn::make('category')
                    ->label('Category')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('itemUnit.name')
                    ->label('SUOM')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('current_stock')
                    ->label('Current stock')
                    ->alignEnd()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            'COALESCE((SELECT quantity_suom FROM inventory_stock_levels WHERE inventory_stock_levels.item_id = items.id AND inventory_stock_levels.business_id = items.business_id LIMIT 1), 0) '.$direction
                        );
                    })
                    ->formatStateUsing(fn (Item $record): string => number_format($this->currentStock($record), 0)),

                TextColumn::make('stock_days')
                    ->label('Stock (days)')
                    ->alignEnd()
                    ->formatStateUsing(fn (Item $record): string => number_format($this->stockDays($record), 1)),

                TextColumn::make('purchase_price')
                    ->label('Purchase price')
                    ->alignEnd()
                    ->formatStateUsing(fn (Item $record): string => 'UGX '.number_format($this->purchasePrice($record), 2)),

                TextColumn::make('valuation')
                    ->label('Valuation')
                    ->alignEnd()
                    ->formatStateUsing(fn (Item $record): string => 'UGX '.number_format($this->valuation($record), 2)),
            ])
            ->filters([])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('No stock on hand')
            ->emptyStateDescription('Items appear here after goods are received and GRNs are approved. Until then, this list stays empty.');
    }

    public function render(): View
    {
        return view('livewire.inventory.monitor-stock-table');
    }

    private function baseQuery(): Builder
    {
        return Item::query()
            ->where('business_id', Auth::user()->business_id)
            ->where('type', 'good')
            ->whereHas('inventoryStockLevel', fn (Builder $query) => $query->where('quantity_suom', '>', 0))
            ->with(['itemUnit', 'inventoryStockLevel']);
    }

    private function currentStock(Item $item): float
    {
        return (float) ($item->inventoryStockLevel?->quantity_suom ?? 0);
    }

    private function dailyUsage(Item $item): float
    {
        return (float) ($item->inventoryStockLevel?->daily_usage_suom ?? 0);
    }

    private function purchasePrice(Item $item): float
    {
        return (float) ($item->inventoryStockLevel?->last_purchase_price ?? $item->default_price ?? 0);
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
        return round($this->currentStock($item) * $this->purchasePrice($item), 2);
    }
}
