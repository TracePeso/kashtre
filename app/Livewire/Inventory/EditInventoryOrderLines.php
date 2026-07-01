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
use App\Models\Supplier;
use App\Services\Inventory\InventoryOrderService;
use App\Services\Inventory\InventoryStockAnalyticsService;
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

    public ?int $supplierId = null;

    /** @var array<int, string> */
    public array $supplierOptions = [];

    /** @var Collection<int, InventoryStockLevel>|null */
    private ?Collection $stockByItemId = null;

    private ?InventoryModuleConfig $orderModuleConfig = null;

    public function mount(InventoryOrder $order): void
    {
        if ((int) $order->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }

        $this->order = $order;
        $this->budgetCapEnforced = (bool) ($order->budget_cap_enforced ?? true);
        $this->supplierId = $order->supplier_id ? (int) $order->supplier_id : null;
        $this->supplierOptions = Supplier::query()
            ->where('business_id', (int) $order->business_id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
        $this->orderModuleConfig = InventoryModuleConfig::query()
            ->forBusiness((int) $order->business_id)
            ->active()
            ->first();
    }

    public function table(Table $table): Table
    {
        $isDraft = $this->order->isDraft();
        $hasPeak = (float) ($this->order->peak_period_percent ?? 0) > 0;
        $showReceipt = ! $isDraft;
        $service = app(InventoryOrderService::class);
        $analytics = app(InventoryStockAnalyticsService::class);
        $config = $this->orderModuleConfig;
        $businessId = (int) $this->order->business_id;
        $stockByItem = $this->stockByItemId();

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
                ->formatStateUsing(fn (?string $state): string => ItemImportanceCategory::labelForSlug((int) $this->order->business_id, $state) ?? '—')
                ->badge()
                ->color('primary')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('suggested_quantity_suom')
                ->label('Suggested')
                ->alignEnd()
                ->sortable()
                ->color('gray')
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

            TextInputColumn::make('order_quantity_suom')
                ->label('Order qty')
                ->type('number')
                ->alignEnd()
                ->step('1')
                ->disabled(! $isDraft)
                ->updateStateUsing(function (InventoryOrderLine $record, $state) use ($service, $isDraft) {
                    if (! $isDraft) {
                        return $state;
                    }

                    $requestedQty = (float) ($state ?? 0);
                    $updated = $service->updateLine($record, $requestedQty);
                    $this->order->refresh();

                    $actualQty = (float) $updated->order_quantity_suom;

                    if ($this->order->enforcesBudgetCap() && $actualQty < $requestedQty) {
                        $this->showBudgetCapNotice = true;
                    }

                    return $actualQty;
                }),
        ];

        if ($hasPeak) {
            $columns[] = TextInputColumn::make('peak_consumption_increase_percent')
                ->label('Peak +%')
                ->type('number')
                ->alignEnd()
                ->step('0.01')
                ->disabled(! $isDraft)
                ->updateStateUsing(function (InventoryOrderLine $record, $state) use ($service, $isDraft) {
                    if (! $isDraft) {
                        return $state;
                    }

                    $requestedIncrease = (float) ($state ?? 0);
                    $beforeQty = (float) $record->order_quantity_suom;
                    $updated = $service->updateLinePeakIncrease($record, $requestedIncrease);
                    $this->order->refresh();

                    if ($this->order->enforcesBudgetCap() && (float) $updated->order_quantity_suom < $beforeQty) {
                        $this->showBudgetCapNotice = true;
                    }

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
                ->label('Unit price')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) ($state ?? 0), 2)),

            TextColumn::make('line_total')
                ->label('Item total')
                ->alignEnd()
                ->weight('medium')
                ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) ($state ?? 0), 2)),

            TextColumn::make('days_left_am')
                ->label('Days left')
                ->alignEnd()
                ->toggleable(isToggledHiddenByDefault: true)
                ->state(function (InventoryOrderLine $record) use ($analytics, $config, $stockByItem): ?float {
                    $stock = $stockByItem->get($record->item_id);

                    return $stock ? $analytics->daysLeftToOrder($stock, $config, $this->order) : null;
                })
                ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 1) : '—'),

            TextColumn::make('system_quantity_suom')
                ->label('System stock')
                ->alignEnd()
                ->toggleable(isToggledHiddenByDefault: true)
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),
        ]);

        return $table
            ->query(
                InventoryOrderLine::query()
                    ->where('inventory_order_id', $this->order->id)
                    ->with(['item.itemUnit', 'item.orderUnit', 'item.suppliers', 'item.group', 'item.subgroup', 'supplier'])
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

    public function updatedSupplierId($value): void
    {
        if (! $this->order->isDraft() || ! filled($value)) {
            return;
        }

        try {
            $this->order = app(InventoryOrderService::class)->setOrderSupplier(
                $this->order,
                (int) $value
            );
            $this->supplierId = (int) $this->order->supplier_id;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('supplierId', collect($e->errors())->flatten()->first() ?? 'Invalid supplier.');
            $this->supplierId = $this->order->supplier_id ? (int) $this->order->supplier_id : null;
        }
    }

    public function toggleBudgetCapEnforced(): void
    {
        if (! $this->order->isDraft()) {
            return;
        }

        if ($this->order->effectiveAmountCap() === null) {
            return;
        }

        $this->budgetCapEnforced = ! $this->budgetCapEnforced;
        $this->showBudgetCapNotice = false;
        $this->order = app(InventoryOrderService::class)->setBudgetCapEnforced(
            $this->order,
            $this->budgetCapEnforced
        );
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
}
