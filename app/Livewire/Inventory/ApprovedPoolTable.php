<?php

namespace App\Livewire\Inventory;

use App\Models\PatientApprovedPoolLine;
use App\Support\InventoryBusinessContext;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ApprovedPoolTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        $query = PatientApprovedPoolLine::query()
            ->with([
                'client:id,name',
                'item:id,name,strength,code',
                'invoice:id,invoice_number',
                'sourceFulfillmentLine:id,uuid,store_id,status',
                'sourceFulfillmentLine.store:id,name',
            ])
            ->where('business_id', $businessId)
            ->orderByDesc('id');

        return $table
            ->query($query)
            ->defaultPaginationPageOption(25)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable()
                    ->wrap()
                    ->description(fn (PatientApprovedPoolLine $record): ?string => collect([
                        $record->item?->strength ? 'Strength: '.$record->item->strength : null,
                        $record->item?->code ? 'Code: '.$record->item->code : null,
                    ])->filter()->implode(' · ') ?: null),
                Tables\Columns\TextColumn::make('quantity_remaining')
                    ->label('Remaining')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2), '0'), '.')),
                Tables\Columns\TextColumn::make('quantity_original')
                    ->label('Original')
                    ->alignEnd()
                    ->toggleable()
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2), '0'), '.')),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sourceFulfillmentLine.store.name')
                    ->label('Dispensed from')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('has_remaining')
                    ->label('Balance')
                    ->placeholder('All')
                    ->trueLabel('Has remaining')
                    ->falseLabel('Fully used')
                    ->queries(
                        true: fn ($query) => $query->where('quantity_remaining', '>', 0),
                        false: fn ($query) => $query->where('quantity_remaining', '<=', 0),
                        blank: fn ($query) => $query,
                    )
                    ->default(true),
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No approved pool lines')
            ->emptyStateDescription('Lines appear here after End Store dispense or inpatient handoff release.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }

    public function render(): View
    {
        return view('livewire.inventory.approved-pool-table');
    }
}
