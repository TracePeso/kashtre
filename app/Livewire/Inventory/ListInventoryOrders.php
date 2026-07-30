<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryOrder;
use App\Models\ItemImportanceCategory;
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

class ListInventoryOrders extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public string $statusFilter = 'all';

    /** @var array<string, string>|null */
    private ?array $importanceLabels = null;

    public function mount(string $statusFilter = 'all'): void
    {
        if (! in_array($statusFilter, ['all', 'draft', 'running', 'completed', 'rejected'], true)) {
            $statusFilter = 'all';
        }

        $this->statusFilter = $statusFilter;
    }

    public function table(Table $table): Table
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        $query = InventoryOrder::query()
            ->select([
                'id',
                'business_id',
                'store_id',
                'source_store_id',
                'supplier_id',
                'group_id',
                'order_number',
                'order_type',
                'status',
                'importance_filter',
                'created_at',
            ])
            ->where('business_id', $businessId)
            ->with([
                'store:id,name',
                'sourceStore:id,name',
                'group:id,name',
            ])
            ->withCount('lines')
            ->latest('created_at');

        if ($this->statusFilter === 'running') {
            $query->whereIn('status', [
                InventoryOrder::STATUS_PENDING_APPROVAL,
                InventoryOrder::STATUS_APPROVED,
                InventoryOrder::STATUS_PO_ISSUED,
                InventoryOrder::STATUS_PARTIALLY_RECEIVED,
            ]);
        } elseif ($this->statusFilter === 'completed') {
            $query->where('status', InventoryOrder::STATUS_FULFILLED);
        } elseif ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_type')
                    ->label('Type')
                    ->formatStateUsing(fn (InventoryOrder $record): string => $record->orderTypeLabel())
                    ->badge()
                    ->color(fn (InventoryOrder $record): string => $record->isInternal() ? 'info' : 'gray'),

                TextColumn::make('store.name')
                    ->label('Receiving store')
                    ->description(fn (InventoryOrder $record): ?string => $record->isInternal()
                        ? 'From '.$record->sourceStore?->name
                        : 'External RFQ')
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
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? ($this->importanceLabels()[$state] ?? $state)
                        : 'All'),

                TextColumn::make('group.name')
                    ->label('Group')
                    ->placeholder('All')
                    ->toggleable(),

                TextColumn::make('lines_count')
                    ->label('Lines')
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
                Action::make('calculations')
                    ->label('View calculation')
                    ->url(fn (InventoryOrder $record): string => route('inventory.orders.calculations', $record)),
                Action::make('download_rfq')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (InventoryOrder $record): string => route('inventory.orders.pdf', $record))
                    ->visible(fn (InventoryOrder $record): bool => $record->isExternal() && (int) $record->lines_count > 0),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * @return array<string, string>
     */
    private function importanceLabels(): array
    {
        if ($this->importanceLabels !== null) {
            return $this->importanceLabels;
        }

        $this->importanceLabels = ItemImportanceCategory::optionsForBusiness(
            InventoryBusinessContext::effectiveBusinessId()
        );

        return $this->importanceLabels;
    }

    public function render(): View
    {
        return view('livewire.inventory.list-inventory-orders');
    }
}
