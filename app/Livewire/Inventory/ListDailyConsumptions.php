<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryDailyConsumption;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
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
            )
            ->columns([
                TextColumn::make('consumption_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('item.name')
                    ->label('Item')
                    ->description(fn (InventoryDailyConsumption $record): ?string => $record->item?->code)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('item', function (Builder $itemQuery) use ($search): void {
                            $itemQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),

                TextColumn::make('quantity_suom')
                    ->label('Qty (SUOM)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 4)),

                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        InventoryDailyConsumption::SOURCE_SALE => 'success',
                        InventoryDailyConsumption::SOURCE_ISSUE => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        InventoryDailyConsumption::SOURCE_SALE => 'POS / Sale',
                        InventoryDailyConsumption::SOURCE_ISSUE => 'Issue',
                        InventoryDailyConsumption::SOURCE_MANUAL => 'Manual',
                        default => ucfirst($state),
                    }),

                TextColumn::make('recordedBy.email')
                    ->label('Recorded by')
                    ->description(fn (InventoryDailyConsumption $record): ?string => $record->recordedBy?->name)
                    ->placeholder('—')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name', fn (Builder $query) => $query->where('business_id', Auth::user()->business_id)),
                SelectFilter::make('source')
                    ->label('Source')
                    ->options([
                        InventoryDailyConsumption::SOURCE_SALE => 'POS / Sale',
                        InventoryDailyConsumption::SOURCE_ISSUE => 'Issue',
                        InventoryDailyConsumption::SOURCE_MANUAL => 'Manual',
                    ]),
                Filter::make('consumption_date')
                    ->label('Date range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, string $date): Builder => $q->whereDate('consumption_date', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, string $date): Builder => $q->whereDate('consumption_date', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (InventoryDailyConsumption $record): string => route('inventory.consumption.show', $record)),
                Action::make('item_stock')
                    ->label('Item stock')
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn (InventoryDailyConsumption $record): ?string => $record->item
                        ? route('inventory.monitor.history', $record->item)
                        : null)
                    ->visible(fn (InventoryDailyConsumption $record): bool => $record->item !== null),
            ])
            ->defaultSort('consumption_date', 'desc')
            ->striped()
            ->deferLoading()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('No consumption recorded yet')
            ->emptyStateDescription('Entries appear here automatically when goods are sold or issued. Ensure staff have a default store set so consumption is attributed to the correct location.');
    }

    public function render(): View
    {
        return view('livewire.inventory.list-daily-consumptions');
    }
}
