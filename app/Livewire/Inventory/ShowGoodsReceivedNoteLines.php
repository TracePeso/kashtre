<?php

namespace App\Livewire\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteLine;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ShowGoodsReceivedNoteLines extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public GoodsReceivedNote $goodsReceivedNote;

    public function mount(GoodsReceivedNote $goodsReceivedNote): void
    {
        if ((int) $goodsReceivedNote->business_id !== (int) \App\Support\InventoryBusinessContext::effectiveBusinessId()) {
            abort(403);
        }

        $this->goodsReceivedNote = $goodsReceivedNote;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GoodsReceivedNoteLine::query()
                    ->where('goods_received_note_id', $this->goodsReceivedNote->id)
                    ->with('item')
            )
            ->columns([
                TextColumn::make('item_name')
                    ->label('Item')
                    ->description(fn (GoodsReceivedNoteLine $record): ?string => $record->item?->code)
                    ->weight('medium')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->placeholder('—'),

                TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->date('M d, Y')
                    ->placeholder('—'),

                TextColumn::make('suom')
                    ->label('Sale unit')
                    ->placeholder('—'),

                TextColumn::make('sale_units_per_purchase_unit')
                    ->label('No. sale units / purchase')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('duom')
                    ->label('Delivery unit')
                    ->placeholder('—'),

                TextColumn::make('purchase_price')
                    ->label('Purchase price')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2)),

                TextColumn::make('line_total')
                    ->label('Item total')
                    ->alignEnd()
                    ->state(fn (GoodsReceivedNoteLine $record): float => (float) $record->quantity * (float) $record->purchase_price)
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2))
                    ->weight('medium'),

                TextColumn::make('sale_units_purchased')
                    ->label('Sale units purchased')
                    ->alignEnd()
                    ->weight('semibold')
                    ->color('success')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('No items')
            ->emptyStateDescription('This goods receive note has no items recorded.');
    }

    public function render(): View
    {
        return view('livewire.inventory.show-goods-received-note-lines');
    }
}
