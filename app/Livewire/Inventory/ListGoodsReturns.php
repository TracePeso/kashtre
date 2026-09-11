<?php

namespace App\Livewire\Inventory;

use App\Models\GoodsReturnNote;
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

class ListGoodsReturns extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GoodsReturnNote::query()
                    ->select([
                        'id',
                        'business_id',
                        'store_id',
                        'supplier_id',
                        'created_by_user_id',
                        'reference',
                        'reason_code',
                        'return_date',
                        'status',
                        'created_at',
                    ])
                    ->where('business_id', InventoryBusinessContext::effectiveBusinessId())
                    ->with([
                        'store:id,name',
                        'supplier:id,name',
                    ])
                    ->withCount('lines')
                    ->latest('created_at')
            )
            ->columns([
                TextColumn::make('reference')->searchable()->sortable(),
                TextColumn::make('store.name')->label('Store')->sortable(),
                TextColumn::make('supplier.name')->label('Supplier')->placeholder('—'),
                TextColumn::make('reason_code')
                    ->label('Reason')
                    ->formatStateUsing(fn (?string $state): string => GoodsReturnNote::reasonOptions()[$state] ?? ($state ?? '—')),
                TextColumn::make('return_date')->date('M d, Y')->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('lines_count')->label('Items')->alignEnd(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(fn (GoodsReturnNote $record): string => route('inventory.returns.show', $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    public function render(): View
    {
        return view('livewire.inventory.list-goods-returns');
    }
}
