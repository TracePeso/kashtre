<?php

namespace App\Livewire\Suppliers;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;


class ListSuppliers extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = Supplier::query()->where('business_id', '!=', 1)->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Tagged items')
                    ->counts('items')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit Supplier')
                    ->visible(fn () => in_array('Edit Suppliers', Auth::user()->permissions))
                    ->modalHeading('Edit Supplier')
                    ->fillForm(fn (Supplier $record): array => array_merge($record->toArray(), [
                        'item_ids' => $record->items()->pluck('items.id')->all(),
                    ]))
                    ->form(fn (Supplier $record) => $this->supplierForm($record->business_id))
                    ->using(function (Supplier $record, array $data): Supplier {
                        $itemIds = $data['item_ids'] ?? [];
                        unset($data['item_ids']);
                        $record->update($data);
                        $record->items()->sync($itemIds);

                        return $record;
                    })
                    ->successNotificationTitle('Supplier updated successfully.'),
                DeleteAction::make()
                    ->visible(fn() => in_array('Delete Suppliers', Auth::user()->permissions))
                    ->modalHeading('Delete Supplier')
                    ->successNotificationTitle('Supplier deleted (soft) successfully.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => in_array('Add Suppliers', Auth::user()->permissions))
                    ->label('Create Supplier')
                    ->modalHeading('Add New Supplier')
                    ->form($this->supplierForm(Auth::user()->business_id))
                    ->using(function (array $data): Supplier {
                        $itemIds = $data['item_ids'] ?? [];
                        unset($data['item_ids']);
                        $supplier = Supplier::create($data);
                        $supplier->items()->sync($itemIds);

                        return $supplier;
                    })
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Supplier created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /** @return array<int, Forms\Components\Component> */
    private function supplierForm(?int $businessId = null): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('Select a business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default(Auth::user()->business_id)
                ->disabled(fn () => Auth::user()->business_id !== 1)
                ->live(),
            Forms\Components\TextInput::make('name')
                ->label('Supplier Name')
                ->required(),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable(),
            Forms\Components\Select::make('item_ids')
                ->label('Items supplied')
                ->helperText('Leave empty to allow any item on GRNs.')
                ->options(fn (Get $get): array => Item::query()
                    ->where('business_id', $get('business_id') ?? $businessId ?? Auth::user()->business_id)
                    ->where('type', 'good')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->multiple()
                ->searchable(),
        ];
    }

    public function render(): View
    {
        return view('livewire.suppliers.list-suppliers');
    }
}
