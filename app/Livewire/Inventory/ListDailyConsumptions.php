<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryStockLevel;
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

    public const RECENT_DAYS = 10;

    /** @var array<string, mixed> */
    public array $summary = [
        'from' => '',
        'until' => '',
        'period_days' => self::RECENT_DAYS,
        'item_day_rows' => 0,
        'distinct_items' => 0,
        'total_quantity_suom' => 0,
    ];

    /** @var array<string, string> */
    public array $storeOptions = [];

    public function mount(): void
    {
        $this->storeOptions = Store::optionsForSelect((int) Auth::user()->business_id);

        $defaultStoreId = $this->resolveDefaultStoreId();

        $this->tableFilters = [
            'scope' => [
                'store_id' => $defaultStoreId ? (string) $defaultStoreId : null,
            ],
        ];

        $this->loadSummary();
    }

    public function loadSummary(): void
    {
        $storeId = $this->selectedStoreId();
        [$from, $until] = $this->periodBounds();

        if (! $storeId) {
            $this->summary = [
                'from' => $from,
                'until' => $until,
                'period_days' => self::RECENT_DAYS,
                'item_day_rows' => 0,
                'distinct_items' => 0,
                'total_quantity_suom' => 0,
            ];

            return;
        }

        $this->summary = app(InventoryConsumptionQueryService::class)->periodSummary(
            (int) Auth::user()->business_id,
            $from,
            $until,
            $storeId,
        );
    }

    public function table(Table $table): Table
    {
        $businessId = (int) Auth::user()->business_id;
        $queries = app(InventoryConsumptionQueryService::class);
        [$from, $until] = $this->periodBounds();

        return $table
            ->query(fn (): Builder => $queries->itemStoreDailySummariesQuery($businessId, $from, $until))
            ->columns([
                TextColumn::make('consumption_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('item_name')
                    ->label('Item')
                    ->description(fn ($record): ?string => $record->item_code)
                    ->searchable(['items.name', 'items.code'])
                    ->sortable(),

                TextColumn::make('total_quantity_suom')
                    ->label('Consumed (SUOM)')
                    ->tooltip('Quantity used on this day')
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
                    ])
                    ->baseQuery(function (Builder $query, array $data): Builder {
                        $storeId = filled($data['store_id'] ?? null) ? (int) $data['store_id'] : null;

                        if (! $storeId) {
                            return $query->whereRaw('0 = 1');
                        }

                        return $query->where('idc.store_id', $storeId);
                    })
                    ->indicateUsing(function (array $data): array {
                        if ($data['store_id'] ?? null) {
                            return [$this->storeOptions[(string) $data['store_id']] ?? 'Store'];
                        }

                        return [];
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->actions([
                Action::make('view_day')
                    ->label('Hourly breakdown')
                    ->icon('heroicon-o-clock')
                    ->url(fn ($record): string => route('inventory.consumption.day', [
                        'item' => $record->item_id,
                        'date' => Carbon::parse($record->consumption_date)->toDateString(),
                        'store_id' => $record->store_id,
                    ])),
            ])
            ->defaultSort('consumption_date', 'desc')
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading($this->selectedStoreId() ? 'No consumption in the last '.self::RECENT_DAYS.' days' : 'Select a store')
            ->emptyStateDescription($this->selectedStoreId()
                ? 'Daily usage per item will appear here once goods are consumed at this store.'
                : 'Choose a store in the filter above to view recent consumption.');
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
        return $record->store_id.'-'.$record->item_id.'-'.Carbon::parse($record->consumption_date)->toDateString();
    }

    public function selectedStoreLabel(): ?string
    {
        $storeId = $this->selectedStoreId();

        if (! $storeId) {
            return null;
        }

        return $this->storeOptions[(string) $storeId] ?? null;
    }

    public function periodLabel(): string
    {
        [$from, $until] = $this->periodBounds();

        return Carbon::parse($from)->format('M j').' – '.Carbon::parse($until)->format('M j, Y');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function periodBounds(): array
    {
        return app(InventoryConsumptionQueryService::class)->recentDaysBounds(self::RECENT_DAYS);
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

    private function resolveDefaultStoreId(): ?int
    {
        $businessId = (int) Auth::user()->business_id;
        $user = Auth::user();

        if ($user->default_store_id) {
            $exists = Store::query()
                ->forBusiness($businessId)
                ->whereKey($user->default_store_id)
                ->exists();

            if ($exists) {
                return (int) $user->default_store_id;
            }
        }

        if ($user->branch_id) {
            $branchStoreId = Store::query()
                ->forBusiness($businessId)
                ->where('branch_id', $user->branch_id)
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->value('id');

            if ($branchStoreId) {
                return (int) $branchStoreId;
            }
        }

        $stockStoreId = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where(function ($query) {
                $query->where('quantity_suom', '>', 0)
                    ->orWhere('ma_15_days', '>', 0)
                    ->orWhere('ma_30_days', '>', 0);
            })
            ->orderByDesc('quantity_suom')
            ->value('store_id');

        if ($stockStoreId) {
            return (int) $stockStoreId;
        }

        $first = array_key_first($this->storeOptions);

        return $first ? (int) $first : null;
    }
}
