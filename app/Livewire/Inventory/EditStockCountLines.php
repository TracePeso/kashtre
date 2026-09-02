<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryStockCount;
use App\Models\InventoryStockCountLine;
use App\Services\Inventory\InventoryStockCountService;
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

class EditStockCountLines extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public InventoryStockCount $stockCount;

    public function mount(InventoryStockCount $stockCount): void
    {
        if ((int) $stockCount->business_id !== (int) \App\Support\InventoryBusinessContext::effectiveBusinessId()) {
            abort(403);
        }

        $this->stockCount = $stockCount;
    }

    public function table(Table $table): Table
    {
        $isDraft = $this->stockCount->isDraft();
        $writable = $isDraft && ! \App\Support\InventoryBusinessContext::isAdminBrowsing();
        $service = app(InventoryStockCountService::class);

        return $table
            ->query(
                InventoryStockCountLine::query()
                    ->where('inventory_stock_count_id', $this->stockCount->id)
                    ->with('item.itemUnit')
            )
            ->columns([
                TextColumn::make('item.name')
                    ->label('Item')
                    ->description(fn (InventoryStockCountLine $record): ?string => $record->item?->code),

                TextColumn::make('item.itemUnit.name')
                    ->label('Sale unit')
                    ->placeholder('—'),

                TextColumn::make('system_quantity_suom')
                    ->label('System')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextInputColumn::make('physical_quantity_suom')
                    ->label('Physical')
                    ->type('number')
                    ->alignEnd()
                    ->step('1')
                    ->disabled(! $writable)
                    ->updateStateUsing(function (InventoryStockCountLine $record, $state) use ($service, $writable) {
                        if (! $writable) {
                            return $state;
                        }

                        \App\Support\InventoryBusinessContext::assertWritable();

                        $service->updateLine(
                            $record,
                            (float) ($state ?? 0),
                            (float) $record->damaged_quantity_suom,
                            (float) ($record->expired_quantity_suom ?? 0)
                        );

                        return $state;
                    }),

                TextInputColumn::make('damaged_quantity_suom')
                    ->label('Damaged')
                    ->type('number')
                    ->alignEnd()
                    ->step('1')
                    ->disabled(! $writable)
                    ->updateStateUsing(function (InventoryStockCountLine $record, $state) use ($service, $writable) {
                        if (! $writable) {
                            return $state;
                        }

                        \App\Support\InventoryBusinessContext::assertWritable();

                        $service->updateLine(
                            $record,
                            (float) $record->physical_quantity_suom,
                            (float) ($state ?? 0),
                            (float) ($record->expired_quantity_suom ?? 0)
                        );

                        return $state;
                    }),

                TextInputColumn::make('expired_quantity_suom')
                    ->label('Expired')
                    ->type('number')
                    ->alignEnd()
                    ->step('1')
                    ->disabled(! $writable)
                    ->updateStateUsing(function (InventoryStockCountLine $record, $state) use ($service, $writable) {
                        if (! $writable) {
                            return $state;
                        }

                        \App\Support\InventoryBusinessContext::assertWritable();

                        $service->updateLine(
                            $record,
                            (float) $record->physical_quantity_suom,
                            (float) $record->damaged_quantity_suom,
                            (float) ($state ?? 0)
                        );

                        return $state;
                    }),

                TextColumn::make('unaccounted_display')
                    ->label('Unaccounted')
                    ->alignEnd()
                    ->color('danger')
                    ->state(fn (InventoryStockCountLine $record): float => $record->unaccountedLossSuom())
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),

                TextColumn::make('shrinkage_display')
                    ->label('Shrinkage')
                    ->alignEnd()
                    ->color('danger')
                    ->state(fn (InventoryStockCountLine $record): float => $record->totalShrinkageLossSuom())
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0)),
            ])
            ->paginated(false)
            ->striped()
            ->emptyStateHeading('No lines')
            ->emptyStateDescription('This stock count has no item lines.');
    }

    public function render(): View
    {
        return view('livewire.inventory.edit-stock-count-lines');
    }
}
