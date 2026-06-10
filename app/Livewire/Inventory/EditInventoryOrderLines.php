<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryStockLevel;
use App\Services\Inventory\InventoryOrderService;
use App\Services\Inventory\InventoryStockAnalyticsService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
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

        return $table
            ->query(
                InventoryOrderLine::query()
                    ->where('inventory_order_id', $this->order->id)
                    ->with(['item.itemUnit', 'item.orderUnit', 'supplier'])
            )
            ->columns([
                TextColumn::make('item.name')
                    ->label('Item')
                    ->description(fn (InventoryOrderLine $record): ?string => $record->item?->code),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('—'),

                TextColumn::make('days_left_am')
                    ->label('Days left')
                    ->alignEnd()
                    ->state(function (InventoryOrderLine $record) use ($analytics, $config): ?float {
                        $stock = $this->stockLevelForLine($record);

                        return $stock ? $analytics->daysLeftToOrder($stock, $config) : null;
                    })
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 1) : '—'),

                TextColumn::make('notify_date')
                    ->label('Notify date')
                    ->state(function (InventoryOrderLine $record) use ($analytics, $config): ?string {
                        $stock = $this->stockLevelForLine($record);

                        return $stock ? $analytics->orderingNotificationDate($stock, $config)?->format('M d, Y') : null;
                    })
                    ->placeholder('—'),

                TextColumn::make('lead_time_days')
                    ->label('Lead time')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => (int) $state.'d'),

                TextColumn::make('daily_average_suom')
                    ->label('Daily avg')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 4)),

                TextColumn::make('system_quantity_suom')
                    ->label('System (AR)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('suggested_quantity_suom')
                    ->label('Suggested')
                    ->alignEnd()
                    ->color('gray')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextInputColumn::make('order_quantity_suom')
                    ->label('Order (SUOM)')
                    ->type('number')
                    ->alignEnd()
                    ->step('0.0001')
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
