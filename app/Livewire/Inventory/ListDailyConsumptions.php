<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryDailyConsumption;
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

class ListDailyConsumptions extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryDailyConsumption::query()
                    ->where('business_id', Auth::user()->business_id)
                    ->with(['store', 'item.itemUnit', 'recordedBy'])
                    ->latest('consumption_date')
            )
            ->columns([
                TextColumn::make('consumption_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),

                TextColumn::make('quantity_suom')
                    ->label('Qty (SUOM)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 4)),

                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('recordedBy.name')
                    ->label('Recorded by')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name', fn (Builder $query) => $query->where('business_id', Auth::user()->business_id)),
            ])
            ->defaultSort('consumption_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public function render(): View
    {
        return view('livewire.inventory.list-daily-consumptions');
    }
}
