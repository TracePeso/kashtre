<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
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

class NetworkStockTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?int $storeId = null;

    public bool $embedded = false;

    public function mount(?int $storeId = null, bool $embedded = false): void
    {
        $this->storeId = $storeId;
        $this->embedded = $embedded;
    }

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

                TextColumn::make('itemUnit.name')
                    ->label('SUOM')
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
            ])
            ->actions([
                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->url(fn (Item $record): string => route('inventory.monitor.history', $record)),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('No inventory activity')
            ->emptyStateDescription('No stock or consumption recorded in this store network yet.');
    }

    public function render(): View
    {
        return view('livewire.inventory.network-stock-table', [
            'embedded' => $this->embedded,
        ]);
    }

    private function baseQuery(): Builder
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();

        if (! $this->storeId) {
            return Item::query()->whereRaw('0 = 1');
        }

        $storeIds = Store::descendantIds($this->storeId);

        $rollupSub = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->whereIn('store_id', $storeIds)
            ->where(function (Builder $query) {
                $query->where('quantity_suom', '>', 0)
                    ->orWhere('ma_15_days', '>', 0)
                    ->orWhere('ma_30_days', '>', 0);
            })
            ->groupBy('item_id')
            ->selectRaw('item_id,
                SUM(quantity_suom) as rollup_physical_quantity_suom,
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
                'rollup.rollup_damaged_quantity_suom',
                'rollup.rollup_expired_quantity_suom',
                'rollup.rollup_store_count',
            ])
            ->with('itemUnit:id,name');
    }
}
