<?php

namespace App\Livewire\Items;

use App\Models\Item;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class SimpleItems extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = \App\Models\Item::query()
            ->where('business_id', '!=', 1)
            ->whereIn('type', ['service', 'good']) // Filter for simple items only
            ->latest();
            
        // if (auth()->check() && auth()->user()->business_id !== 1) {
        //     $query->where('business_id', auth()->user()->business_id);
        // }
        
        return $table
            ->query($query)
            ->columns([
                TextColumn::make('business.name')
                ->label('Business')
                ->sortable()
                ->searchable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('generic_name')
                    ->label('Generic Name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('category')
                    ->label('Category')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'service' => 'blue',
                        'good' => 'green',
                        default => 'gray',
                    }),
                TextColumn::make('group.name')
                    ->label('Group')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('subgroup.name')
                    ->label('Subgroup')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('itemUnit.name')
                    ->label('Unit of Measure')
                    ->sortable(),
                TextColumn::make('default_price')
                    ->label('Sale price')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('purchase_price')
                    ->label('Purchase price')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('hospital_share')
                    ->label('Company/Entity')
                    ->formatStateUsing(fn (string $state): string => $state . '%')
                    ->sortable(),
                TextColumn::make('contractor.user.name')
                    ->label('Contractor')
                    ->sortable()
                    ->visible(fn ($record) => $record && $record->hospital_share < 100),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ...((auth()->check() && auth()->user()->business_id === 1) ? [
                    SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(\App\Models\Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
                SelectFilter::make('type')
                    ->label('Item Type')
                    ->options([
                        'service' => 'Service',
                        'good' => 'Good',
                    ])
                    ->multiple(),
            ])
            ->actions([
                \Filament\Tables\Actions\ViewAction::make()
                    ->url(fn (Item $record): string => route('items.show', $record))
                    ->visible(fn() => in_array('View Items', auth()->user()->permissions ?? [])),
            ])
            ->bulkActions([]);
    }

    public function render(): View
    {
        return view('livewire.items.simple_items');
    }
}
