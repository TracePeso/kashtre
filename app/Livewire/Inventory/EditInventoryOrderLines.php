<?php

namespace App\Livewire\Inventory;

use App\Models\Group;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\ItemImportanceCategory;
use App\Models\SubGroup;
use App\Services\Inventory\InventoryOrderService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditInventoryOrderLines extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public InventoryOrder $order;

    public bool $budgetCapEnforced = true;

    public bool $showBudgetCapNotice = false;

    public string $budgetCapNotice = '';

    /** Quantity Mode (default) vs Days Mode. */
    public string $editMode = 'quantity';

    /**
     * Original vs adjusted line snapshot after an edit.
     *
     * @var array{
     *     edited_line_id: int,
     *     cap: ?float,
     *     capped: bool,
     *     order_total_before: float,
     *     order_total_after: float,
     *     lines: list<array<string, mixed>>
     * }|null
     */
    public ?array $capAdjustmentComparison = null;

    /** @var Collection<int, InventoryStockLevel>|null */
    private ?Collection $stockByItemId = null;

    /** @var array<string, string>|null */
    private ?array $importanceLabels = null;

    private ?InventoryModuleConfig $orderModuleConfig = null;

    public function mount(InventoryOrder $order): void
    {
        if ((int) $order->business_id !== (int) \App\Support\InventoryBusinessContext::effectiveBusinessId()) {
            abort(403);
        }

        $this->order = $order;
        $this->budgetCapEnforced = (bool) ($order->budget_cap_enforced ?? true);
        $this->orderModuleConfig = InventoryModuleConfig::query()
            ->forBusiness((int) $order->business_id)
            ->active()
            ->first();
    }

    public function table(Table $table): Table
    {
        $isDraft = $this->order->isDraft();
        $writable = $isDraft && ! \App\Support\InventoryBusinessContext::isAdminBrowsing();
        $hasPeak = (float) ($this->order->peak_period_percent ?? 0) > 0;
        $showReceipt = ! $isDraft;
        $service = app(InventoryOrderService::class);
        $businessId = (int) $this->order->business_id;

        $columns = [
            TextColumn::make('item.name')
                ->label('Item')
                ->description(fn (InventoryOrderLine $record): ?string => $record->item?->code)
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('item', fn (Builder $q) => $q
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
                })
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                    ->leftJoin('items', 'items.id', '=', 'inventory_order_lines.item_id')
                    ->orderBy('items.name', $direction)
                    ->select('inventory_order_lines.*'))
                ->wrap(),

            TextColumn::make('item.group.name')
                ->label('Group')
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                    ->leftJoin('items', 'items.id', '=', 'inventory_order_lines.item_id')
                    ->leftJoin('groups', 'groups.id', '=', 'items.group_id')
                    ->orderBy('groups.name', $direction)
                    ->select('inventory_order_lines.*')),

            TextColumn::make('item.importance_category')
                ->label('Importance')
                ->formatStateUsing(fn (?string $state): string => $state
                    ? ($this->importanceLabels()[$state] ?? $state)
                    : '—')
                ->badge()
                ->color('primary')
                ->toggleable(isToggledHiddenByDefault: true),

            TextInputColumn::make('order_quantity_suom')
                ->label('Order qty')
                ->type('number')
                ->alignEnd()
                ->step('1')
                ->disabled(! $writable || $this->editMode === 'days')
                ->visible($this->editMode === 'quantity')
                ->updateStateUsing(function (InventoryOrderLine $record, $state) use ($service, $writable) {
                    if (! $writable) {
                        return $state;
                    }

                    \App\Support\InventoryBusinessContext::assertWritable();

                    $requestedQty = (float) ($state ?? 0);
                    $result = $service->applyLineQuantityUpdate($record, $requestedQty, null, true);
                    $this->order->refresh();

                    $actualQty = (float) $result['line']->order_quantity_suom;
                    $this->applyCapAdjustmentFeedback(
                        $result,
                        $this->order->enforcesBudgetCap() && $actualQty + 0.0001 < $requestedQty
                            ? 'Quantity limited so this line alone does not exceed the order cap.'
                            : null,
                        'This line was updated and 1 other line was adjusted equally so the order total stays at the cap.',
                        'This line was updated and :count other lines were adjusted equally so the order total stays at the cap.'
                    );

                    return $actualQty;
                }),

            TextColumn::make('order_quantity_suom_readonly')
                ->label('Order qty')
                ->alignEnd()
                ->visible($this->editMode === 'days')
                ->state(fn (InventoryOrderLine $record): float => (float) $record->order_quantity_suom)
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

            TextInputColumn::make('order_days')
                ->label('Order days')
                ->type('number')
                ->alignEnd()
                ->step('0.1')
                ->disabled(! $writable || $this->editMode === 'quantity')
                ->visible($this->editMode === 'days')
                ->updateStateUsing(function (InventoryOrderLine $record, $state) use ($service, $writable) {
                    if (! $writable) {
                        return $state;
                    }

                    \App\Support\InventoryBusinessContext::assertWritable();

                    $requestedDays = (float) ($state ?? 0);
                    $beforeQty = (float) $record->order_quantity_suom;
                    $result = $service->applyLineDaysUpdate($record, $requestedDays, true);
                    $this->order->refresh();

                    $this->applyCapAdjustmentFeedback(
                        $result,
                        $this->order->enforcesBudgetCap()
                            && (float) $result['line']->order_quantity_suom + 0.0001 < $beforeQty
                            ? 'Quantity limited so this line alone does not exceed the order cap.'
                            : null,
                        'Days change updated this line and adjusted 1 other line equally so the order total stays at the cap.',
                        'Days change updated this line and adjusted :count other lines equally so the order total stays at the cap.'
                    );

                    return (float) $result['line']->order_days;
                }),
        ];

        if ($hasPeak) {
            $columns[] = TextInputColumn::make('peak_consumption_increase_percent')
                ->label('Peak +%')
                ->type('number')
                ->alignEnd()
                ->step('0.01')
                ->disabled(! $writable)
                ->updateStateUsing(function (InventoryOrderLine $record, $state) use ($service, $writable) {
                    if (! $writable) {
                        return $state;
                    }

                    \App\Support\InventoryBusinessContext::assertWritable();

                    $requestedIncrease = (float) ($state ?? 0);
                    $beforeQty = (float) $record->order_quantity_suom;
                    $result = $service->updateLinePeakIncrease($record, $requestedIncrease);
                    $this->order->refresh();
                    $this->order->load('lines');

                    $this->applyCapAdjustmentFeedback(
                        $result,
                        $this->order->enforcesBudgetCap()
                            && (float) $result['line']->order_quantity_suom + 0.0001 < $beforeQty
                            ? 'Quantity limited so this line alone does not exceed the order cap.'
                            : null,
                        'Peak change updated this line and adjusted 1 other line equally so the order total stays at the cap.',
                        'Peak change updated this line and adjusted :count other lines equally so the order total stays at the cap.'
                    );

                    return $state;
                });
        }

        if ($showReceipt) {
            $columns[] = TextColumn::make('received_quantity_suom')
                ->label('Received')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 0));

            $columns[] = TextColumn::make('remaining_quantity_suom')
                ->label('Remaining')
                ->alignEnd()
                ->state(fn (InventoryOrderLine $record): float => max(0, (float) $record->order_quantity_suom - (float) $record->received_quantity_suom))
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0))
                ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'success');
        }

        $columns = array_merge($columns, [
            TextColumn::make('unit_price')
                ->label('Purchase price')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) ($state ?? 0), 2)),

            TextColumn::make('line_total')
                ->label('Item total')
                ->alignEnd()
                ->weight('medium')
                ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) ($state ?? 0), 2)),

            TextColumn::make('current_stock_suom')
                ->label('Current stock')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0))
                ->description(fn (InventoryOrderLine $record): ?string => $record->stock_days_at_order !== null
                    ? number_format((float) $record->stock_days_at_order, 1).' stock days'
                    : null),

            TextColumn::make('system_quantity_suom')
                ->label('System stock (AR)')
                ->alignEnd()
                ->toggleable(isToggledHiddenByDefault: true)
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),
        ]);

        if (in_array($this->order->budget_mode, [
            InventoryOrder::BUDGET_MODE_DAYS,
            InventoryOrder::BUDGET_MODE_AMOUNT,
        ], true)) {
            $columns[] = TextColumn::make('days_left_at_order')
                ->label('Days left to order')
                ->alignEnd()
                ->placeholder('—')
                ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 1) : '—')
                ->color(fn ($state): ?string => $state !== null && (float) $state <= 0 ? 'danger' : null)
                ->description(fn (InventoryOrderLine $record): ?string => $record->order_days !== null
                    ? number_format((float) $record->order_days, 1).' order days allocated'
                    : null);
        }

        return $table
            ->query(
                InventoryOrderLine::query()
                    ->where('inventory_order_id', $this->order->id)
                    ->with([
                        'item:id,name,code,uom_id,order_unit_id,group_id,subgroup_id,importance_category,purchase_price,default_price,suom_per_ouom',
                        'item.itemUnit:id,name',
                        'item.orderUnit:id,name',
                        'item.group:id,name',
                        'item.subgroup:id,name',
                        'supplier:id,name',
                    ])
            )
            ->columns($columns)
            ->filters([
                SelectFilter::make('importance')
                    ->label('Importance')
                    ->options(Item::importanceOptions((int) $this->order->business_id))
                    ->query(fn (Builder $query, array $data): Builder => $data['value']
                        ? $query->whereHas('item', fn (Builder $q) => $q->where('importance_category', $data['value']))
                        : $query),
                SelectFilter::make('group_id')
                    ->label('Group')
                    ->options(fn (): array => Group::query()
                        ->where('business_id', $businessId)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $data['value']
                        ? $query->whereHas('item', fn (Builder $q) => $q->where('group_id', $data['value']))
                        : $query),
                SelectFilter::make('subgroup_id')
                    ->label('Subgroup')
                    ->options(fn (): array => SubGroup::query()
                        ->where('business_id', $businessId)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $data['value']
                        ? $query->whereHas('item', fn (Builder $q) => $q->where('subgroup_id', $data['value']))
                        : $query),
            ], layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->defaultSort('item.name')
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100])
            ->striped()
            ->emptyStateHeading('No order items')
            ->emptyStateDescription(fn (): string => app(InventoryOrderService::class)->explainEmptyOrder($this->order));
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }

    public function toggleBudgetCapEnforced(): void
    {
        if (! $this->order->isDraft() || \App\Support\InventoryBusinessContext::isAdminBrowsing()) {
            return;
        }

        \App\Support\InventoryBusinessContext::assertWritable();

        if ($this->order->effectiveAmountCap() === null) {
            return;
        }

        $this->budgetCapEnforced = ! $this->budgetCapEnforced;
        $this->dismissCapAdjustmentComparison();
        $this->order = app(InventoryOrderService::class)->setBudgetCapEnforced(
            $this->order,
            $this->budgetCapEnforced
        );
        $this->resetTable();
    }

    public function setEditMode(string $mode): void
    {
        if (! in_array($mode, ['quantity', 'days'], true) || $this->editMode === $mode) {
            return;
        }

        $this->editMode = $mode;
        $this->dismissCapAdjustmentComparison();
        $this->resetTable();
    }

    public function dismissCapAdjustmentComparison(): void
    {
        $this->capAdjustmentComparison = null;
        $this->showBudgetCapNotice = false;
        $this->budgetCapNotice = '';
    }

    /**
     * @param  array{redistributed: bool, adjusted_count: int, comparison: ?array}  $result
     */
    private function applyCapAdjustmentFeedback(
        array $result,
        ?string $limitedAloneMessage,
        string $redistributedSingular,
        string $redistributedPlural
    ): void {
        $comparison = $result['comparison'] ?? null;
        $hasChanges = is_array($comparison)
            && collect($comparison['lines'] ?? [])->contains(fn (array $row) => (bool) ($row['changed'] ?? false));

        if ($hasChanges) {
            $this->capAdjustmentComparison = $comparison;
            $this->showBudgetCapNotice = true;
            if ($result['redistributed'] && (int) ($result['adjusted_count'] ?? 0) > 0) {
                $count = (int) $result['adjusted_count'];
                $this->budgetCapNotice = $count === 1
                    ? $redistributedSingular
                    : str_replace(':count', (string) $count, $redistributedPlural);
            } elseif ($limitedAloneMessage) {
                $this->budgetCapNotice = $limitedAloneMessage;
            } elseif (! ($comparison['capped'] ?? true)) {
                $this->budgetCapNotice = 'This line was updated. Original vs adjusted values for all lines are shown below.';
            } else {
                $this->budgetCapNotice = 'Line quantities were updated. Original vs adjusted values are shown below.';
            }
            $this->resetTable();

            return;
        }

        $this->capAdjustmentComparison = null;
        if ($limitedAloneMessage) {
            $this->showBudgetCapNotice = true;
            $this->budgetCapNotice = $limitedAloneMessage;
        } else {
            $this->showBudgetCapNotice = false;
            $this->budgetCapNotice = '';
        }
    }

    public function render(): View
    {
        return view('livewire.inventory.edit-inventory-order-lines');
    }

    /** @return Collection<int, InventoryStockLevel> */
    private function stockByItemId(): Collection
    {
        if ($this->stockByItemId === null) {
            $this->stockByItemId = InventoryStockLevel::query()
                ->where('business_id', $this->order->business_id)
                ->where('store_id', $this->order->store_id)
                ->get()
                ->keyBy('item_id');
        }

        return $this->stockByItemId;
    }

    /**
     * @return array<string, string>
     */
    private function importanceLabels(): array
    {
        if ($this->importanceLabels !== null) {
            return $this->importanceLabels;
        }

        return $this->importanceLabels = ItemImportanceCategory::optionsForBusiness(
            (int) $this->order->business_id
        );
    }
}
