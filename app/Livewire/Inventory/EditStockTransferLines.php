<?php

namespace App\Livewire\Inventory;

use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Services\Inventory\InventoryStockTransferService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditStockTransferLines extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public StockTransfer $transfer;

    public function mount(StockTransfer $transfer): void
    {
        if ((int) $transfer->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }

        $this->transfer = $transfer;
    }

    public function table(Table $table): Table
    {
        $editable = $this->transfer->isDraft() || $this->transfer->isPending();
        $service = app(InventoryStockTransferService::class);

        return $table
            ->query(
                StockTransferLine::query()
                    ->where('stock_transfer_id', $this->transfer->id)
                    ->with('item.itemUnit')
            )
            ->columns([
                TextColumn::make('item.name')->label('Item')->description(fn (StockTransferLine $r) => $r->item?->code),
                TextColumn::make('item.itemUnit.name')->label('SUOM')->placeholder('—'),
                TextColumn::make('requested_quantity_suom')
                    ->label('Requested')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0)),
                TextInputColumn::make('approved_quantity_suom')
                    ->label('Approved')
                    ->type('number')
                    ->alignEnd()
                    ->step('1')
                    ->disabled(! $editable)
                    ->updateStateUsing(function (StockTransferLine $record, $state) use ($service, $editable) {
                        if (! $editable) {
                            return $state;
                        }

                        $service->updateLine($record, (float) ($state ?? 0), (float) $record->received_quantity_suom);

                        return $state;
                    }),
                TextInputColumn::make('received_quantity_suom')
                    ->label('To receive')
                    ->type('number')
                    ->alignEnd()
                    ->step('1')
                    ->disabled(! $editable)
                    ->updateStateUsing(function (StockTransferLine $record, $state) use ($service, $editable) {
                        if (! $editable) {
                            return $state;
                        }

                        $service->updateLine($record, (float) $record->approved_quantity_suom, (float) ($state ?? 0));

                        return $state;
                    }),
            ])
            ->paginated(false)
            ->striped();
    }

    public function render(): View
    {
        return view('livewire.inventory.edit-stock-transfer-lines');
    }
}
