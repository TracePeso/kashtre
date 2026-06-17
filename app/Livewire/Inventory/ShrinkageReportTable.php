<?php

namespace App\Livewire\Inventory;

use App\Livewire\Inventory\Concerns\InteractsWithInventoryMetrics;
use App\Livewire\Inventory\Concerns\WarmsInventoryFilamentTable;
use App\Models\InventoryModuleConfig;
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

class ShrinkageReportTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithInventoryMetrics;
    use WarmsInventoryFilamentTable;

    public ?InventoryModuleConfig $inventoryModuleConfig = null;

    public function mount(): void
    {
        $this->inventoryModuleConfig = $this->moduleConfigFor((int) Auth::user()->business_id);
    }

    public function table(Table $table): Table
    {
        $businessId = (int) Auth::user()->business_id;

        return $table
            ->query($this->inventoryReportQuery($businessId))
            ->columns([
                TextColumn::make('item_name')->label('Item')->searchable()->sortable(),
                TextColumn::make('item_code')->label('Code')->searchable(),
                TextColumn::make('store_name')->label('Store')->sortable(),
                TextColumn::make('system_ar')
                    ->label('System stock (AR)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => (float) $this->m($record, 'system_ar'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0)),
                TextColumn::make('current_m')
                    ->label('Current stock (M)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => (float) $this->m($record, 'current_m'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0)),
                TextColumn::make('shrinkage_qty')
                    ->label('Shrinkage (SUOM)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => (float) $this->m($record, 'shrinkage_qty'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0)),
                TextColumn::make('shrinkage_pct')
                    ->label('Shrinkage % (AV)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): ?float => $this->m($record, 'shrinkage_pct'))
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 4).'%' : '—')
                    ->color(fn ($state) => $state !== null && (float) $state > 0 ? 'danger' : null),
                TextColumn::make('shrinkage_ugx')
                    ->label('Shrinkage UGX (AW)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): ?float => $this->m($record, 'shrinkage_ugx'))
                    ->formatStateUsing(fn ($state) => $state !== null ? 'UGX '.number_format((float) $state, 2) : '—'),
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
        return view('livewire.inventory.shrinkage-report-table');
    }
}
