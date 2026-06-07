<?php

namespace App\Livewire\Inventory;

use App\Models\StockTransfer;
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

class ListStockTransfers extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockTransfer::query()
                    ->where('business_id', Auth::user()->business_id)
                    ->with(['fromStore', 'toStore', 'requestedBy'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('reference')->searchable()->sortable(),
                TextColumn::make('fromStore.name')->label('From')->sortable(),
                TextColumn::make('toStore.name')->label('To')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        StockTransfer::STATUS_DRAFT => 'gray',
                        StockTransfer::STATUS_PENDING => 'warning',
                        StockTransfer::STATUS_APPROVED => 'info',
                        StockTransfer::STATUS_RECEIVED => 'success',
                        StockTransfer::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('requested_at')->label('Requested')->dateTime('M d, Y H:i')->placeholder('—'),
                TextColumn::make('lines_count')->label('Items')->counts('lines')->alignEnd(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(fn (StockTransfer $record): string => route('inventory.transfers.show', $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public function render(): View
    {
        return view('livewire.inventory.list-stock-transfers');
    }
}
