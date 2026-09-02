<?php

namespace App\Livewire\Inventory;

use App\Models\InventorySupplierQuotation;
use App\Services\Inventory\SuppliedQuotationService;
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

class ListSuppliedQuotations extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $service = app(SuppliedQuotationService::class);
        $entityBusinessId = (int) InventoryBusinessContext::effectiveBusinessId();

        return $table
            ->query($service->quotationsQuery($entityBusinessId))
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Your reference')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('inventoryOrder.order_number')
                    ->label('RFQ #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventoryOrder.business.name')
                    ->label('Buyer')
                    ->searchable()
                    ->description(fn (InventorySupplierQuotation $record): ?string => $record->inventoryOrder?->business?->entity_code),
                TextColumn::make('outcome')
                    ->label('Outcome')
                    ->badge()
                    ->state(fn (InventorySupplierQuotation $record): string => $service->outcomeLabel($record))
                    ->color(fn (InventorySupplierQuotation $record): string => $service->outcomeColor($service->outcomeLabel($record))),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state, 2)),
                TextColumn::make('received_at')
                    ->label('Submitted')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('purchaseOrder.po_number')
                    ->label('LPO')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        InventorySupplierQuotation::STATUS_RECEIVED => 'Submitted',
                        InventorySupplierQuotation::STATUS_ACCEPTED => 'Accepted',
                        InventorySupplierQuotation::STATUS_REJECTED => 'Not selected',
                    ]),
            ])
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(fn (InventorySupplierQuotation $record): string => route('inventory.supplied-quotations.show', $record)),
            ])
            ->defaultSort('received_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No supplied quotations yet')
            ->emptyStateDescription('Quotations you submit on incoming RFQs from other Kashtre organisations will appear here.');
    }

    public function render(): View
    {
        return view('livewire.inventory.list-supplied-quotations');
    }
}
