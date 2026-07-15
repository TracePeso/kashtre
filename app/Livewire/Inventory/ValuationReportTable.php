<?php

namespace App\Livewire\Inventory;

use App\Livewire\Inventory\Concerns\InteractsWithInventoryMetrics;
use App\Livewire\Inventory\Concerns\WarmsInventoryFilamentTable;
use App\Models\InventoryStockLevel;
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

class ValuationReportTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithInventoryMetrics;
    use WarmsInventoryFilamentTable;

    public function table(Table $table): Table
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();

        return $table
            ->query($this->inventoryReportQuery($businessId))
            ->columns([
                TextColumn::make('item_name')->label('Item')->searchable()->sortable(),
                TextColumn::make('item_code')->label('Code')->searchable(),
                TextColumn::make('store_name')->label('Store')->sortable(),
                TextColumn::make('current_m')
                    ->label('Physical stock')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => (float) $this->m($record, 'current_m'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0)),
                TextColumn::make('unit_cost')
                    ->label('Cost per sale unit')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => (float) $this->m($record, 'purchase_price'))
                    ->formatStateUsing(fn ($state) => 'UGX '.number_format((float) $state, 2)),
                TextColumn::make('valuation')
                    ->label('Valuation')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => (float) $this->m($record, 'valuation'))
                    ->formatStateUsing(fn ($state) => 'UGX '.number_format((float) $state, 2)),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name', fn (Builder $query) => $query->where('business_id', $businessId)),
            ])
            ->defaultSort('item_name')
            ->striped()
            ->paginated([25, 50, 100]);
    }

    public function render(): View
    {
        return view('livewire.inventory.valuation-report-table');
    }
}
