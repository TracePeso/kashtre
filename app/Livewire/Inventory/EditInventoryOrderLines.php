<?php

namespace App\Livewire\Inventory;

use App\Models\Group;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\SubGroup;
use App\Services\Inventory\InventoryOrderService;
use App\Services\Inventory\InventoryStockAnalyticsService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditInventoryOrderLines extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public InventoryOrder $order;

    public function mount(InventoryOrder $order): void
    {
        if ((int) $order->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }

        $this->order = $order;
    }

    public function table(Table $table): Table
    {
        $isDraft = $this->order->isDraft();
        $service = app(InventoryOrderService::class);
        $analytics = app(InventoryStockAnalyticsService::class);
        $config = InventoryModuleConfig::query()
            ->forBusiness((int) $this->order->business_id)
            ->active()
            ->first();
        $businessId = (int) $this->order->business_id;

        return $table
            ->query(
                InventoryOrderLine::query()
                    ->where('inventory_order_id', $this->order->id)
                    ->with(['item.itemUnit', 'item.orderUnit', 'item.suppliers', 'item.group', 'item.subgroup'])
            )
            ->columns([
                TextColumn::make('item.group.name')
                    ->label('Group')
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->leftJoin('items', 'items.id', '=', 'inventory_order_lines.item_id')
                        ->leftJoin('groups', 'groups.id', '=', 'items.group_id')
                        ->orderBy('groups.name', $direction)
                        ->select('inventory_order_lines.*')),

                TextColumn::make('item.subgroup.name')
                    ->label('Subgroup')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('item.importance_category')
                    ->label('Importance')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Item::IMPORTANCE_ESSENTIAL => 'Essential',
                        Item::IMPORTANCE_NON_ESSENTIAL => 'Non-essential',
                        default => '—',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        Item::IMPORTANCE_ESSENTIAL => 'success',
                        Item::IMPORTANCE_NON_ESSENTIAL => 'gray',
                        default => 'warning',
                    })
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->leftJoin('items', 'items.id', '=', 'inventory_order_lines.item_id')
                        ->orderBy('items.importance_category', $direction)
                        ->select('inventory_order_lines.*')),

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
                        ->select('inventory_order_lines.*')),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('—'),

                TextColumn::make('days_left_am')
                    ->label('Days left')
                    ->alignEnd()
                    ->state(function (InventoryOrderLine $record) use ($analytics, $config): ?float {
                        $stock = $this->stockLevelForLine($record);

                        return $stock ? $analytics->daysLeftToOrder($stock, $config, $this->order) : null;
                    })
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 1) : '—'),

                TextColumn::make('notify_date')
                    ->label('Notify date')
                    ->state(function (InventoryOrderLine $record) use ($analytics, $config): ?string {
                        $stock = $this->stockLevelForLine($record);

                        return $stock ? $analytics->orderingNotificationDate($stock, $config, $this->order)?->format('M d, Y') : null;
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('lead_time_days')
                    ->label('Lead time')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => (int) $state.'d')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('system_quantity_suom')
                    ->label('System (AR)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('base_suggested_quantity_suom')
                    ->label('Base suggested')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 0)),

                TextInputColumn::make('peak_consumption_increase_percent')
                    ->label('Peak consumption +%')
                    ->type('number')
                    ->alignEnd()
                    ->step('0.01')
                    ->disabled(! $isDraft)
                    ->updateStateUsing(function (InventoryOrderLine $record, $state) use ($service, $isDraft) {
                        if (! $isDraft) {
                            return $state;
                        }

                        $service->updateLinePeakIncrease($record, (float) ($state ?? 0));
                        $this->order->refresh();

                        return $state;
                    }),

                TextColumn::make('peak_impact_percent')
                    ->label('Peak impact %')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 2).'%')
                    ->description(fn (InventoryOrderLine $record): ?string => (float) ($record->peak_impact_percent ?? 0) > 0
                        ? 'Applied to base suggested qty'
                        : null),

                TextColumn::make('suggested_quantity_suom')
                    ->label('Suggested')
                    ->alignEnd()
                    ->sortable()
                    ->color('gray')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextInputColumn::make('order_quantity_suom')
                    ->label('Order (SUOM)')
                    ->type('number')
                    ->alignEnd()
                    ->step('1')
                    ->disabled(! $isDraft)
                    ->updateStateUsing(function (InventoryOrderLine $record, $state) use ($service, $isDraft) {
                        if (! $isDraft) {
                            return $state;
                        }

                        $qty = (float) ($state ?? 0);
                        $ouom = null;
                        $item = $record->item;

                        if ($item && $item->suom_per_ouom && (float) $item->suom_per_ouom > 0) {
                            $ouom = round($qty / (float) $item->suom_per_ouom, 4);
                        }

                        $service->updateLine($record, $qty, $ouom);
                        $this->order->refresh();

                        return $state;
                    }),

                TextColumn::make('received_quantity_suom')
                    ->label('Received')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 0))
                    ->description(fn (InventoryOrderLine $record): ?string => (float) $record->order_quantity_suom > 0
                        ? round(((float) $record->received_quantity_suom / (float) $record->order_quantity_suom) * 100, 1).'% of order'
                        : null),

                TextColumn::make('remaining_quantity_suom')
                    ->label('Remaining')
                    ->alignEnd()
                    ->state(fn (InventoryOrderLine $record): float => max(0, (float) $record->order_quantity_suom - (float) $record->received_quantity_suom))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0))
                    ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'success'),

                TextColumn::make('order_ouom_display')
                    ->label('Order (OUOM)')
                    ->alignEnd()
                    ->state(function (InventoryOrderLine $record): ?string {
                        $qty = (float) $record->order_quantity_suom;
                        $item = $record->item;

                        if (! $item || ! $item->suom_per_ouom || (float) $item->suom_per_ouom <= 0) {
                            return null;
                        }

                        $ouom = round($qty / (float) $item->suom_per_ouom, 2);

                        return number_format($ouom, 2).' '.($item->orderUnit?->name ?? 'OUOM');
                    })
                    ->placeholder('—'),

                TextColumn::make('unit_price')
                    ->label('Unit price')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) ($state ?? 0), 2)),

                TextColumn::make('line_total')
                    ->label('Line total')
                    ->alignEnd()
                    ->weight('medium')
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) ($state ?? 0), 2)),
            ])
            ->filters([
                SelectFilter::make('importance')
                    ->label('Importance')
                    ->options(Item::importanceOptions())
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
            ])
            ->filtersFormColumns(3)
            ->defaultSort('item.name')
            ->paginated(false)
            ->striped()
            ->emptyStateHeading('No order lines')
            ->emptyStateDescription(fn (): string => app(InventoryOrderService::class)->explainEmptyOrder($this->order));
    }

    public function render(): View
    {
        return view('livewire.inventory.edit-inventory-order-lines');
    }

    private function stockLevelForLine(InventoryOrderLine $record): ?InventoryStockLevel
    {
        return InventoryStockLevel::query()
            ->where('business_id', $this->order->business_id)
            ->where('store_id', $this->order->store_id)
            ->where('item_id', $record->item_id)
            ->first();
    }
}
