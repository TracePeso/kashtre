<?php

namespace App\Livewire\SupplierIndustries;

use App\Models\Business;
use App\Models\SupplierIndustry;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListSupplierIndustries extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        abort_unless(Auth::user()?->business_id === 1, 403);

        $query = SupplierIndustry::query()
            ->where('business_id', '!=', 1)
            ->latest();

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Industry')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sub_categories_count')
                    ->label('Sub categories')
                    ->counts('subCategories')
                    ->alignEnd(),
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
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('business_id')
                    ->label('Filter by Business')
                    ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                    ->searchable()
                    ->multiple(),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit')
                    ->visible(fn () => in_array('Edit Supplier Industries', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Supplier Industry')
                    ->form(fn (SupplierIndustry $record) => $this->industryForm($record))
                    ->successNotificationTitle('Supplier industry updated successfully.'),
                DeleteAction::make()
                    ->label('Delete')
                    ->visible(fn () => in_array('Edit Supplier Industries', Auth::user()->permissions ?? []))
                    ->modalHeading('Delete Supplier Industry')
                    ->successNotificationTitle('Supplier industry deleted successfully.'),
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
                    ->visible(fn () => in_array('Add Supplier Industries', Auth::user()->permissions ?? []))
                    ->label('Create Industry')
                    ->modalHeading('Add Supplier Industry')
                    ->form($this->industryForm())
                    ->createAnother(false)
                    ->after(fn () => Notification::make()
                        ->title('Supplier industry created successfully.')
                        ->success()
                        ->send()),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function industryForm(?SupplierIndustry $record = null): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('Select a business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default($record?->business_id),
            TextInput::make('name')
                ->label('Industry name')
                ->placeholder('e.g. Pharmaceuticals, Medical Equipment')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label('Description')
                ->placeholder('Optional notes')
                ->nullable()
                ->rows(2),
        ];
    }

    public function render(): View
    {
        return view('livewire.supplier-industries.list-supplier-industries');
    }
}
