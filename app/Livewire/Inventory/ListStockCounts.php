<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryStockCount;
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

class ListStockCounts extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryStockCount::query()
                    ->select([
                        'id',
                        'business_id',
                        'store_id',
                        'created_by_user_id',
                        'reference',
                        'status',
                        'counted_at',
                        'created_at',
                    ])
                    ->where('business_id', InventoryBusinessContext::effectiveBusinessId())
                    ->with([
                        'store:id,name',
                        'createdBy:id,name',
                    ])
                    ->withCount('lines')
                    ->latest('created_at')
            )
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'pending_approval' => 'Pending approval',
                        'approved', 'finalized' => 'Approved',
                        'rejected' => 'Rejected',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'pending_approval' => 'info',
                        'approved', 'finalized' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('counted_at')
                    ->label('Count date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Created by')
                    ->placeholder('—'),

                TextColumn::make('lines_count')
                    ->label('Items')
                    ->alignEnd(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(fn (InventoryStockCount $record): string => route('inventory.stock-counts.show', $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    public function render(): View
    {
        return view('livewire.inventory.list-stock-counts');
    }
}
