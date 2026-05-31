<?php

namespace App\Livewire\Inventory;

use App\Models\GoodsReceivedNote;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListGoodsReceivedNotes extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GoodsReceivedNote::query()
                    ->where('business_id', Auth::user()->business_id)
                    ->with(['supplier', 'entryBy'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('grn_number')
                    ->label('GRN #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('date_of_delivery')
                    ->label('Delivery date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('lead_time_days')
                    ->label('Lead time (days)')
                    ->alignEnd(),

                TextColumn::make('entryBy.name')
                    ->label('Entry by')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'pending_approval' => 'Pending approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_approval' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('lines_count')
                    ->label('Lines')
                    ->counts('lines')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        GoodsReceivedNote::STATUS_DRAFT => 'Draft',
                        GoodsReceivedNote::STATUS_PENDING => 'Pending approval',
                        GoodsReceivedNote::STATUS_APPROVED => 'Approved',
                        GoodsReceivedNote::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->url(fn (GoodsReceivedNote $record): string => route('inventory.receive.show', $record)),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('New GRN')
                    ->url(route('inventory.receive.create'))
                    ->icon('heroicon-o-plus'),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No goods received notes yet')
            ->emptyStateDescription('Create a GRN to record incoming stock from a supplier.');
    }

    public function render(): View
    {
        return view('livewire.inventory.list-goods-received-notes');
    }
}
