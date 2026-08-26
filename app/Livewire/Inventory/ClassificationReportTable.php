<?php

namespace App\Livewire\Inventory;

use App\Models\Branch;
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
                        'store:id,name,branch_id',
                        'store.branch:id,name',
                        'item:id,name,code',
                        'client:id,name',
                        'recordedBy:id,name,department_id',
                        'recordedBy.department:id,name',
                    ])
            )
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label(inventory_label('store'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('store.branch.name')
                    ->label('Department / Branch')
                    ->placeholder('—')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('recordedBy.department.name')
                    ->label('Recorder department')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client.name')
                    ->label(inventory_label('client'))
                    ->placeholder('—')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('item.name')
                    ->label(inventory_label('item'))
                    ->description(fn (InventoryUsageEvent $record): ?string => $record->item?->code)
                    ->searchable()
                    ->wrap(),
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
                    ->label(inventory_label('store'))
                    ->options(fn (): array => Store::optionsForSelect($businessId)),
                SelectFilter::make('branch')
                    ->label('Department / Branch')
                    ->options(fn (): array => Branch::query()
                        ->where('business_id', $businessId)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        return $query->whereHas('store', fn ($q) => $q->where('branch_id', $value));
                    }),
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
