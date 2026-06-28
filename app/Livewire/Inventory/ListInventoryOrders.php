<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryOrder;
use App\Models\ItemImportanceCategory;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListInventoryOrders extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryOrder::query()
                    ->where('business_id', Auth::user()->business_id)
                    ->with(['store', 'createdBy', 'group'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (InventoryOrder $record): string => $record->statusLabel())
                    ->color(fn (InventoryOrder $record): string => match ($record->status) {
                        InventoryOrder::STATUS_DRAFT => 'warning',
                        InventoryOrder::STATUS_PENDING_APPROVAL => 'warning',
                        InventoryOrder::STATUS_APPROVED => 'info',
                        InventoryOrder::STATUS_PO_ISSUED => 'primary',
                        InventoryOrder::STATUS_PARTIALLY_RECEIVED => 'primary',
                        InventoryOrder::STATUS_FULFILLED => 'success',
                        InventoryOrder::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('importance_filter')
                    ->label('Importance')
                    ->formatStateUsing(fn (?string $state, InventoryOrder $record): string => $state
                        ? (ItemImportanceCategory::labelForSlug((int) $record->business_id, $state) ?? $state)
                        : 'All'),

                TextColumn::make('group.name')
                    ->label('Group')
                    ->placeholder('All')
                    ->toggleable(),

                TextColumn::make('lines_count')
                    ->label('Lines')
                    ->counts('lines')
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(fn (InventoryOrder $record): string => route('inventory.orders.show', $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public function render(): View
    {
        return view('livewire.inventory.list-inventory-orders');
    }
}
