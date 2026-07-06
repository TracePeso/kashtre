<?php

namespace App\Livewire\Inventory;

use App\Livewire\Inventory\Concerns\WarmsInventoryFilamentTable;
use App\Models\InventoryStockLevel;
use App\Services\Inventory\InventoryStockAgingService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockAgingTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use WarmsInventoryFilamentTable;

    public function table(Table $table): Table
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $agingService = app(InventoryStockAgingService::class);

        return $table
            ->query(
                InventoryStockLevel::query()
                    ->where('inventory_stock_levels.business_id', $businessId)
                    ->where('inventory_stock_levels.quantity_suom', '>', 0)
                    ->join('items', 'items.id', '=', 'inventory_stock_levels.item_id')
                    ->join('stores', 'stores.id', '=', 'inventory_stock_levels.store_id')
                    ->select([
                        'inventory_stock_levels.*',
                        'items.name as item_name',
                        'items.code as item_code',
                        'stores.name as store_name',
                    ])
                    ->with('item.itemUnit')
            )
            ->columns([
                TextColumn::make('item_name')->label('Item')->searchable()->sortable(),
                TextColumn::make('item_code')->label('Code')->searchable(),
                TextColumn::make('store_name')->label('Store')->sortable(),
                TextColumn::make('quantity_suom')
                    ->label('System stock')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0)),
                TextColumn::make('last_delivery_display')
                    ->label('Last delivery')
                    ->state(function (InventoryStockLevel $record) use ($agingService, $businessId): ?string {
                        return $agingService->pageLastDeliveryDate($businessId, (int) $record->store_id, (int) $record->item_id)
                            ?->format('M d, Y');
                    })
                    ->placeholder('—'),
                TextColumn::make('aging_days_display')
                    ->label('Aging (days)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): ?int => $agingService->pageAgingDays(
                        $businessId,
                        (int) $record->store_id,
                        (int) $record->item_id
                    ))
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((int) $state) : '—')
                    ->color(fn ($state) => $state !== null && (int) $state > 90 ? 'danger' : ($state !== null && (int) $state > 30 ? 'warning' : null)),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name', fn (Builder $query) => $query->where('business_id', $businessId)),
            ])
            ->defaultSort('item_name')
            ->striped()
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No stocked items')
            ->emptyStateDescription('Items appear here after goods are received and approved via a goods receive note.');
    }

    protected function warmTablePageMetrics(iterable $stockLevels): void
    {
        app(InventoryStockAgingService::class)->warmPageAging(
            (int) \App\Support\InventoryBusinessContext::effectiveBusinessId(),
            $stockLevels
        );
    }

    public function render(): View
    {
        return view('livewire.inventory.stock-aging-table');
    }
}
