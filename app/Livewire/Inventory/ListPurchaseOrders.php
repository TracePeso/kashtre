<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryPurchaseOrder;
use App\Support\InventoryBusinessContext;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ListPurchaseOrders extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryPurchaseOrder::query()
                    ->select([
                        'id',
                        'business_id',
                        'inventory_order_id',
                        'supplier_id',
                        'store_id',
                        'po_number',
                        'status',
                        'total_amount',
                        'issued_at',
                        'created_at',
                    ])
                    ->where('business_id', InventoryBusinessContext::effectiveBusinessId())
                    ->with([
                        'supplier:id,name',
                        'store:id,name',
                        'inventoryOrder:id,order_number',
                    ])
                    ->latest('created_at')
            )
            ->columns([
                TextColumn::make('po_number')
                    ->label('LPO #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('inventoryOrder.order_number')
                    ->label('RFQ')
                    ->url(fn (InventoryPurchaseOrder $record): ?string => $record->inventory_order_id
                        ? route('inventory.orders.show', $record->inventory_order_id)
                        : null)
                    ->color('primary')
                    ->placeholder('—'),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (InventoryPurchaseOrder $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        InventoryPurchaseOrder::STATUS_DRAFT => 'warning',
                        InventoryPurchaseOrder::STATUS_ISSUED => 'info',
                        InventoryPurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'primary',
                        InventoryPurchaseOrder::STATUS_FULFILLED => 'success',
                        InventoryPurchaseOrder::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2)),

                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->dateTime('M d, Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        InventoryPurchaseOrder::STATUS_DRAFT => 'Draft',
                        InventoryPurchaseOrder::STATUS_ISSUED => 'Issued',
                        InventoryPurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'Partially received',
                        InventoryPurchaseOrder::STATUS_FULFILLED => 'Fulfilled',
                        InventoryPurchaseOrder::STATUS_CANCELLED => 'Cancelled',
                    ]),
            ])
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(fn (InventoryPurchaseOrder $record): string => route('inventory.purchase-orders.show', $record)),
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (InventoryPurchaseOrder $record): string => route('inventory.purchase-orders.pdf', $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No LPOs yet')
            ->emptyStateDescription('Generate LPOs from accepted quotations on an approved RFQ.');
    }

    public function render(): View
    {
        return view('livewire.inventory.list-purchase-orders');
    }
}
