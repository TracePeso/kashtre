<?php

namespace App\Livewire\Inventory;

use App\Models\Store;
use App\Services\Inventory\InventoryConsumptionQueryService;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
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

    /** @var array<string, mixed> */
    public array $summary = [
        'year' => 0,
        'month_rows' => 0,
        'distinct_items' => 0,
        'total_quantity_suom' => 0,
    ];

    /** @var array<string, string> */
    public array $storeOptions = [];

    public function mount(): void
    {
        $this->storeOptions = Store::optionsForSelect((int) Auth::user()->business_id);

        $defaultStoreId = array_key_first($this->storeOptions);

        $this->tableFilters = [
            'scope' => [
                'store_id' => $defaultStoreId ? (string) $defaultStoreId : null,
                'year' => (string) now()->year,
                'month' => null,
            ],
        ];

        $this->loadSummary();
    }

    public function loadSummary(): void
    {
        $storeId = $this->selectedStoreId();

        if (! $storeId) {
            $this->summary = [
                'year' => $this->selectedYear(),
                'month_rows' => 0,
                'distinct_items' => 0,
                'total_quantity_suom' => 0,
            ];

            return;
        }

        $this->summary = app(InventoryConsumptionQueryService::class)->yearSummary(
            (int) Auth::user()->business_id,
            $this->selectedYear(),
            $this->selectedMonth(),
            $storeId,
        );
    }

    public function table(Table $table): Table
    {
        $businessId = (int) Auth::user()->business_id;
        $queries = app(InventoryConsumptionQueryService::class);

        return $table
            ->query(fn (): Builder => $queries->itemStoreMonthlySummariesQuery($businessId))
            ->columns([
                TextColumn::make('consumption_month')
                    ->label('Month')
                    ->formatStateUsing(fn (string $state): string => Carbon::parse($state.'-01')->format('F Y'))
                    ->sortable(),

                TextColumn::make('item_name')
                    ->label('Item')
                    ->description(fn ($record): ?string => $record->item_code)
                    ->searchable(['items.name', 'items.code'])
                    ->sortable(),

                TextColumn::make('total_quantity_suom')
                    ->label('Consumed (SUOM)')
                    ->tooltip('Total quantity used in this month')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),
            ])
            ->filters([
                Filter::make('scope')
                    ->form([
                        Select::make('store_id')
                            ->label('Store')
                            ->options($this->storeOptions)
                            ->required()
                            ->native(false)
                            ->live(),
                        Select::make('year')
                            ->label('Year')
                            ->options($this->yearOptions())
                            ->required()
                            ->native(false)
                            ->live(),
                        Select::make('month')
                            ->label('Month (optional)')
                            ->options($this->monthOptions())
                            ->placeholder('All months')
                            ->nullable()
                            ->native(false)
                            ->live(),
                    ])
                    ->baseQuery(function (Builder $query, array $data): Builder {
                        $service = app(InventoryConsumptionQueryService::class);

                        $storeId = filled($data['store_id'] ?? null) ? (int) $data['store_id'] : null;

                        if (! $storeId) {
                            return $query->whereRaw('0 = 1');
                        }

                        $year = (int) ($data['year'] ?? now()->year);
                        $month = filled($data['month'] ?? null) ? (int) $data['month'] : null;

                        [$from, $until] = $service->yearMonthBounds($year, $month);
                        [$monthFrom, $monthUntil] = $service->monthRangeBounds($from, $until);

                        return $query
                            ->where('imc.store_id', $storeId)
                            ->whereBetween('imc.consumption_month', [$monthFrom, $monthUntil]);
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['store_id'] ?? null) {
                            $indicators[] = $this->storeOptions[(string) $data['store_id']] ?? 'Store';
                        }

                        if ($data['year'] ?? null) {
                            $indicators[] = 'Year '.$data['year'];
                        }

                        if (filled($data['month'] ?? null)) {
                            $indicators[] = Carbon::create((int) ($data['year'] ?? now()->year), (int) $data['month'], 1)->format('F');
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
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
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading($this->selectedStoreId() ? 'No consumption for this store' : 'Select a store')
            ->emptyStateDescription($this->selectedStoreId()
                ? 'Monthly usage per item will appear here once goods are consumed at this store.'
                : 'Choose a store in the filters above to view monthly consumption.');
    }

    public function updatedTableFilters(): void
    {
        $this->loadSummary();
        $this->handleTableFilterUpdates();
    }

    public function render(): View
    {
        return view('livewire.inventory.list-daily-consumptions');
    }

    public function getTableRecordKey(Model $record): string
    {
        return $record->store_id.'-'.$record->item_id.'-'.$record->consumption_month;
    }

    public function selectedStoreLabel(): ?string
    {
        $storeId = $this->selectedStoreId();

        if (! $storeId) {
            return null;
        }

        return $this->storeOptions[(string) $storeId] ?? null;
    }

    private function selectedYear(): int
    {
        $year = $this->scopeFilterData()['year'] ?? now()->year;

        return (int) $year;
    }

    private function selectedMonth(): ?int
    {
        $month = $this->scopeFilterData()['month'] ?? null;

        return filled($month) ? (int) $month : null;
    }

    private function selectedStoreId(): ?int
    {
        $storeId = $this->scopeFilterData()['store_id'] ?? null;

        return filled($storeId) ? (int) $storeId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function scopeFilterData(): array
    {
        return $this->tableFilters['scope'] ?? [];
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
