<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryOrder;
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

class ListInternalReplenishmentOrders extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        $query = InventoryOrder::query()
            ->select([
                'id',
                'business_id',
                'store_id',
                'source_store_id',
                'order_number',
                'order_type',
                'status',
                'forecast_basis',
                'period_of_order_days',
                'budget_value',
                'created_at',
                'created_by_user_id',
            ])
            ->where('business_id', $businessId)
            ->where('order_type', InventoryOrder::TYPE_INTERNAL)
            ->with([
                'store:id,name',
                'sourceStore:id,name',
                'createdBy:id,name',
            ])
            ->withCount('lines')
            ->latest('created_at');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('store.name')
                    ->label('Requesting store')
                    ->description(fn (InventoryOrder $record): ?string => $record->sourceStore
                        ? 'From '.$record->sourceStore->name
                        : null)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('forecast_basis')
                    ->label('Basis')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        InventoryOrder::FORECAST_DEMAND => 'Demand',
                        default => 'Consumption',
                    })
                    ->color(fn (?string $state): string => $state === InventoryOrder::FORECAST_DEMAND
                        ? 'warning'
                        : 'gray'),
                TextColumn::make('period_of_order_days')
                    ->label('Coverage days')
                    ->alignEnd()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? rtrim(rtrim(number_format((float) $state, 1), '0'), '.')
                        : '—'),
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
                TextColumn::make('lines_count')
                    ->label('Lines')
                    ->alignEnd(),
                TextColumn::make('createdBy.name')
                    ->label('Created by')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        InventoryOrder::STATUS_DRAFT => 'Draft',
                        InventoryOrder::STATUS_PENDING_APPROVAL => 'Pending approval',
                        InventoryOrder::STATUS_APPROVED => 'Approved',
                        InventoryOrder::STATUS_FULFILLED => 'Fulfilled',
                        InventoryOrder::STATUS_REJECTED => 'Rejected',
                    ]),
                SelectFilter::make('forecast_basis')
                    ->label('Basis')
                    ->options([
                        InventoryOrder::FORECAST_CONSUMPTION => 'Consumption',
                        InventoryOrder::FORECAST_DEMAND => 'Demand',
                    ]),
            ])
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(fn (InventoryOrder $record): string => route('inventory.orders.show', $record)),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Create draft')
                    ->icon('heroicon-o-plus')
                    ->url(route('inventory.replenishment.create')),
            ])
            ->emptyStateHeading('No replenishment drafts yet')
            ->emptyStateDescription('Create a draft to request stock from a parent store.')
            ->emptyStateActions([
                Action::make('createEmpty')
                    ->label('Create draft')
                    ->icon('heroicon-o-plus')
                    ->url(route('inventory.replenishment.create')),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    public function render(): View
    {
        return view('livewire.inventory.list-internal-replenishment-orders');
    }
}
