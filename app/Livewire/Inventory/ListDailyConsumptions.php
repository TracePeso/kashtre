<?php

namespace App\Livewire\Inventory;

use App\Services\Inventory\InventoryConsumptionQueryService;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListDailyConsumptions extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function mount(): void
    {
        $this->tableFilters = [
            'consumption_year' => [
                'year' => (string) now()->year,
            ],
        ];
    }

    public function table(Table $table): Table
    {
        $businessId = (int) Auth::user()->business_id;
        $queries = app(InventoryConsumptionQueryService::class);

        return $table
            ->query($queries->itemStoreMonthlySummariesQuery($businessId))
            ->columns([
                TextColumn::make('consumption_month')
                    ->label('Month')
                    ->formatStateUsing(fn (string $state): string => Carbon::parse($state.'-01')->format('F Y'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('consumption_month', $direction)),

                TextColumn::make('item_name')
                    ->label('Item')
                    ->description(fn ($record): ?string => $record->item_code)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('items.name', 'like', "%{$search}%")
                                ->orWhere('items.code', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('items.name', $direction)),

                TextColumn::make('store_name')
                    ->label('Store')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('stores.name', $direction)),

                TextColumn::make('total_quantity_suom')
                    ->label('Consumed (SUOM)')
                    ->tooltip('Total quantity used in this month')
                    ->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('total_quantity_suom', $direction))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('days_with_usage')
                    ->label('Days used')
                    ->tooltip('Days in the month when this item had any consumption')
                    ->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('days_with_usage', $direction)),

                TextColumn::make('month_daily_avg')
                    ->label('Daily avg')
                    ->tooltip('Monthly total ÷ days in month')
                    ->alignEnd()
                    ->state(fn ($record): float => $queries->monthDailyAverage(
                        (float) $record->total_quantity_suom,
                        (string) $record->consumption_month
                    ))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->options(fn (): array => \App\Models\Store::optionsForSelect($businessId))
                    ->query(fn (Builder $query, array $data): Builder => $data['value']
                        ? $query->where('inventory_daily_consumptions.store_id', $data['value'])
                        : $query),
                Filter::make('consumption_year')
                    ->label('Year')
                    ->form([
                        Select::make('year')
                            ->label('Year')
                            ->options($this->yearOptions())
                            ->required(),
                        Select::make('month')
                            ->label('Month (optional)')
                            ->options($this->monthOptions())
                            ->placeholder('All months'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['year'] ?? null,
                                fn (Builder $q, string $year): Builder => $q->whereYear('inventory_daily_consumptions.consumption_date', $year)
                            )
                            ->when(
                                $data['month'] ?? null,
                                fn (Builder $q, string $month): Builder => $q->whereMonth('inventory_daily_consumptions.consumption_date', $month)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['year'] ?? null) {
                            $indicators[] = 'Year '.$data['year'];
                        }

                        if ($data['month'] ?? null) {
                            $indicators[] = Carbon::create(null, (int) $data['month'], 1)->format('F');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn ($action) => $action->label('Year & store'))
            ->actions([
                Action::make('view_days')
                    ->label('Daily breakdown')
                    ->icon('heroicon-o-calendar-days')
                    ->url(fn ($record): string => route('inventory.consumption.month', [
                        'item' => $record->item_id,
                        'month' => $record->consumption_month,
                        'store_id' => $record->store_id,
                    ])),
                Action::make('item_stock')
                    ->label('Item stock')
                    ->icon('heroicon-o-cube')
                    ->url(fn ($record): string => route('inventory.monitor.history', $record->item_id)),
            ])
            ->defaultSort('consumption_month', 'desc')
            ->striped()
            ->deferLoading()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No consumption this year')
            ->emptyStateDescription('Monthly usage per item will appear here once goods are consumed.');
    }

    public function render(InventoryConsumptionQueryService $queries): View
    {
        $summary = $queries->yearSummary((int) Auth::user()->business_id, $this->selectedYear());

        return view('livewire.inventory.list-daily-consumptions', compact('summary'));
    }

    public function getTableRecordKey(Model $record): string
    {
        return $record->store_id.'-'.$record->item_id.'-'.$record->consumption_month;
    }

    private function selectedYear(): int
    {
        $year = $this->tableFilters['consumption_year']['year'] ?? now()->year;

        return (int) $year;
    }

    /**
     * @return array<string, string>
     */
    private function yearOptions(): array
    {
        $current = (int) now()->year;
        $options = [];

        for ($year = $current; $year >= $current - 5; $year--) {
            $options[(string) $year] = (string) $year;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function monthOptions(): array
    {
        $options = [];

        for ($month = 1; $month <= 12; $month++) {
            $options[(string) $month] = Carbon::create(null, $month, 1)->format('F');
        }

        return $options;
    }
}
