<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryRfqSupplier;
use App\Services\Inventory\IncomingRfqService;
use App\Support\InventoryBusinessContext;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ListIncomingRfqs extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $service = app(IncomingRfqService::class);
        $entityBusinessId = (int) InventoryBusinessContext::effectiveBusinessId();

        return $table
            ->query($service->invitationsQuery($entityBusinessId))
            ->columns([
                TextColumn::make('inventoryOrder.order_number')
                    ->label('RFQ #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventoryOrder.business.name')
                    ->label('From organisation')
                    ->searchable()
                    ->description(fn (InventoryRfqSupplier $record): ?string => $record->inventoryOrder?->business?->entity_code),
                TextColumn::make('inventoryOrder.store.name')
                    ->label('Delivery store')
                    ->placeholder('—'),
                TextColumn::make('supplier_status')
                    ->label('Your status')
                    ->badge()
                    ->state(fn (InventoryRfqSupplier $record): string => $service->statusLabel($record))
                    ->color(fn (InventoryRfqSupplier $record): string => $service->statusColor($service->statusLabel($record))),
                TextColumn::make('quotation_total')
                    ->label('Quoted total')
                    ->alignEnd()
                    ->state(function (InventoryRfqSupplier $record): ?string {
                        $quotation = $record->inventoryOrder?->supplierQuotations
                            ?->firstWhere('supplier_id', $record->supplier_id);

                        return $quotation
                            ? 'UGX '.number_format((float) $quotation->total_amount, 2)
                            : null;
                    })
                    ->placeholder('—'),
                TextColumn::make('invited_at')
                    ->label('Invited')
                    ->dateTime('M d, Y')
                    ->sortable(),
                TextColumn::make('rfq_sent_at')
                    ->label('RFQ sent')
                    ->dateTime('M d, Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(fn (InventoryRfqSupplier $record): string => route('inventory.incoming-rfqs.show', $record)),
            ])
            ->defaultSort('invited_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No incoming RFQs')
            ->emptyStateDescription('When another Kashtre organisation adds your entity as a linked supplier and invites you on an RFQ, it will appear here.');
    }

    public function render(): View
    {
        return view('livewire.inventory.list-incoming-rfqs');
    }
}
