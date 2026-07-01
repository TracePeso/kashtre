<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\Store;
use App\Services\Inventory\InventoryConsumptionQueryService;
use App\Services\Inventory\InventoryConsumptionSampleDataService;
use Carbon\Carbon;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
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

    public const DEFAULT_PERIOD_DAYS = 10;

    public ?int $storeId = null;

    public ?int $itemId = null;

    public string $periodPreset = '10';

    public ?string $dateFrom = null;

    public ?string $dateUntil = null;

    /** @var array<string, mixed> */
    public array $summary = [
        'from' => '',
        'until' => '',
        'period_days' => self::DEFAULT_PERIOD_DAYS,
        'item_day_rows' => 0,
        'distinct_items' => 0,
        'total_quantity_suom' => 0,
    ];

    /** @var array<int, string> */
    public array $storeOptions = [];

    /** @var array<int, string> */
    public array $itemOptions = [];

    public ?string $generateMessage = null;

    public string $generateMessageType = 'success';

    public function mount(): void
    {
        $this->storeOptions = Store::optionsForSelect((int) \App\Support\InventoryBusinessContext::effectiveBusinessId());
        $this->storeId = $this->resolveDefaultStoreId();
        $this->syncPeriodDates();
        $this->refreshItemOptions();
        $this->loadSummary();
    }

    public function updatedStoreId(): void
    {
        $this->itemId = null;
        $this->refreshItemOptions();
        $this->loadSummary();
        $this->resetTable();
    }

    public function updatedItemId(): void
    {
        $this->loadSummary();
        $this->resetTable();
    }

    public function updatedPeriodPreset(): void
    {
        $this->syncPeriodDates();
        $this->refreshItemOptions();
        $this->loadSummary();
        $this->resetTable();
    }

    public function updatedDateFrom(): void
    {
        if ($this->periodPreset !== 'custom') {
            return;
        }

        $this->refreshItemOptions();
        $this->loadSummary();
        $this->resetTable();
    }

    public function updatedDateUntil(): void
    {
        if ($this->periodPreset !== 'custom') {
            return;
        }

        $this->refreshItemOptions();
        $this->loadSummary();
        $this->resetTable();
    }

    public function generateTestData(): void
    {
        \App\Support\InventoryBusinessContext::assertWritable();

        if (! $this->storeId) {
            $this->generateMessage = 'Select a store first.';
            $this->generateMessageType = 'error';

            return;
        }

        $result = app(InventoryConsumptionSampleDataService::class)->backfillToToday(
            (int) \App\Support\InventoryBusinessContext::effectiveBusinessId(),
            $this->storeId,
            (int) Auth::id(),
        );

        $this->generateMessage = $result['message'];
        $this->generateMessageType = $result['success'] ? 'success' : 'warning';

        if ($result['success']) {
            $this->refreshItemOptions();
            $this->loadSummary();
            $this->resetTable();
        }
    }

    public function loadSummary(): void
    {
        [$from, $until] = $this->periodBounds();

        if (! $this->storeId) {
            $this->summary = [
                'from' => $from,
                'until' => $until,
                'period_days' => app(InventoryConsumptionQueryService::class)->periodDays($from, $until),
                'item_day_rows' => 0,
                'distinct_items' => 0,
                'total_quantity_suom' => 0,
            ];

            return;
        }

        $this->summary = app(InventoryConsumptionQueryService::class)->periodSummary(
            (int) \App\Support\InventoryBusinessContext::effectiveBusinessId(),
            $from,
            $until,
            $this->storeId,
            $this->itemId,
        );
    }

    public function table(Table $table): Table
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        [$from, $until] = $this->periodBounds();

        return $table
            ->query(function () use ($businessId, $from, $until): Builder {
                if (! $this->storeId) {
                    return InventoryDailyConsumption::query()->whereRaw('0 = 1');
                }

                return app(InventoryConsumptionQueryService::class)->itemStoreDailySummariesQuery(
                    $businessId,
                    $from,
                    $until,
                    $this->storeId,
                    $this->itemId,
                );
            })
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
                    ->label('Consumed')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),
            ])
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
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading($this->storeId ? 'No consumption in this period' : 'Select a store')
            ->emptyStateDescription($this->storeId
                ? 'Try a wider date range or clear the item filter.'
                : 'Choose a store to view consumption.');
    }

    public function render(): View
    {
        return view('livewire.inventory.list-daily-consumptions', [
            'periodPresets' => InventoryConsumptionQueryService::periodPresetOptions(),
        ]);
    }

    public function getTableRecordKey(Model $record): string
    {
        return $record->store_id.'-'.$record->item_id.'-'.Carbon::parse($record->consumption_date)->toDateString();
    }

    public function periodLabel(): string
    {
        [$from, $until] = $this->periodBounds();

        return Carbon::parse($from)->format('M j').' – '.Carbon::parse($until)->format('M j, Y');
    }

    public function periodPresetLabel(): string
    {
        return InventoryConsumptionQueryService::periodPresetOptions()[$this->periodPreset] ?? 'Custom';
    }

    public function showTestDataButton(): bool
    {
        return ! \App\Support\InventoryBusinessContext::isAdminBrowsing()
            && (app()->environment('local') || (bool) config('app.debug'));
    }

    public function backfillRangeLabel(): ?string
    {
        if (! $this->storeId) {
            return null;
        }

        $range = app(InventoryConsumptionSampleDataService::class)->pendingBackfillRange(
            (int) \App\Support\InventoryBusinessContext::effectiveBusinessId(),
            $this->storeId,
        );

        if ($range['already_current']) {
            return 'Up to date through today';
        }

        return Carbon::parse($range['from'])->format('M j').' → '.Carbon::parse($range['until'])->format('M j, Y');
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function periodBounds(): array
    {
        if ($this->periodPreset === 'custom') {
            $until = $this->dateUntil ?: now()->toDateString();
            $from = $this->dateFrom ?: $until;

            if (Carbon::parse($from)->gt(Carbon::parse($until))) {
                [$from, $until] = [$until, $from];
            }

            return [$from, $until];
        }

        return app(InventoryConsumptionQueryService::class)->recentDaysBounds((int) $this->periodPreset);
    }

    private function syncPeriodDates(): void
    {
        if ($this->periodPreset === 'custom') {
            $this->dateUntil ??= now()->toDateString();
            $this->dateFrom ??= now()->subDays(self::DEFAULT_PERIOD_DAYS - 1)->toDateString();

            return;
        }

        [$from, $until] = app(InventoryConsumptionQueryService::class)->recentDaysBounds((int) $this->periodPreset);
        $this->dateFrom = $from;
        $this->dateUntil = $until;
    }

    private function refreshItemOptions(): void
    {
        if (! $this->storeId) {
            $this->itemOptions = [];

            return;
        }

        [$from, $until] = $this->periodBounds();

        $this->itemOptions = app(InventoryConsumptionQueryService::class)->itemOptionsForPeriod(
            (int) \App\Support\InventoryBusinessContext::effectiveBusinessId(),
            $this->storeId,
            $from,
            $until,
        );

        if ($this->itemId && ! array_key_exists($this->itemId, $this->itemOptions)) {
            $this->itemId = null;
        }
    }

    private function resolveDefaultStoreId(): ?int
    {
        $businessId = (int) \App\Support\InventoryBusinessContext::effectiveBusinessId();
        $user = Auth::user();

        if ($user->default_store_id && isset($this->storeOptions[(string) $user->default_store_id])) {
            return (int) $user->default_store_id;
        }

        if ($user->branch_id) {
            $branchStoreId = Store::query()
                ->forBusiness($businessId)
                ->where('branch_id', $user->branch_id)
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->value('id');

            if ($branchStoreId && isset($this->storeOptions[(string) $branchStoreId])) {
                return (int) $branchStoreId;
            }
        }

        $first = array_key_first($this->storeOptions);

        return $first ? (int) $first : null;
    }
}
