<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryStockMovement;
use App\Models\Item;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ListItemStockHistory extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public Item $item;

    public function mount(Item $item): void
    {
        $this->item = $item->load('inventoryStockLevel');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryStockMovement::query()
                    ->where('business_id', $this->item->business_id)
                    ->where('item_id', $this->item->id)
                    ->with(['recordedBy', 'goodsReceivedNote', 'store'])
            )
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('movement_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state, InventoryStockMovement $record): string => $record->movementTypeLabel()),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('reference_label')
                    ->label('Reference')
                    ->placeholder('—')
                    ->url(fn ($state, InventoryStockMovement $record): ?string => $record->goods_received_note_id
                        ? route('inventory.receive.show', $record->goods_received_note_id)
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('quantity_delta')
                    ->label('Change')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => ((float) $state >= 0 ? '+' : '')
                        .number_format((float) $state, 0)),

                TextColumn::make('balance_after')
                    ->label('Balance after')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('unit_price')
                    ->label('Purchase price')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? 'UGX '.number_format((float) $state, 2)
                        : '—'),

                TextColumn::make('line_valuation')
                    ->label('Receipt valuation')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? 'UGX '.number_format((float) $state, 2)
                        : '—'),

                TextColumn::make('balance_valuation')
                    ->label('Stock value after')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? 'UGX '.number_format((float) $state, 2)
                        : '—'),

                TextColumn::make('recordedBy.name')
                    ->label('Recorded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No stock movements yet')
            ->emptyStateDescription('Each approved goods receive note records purchase price and valuation for that receipt.');
    }

    public function render(): View
    {
        return view('livewire.inventory.list-item-stock-history');
    }
}
