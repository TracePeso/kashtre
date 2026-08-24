<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryUsageEvent;
use App\Models\Store;
use App\Support\InventoryBusinessContext;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ClassificationReportTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $businessId = (int) InventoryBusinessContext::effectiveBusinessId();

        return $table
            ->query(
                InventoryUsageEvent::query()
                    ->where('business_id', $businessId)
                    ->with([
                        'store:id,name',
                        'item:id,name,code',
                        'client:id,name',
                        'invoice:id,client_space_id',
                        'invoice.clientSpace:id,name',
                    ])
            )
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label('Store')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label('Item')
                    ->description(fn (InventoryUsageEvent $record): ?string => $record->item?->code)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('client_space')
                    ->label('Client Space')
                    ->state(fn (InventoryUsageEvent $record): string => $record->invoice?->clientSpace?->name ?? '—'),
                TextColumn::make('classification')
                    ->label('Classification')
                    ->badge()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
                TextColumn::make('resolution')
                    ->label('Resolution')
                    ->formatStateUsing(fn (?string $state, InventoryUsageEvent $record): string => $record->resolutionLabel())
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('classification')
                    ->options([
                        InventoryUsageEvent::CLASSIFICATION_PATIENT => 'PATIENT',
                        InventoryUsageEvent::CLASSIFICATION_ADMINISTRATIVE => 'ADMINISTRATIVE',
                        InventoryUsageEvent::CLASSIFICATION_CRASH_CART => 'CRASH_CART',
                        InventoryUsageEvent::CLASSIFICATION_WASTAGE_OPERATIONAL => 'WASTAGE_OPERATIONAL',
                        InventoryUsageEvent::CLASSIFICATION_WASTAGE_EXPIRED => 'WASTAGE_EXPIRED',
                    ]),
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->options(fn (): array => Store::optionsForSelect($businessId)),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No classified usage')
            ->emptyStateDescription('Usage events appear after Record Usage, wastage, or crash-cart activity.');
    }

    public function render(): View
    {
        return view('livewire.inventory.classification-report-table');
    }
}
