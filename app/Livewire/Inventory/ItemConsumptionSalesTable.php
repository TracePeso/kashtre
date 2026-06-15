<?php

namespace App\Livewire\Inventory;

use App\Models\Sale;
use App\Services\Inventory\InventoryConsumptionQueryService;
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

class ItemConsumptionSalesTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public int $itemId;

    public int $storeId;

    public string $date;

    public function table(Table $table): Table
    {
        $businessId = (int) Auth::user()->business_id;

        return $table
            ->query(
                app(InventoryConsumptionQueryService::class)
                    ->salesForDayQuery($businessId, $this->storeId, $this->itemId, $this->date)
            )
            ->columns([
                TextColumn::make('status_changed_at')
                    ->label('Time')
                    ->dateTime('g:i A')
                    ->sortable(),

                TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('client_name')
                    ->label('Client')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('quantity')
                    ->label('Qty (SUOM)')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0)),

                TextColumn::make('unit_price')
                    ->label('Unit price')
                    ->alignEnd()
                    ->money('UGX', true),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->alignEnd()
                    ->money('UGX', true)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'completed' ? 'Completed' : 'Partial')
                    ->color(fn (string $state): string => $state === 'completed' ? 'success' : 'warning'),

                TextColumn::make('processedByUser.name')
                    ->label('Processed by')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->actions([
                Action::make('invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-receipt-percent')
                    ->url(fn (Sale $record): string => route('invoices.show', $record->invoice_id))
                    ->openUrlInNewTab()
                    ->visible(fn (Sale $record): bool => filled($record->invoice_id)),
            ])
            ->defaultSort('status_changed_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No sales records')
            ->emptyStateDescription('No sales were linked to this item on the selected day for this store.');
    }

    public function render(): View
    {
        return view('livewire.inventory.item-consumption-sales-table');
    }
}
