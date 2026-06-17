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

class ReorderPointReportTable extends Component implements HasForms, HasTable
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
                TextColumn::make('stock_days')
                    ->label('Stock days (N)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): ?float => $this->m($record, 'stock_days'))
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 1) : '—'),
                TextColumn::make('safety_days')
                    ->label('Safety days')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => (float) $this->m($record, 'safety_days'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1)),
                TextColumn::make('buffer_days')
                    ->label('Buffer days')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => (float) $this->m($record, 'buffer_days'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1)),
                TextColumn::make('days_left')
                    ->label('Days left (AM)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): ?float => $this->m($record, 'days_left'))
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 1) : '—')
                    ->color(fn ($state) => $state !== null && (float) $state <= 0 ? 'danger' : null),
                TextColumn::make('notify_date')
                    ->label('Notify date (AY)')
                    ->state(fn (InventoryStockLevel $record): ?string => $this->m($record, 'notify_date'))
                    ->placeholder('—'),
                TextColumn::make('current_m')
                    ->label('Current stock (M)')
                    ->alignEnd()
                    ->state(fn (InventoryStockLevel $record): float => (float) $this->m($record, 'current_m'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0)),
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
        return view('livewire.inventory.reorder-point-report-table');
    }
}
