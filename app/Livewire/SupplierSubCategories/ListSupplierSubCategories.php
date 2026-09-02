<?php

namespace App\Livewire\SupplierSubCategories;

use App\Models\Business;
use App\Models\SupplierIndustry;
use App\Models\SupplierSubCategory;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
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

class ListSupplierSubCategories extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        abort_unless(Auth::user()?->business_id === 1, 403);

        $query = SupplierSubCategory::query()
            ->with('industry')
            ->where('business_id', '!=', 1)
            ->latest();

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Sub category')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('industry.name')
                    ->label('Industry')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('supplier_industry_id')
                    ->label('Filter by Industry')
                    ->options(fn (): array => SupplierIndustry::query()
                        ->where('business_id', '!=', 1)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->multiple(),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit')
                    ->visible(fn () => in_array('Edit Supplier Sub Categories', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Supplier Sub Category')
                    ->form(fn (SupplierSubCategory $record) => $this->subCategoryForm($record))
                    ->successNotificationTitle('Supplier sub category updated successfully.'),
                DeleteAction::make()
                    ->label('Delete')
                    ->visible(fn () => in_array('Edit Supplier Sub Categories', Auth::user()->permissions ?? []))
                    ->modalHeading('Delete Supplier Sub Category')
                    ->successNotificationTitle('Supplier sub category deleted successfully.'),
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
                    ->visible(fn () => in_array('Add Supplier Sub Categories', Auth::user()->permissions ?? []))
                    ->label('Create Sub Category')
                    ->modalHeading('Add Supplier Sub Category')
                    ->form($this->subCategoryForm())
                    ->createAnother(false)
                    ->after(fn () => Notification::make()
                        ->title('Supplier sub category created successfully.')
                        ->success()
                        ->send()),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function subCategoryForm(?SupplierSubCategory $record = null): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('Select a business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default($record?->business_id)
                ->live(),
            Forms\Components\Select::make('supplier_industry_id')
                ->label('Industry')
                ->placeholder('Select an industry')
                ->options(fn (Get $get): array => SupplierIndustry::query()
                    ->when($get('business_id'), fn ($query, $id) => $query->where('business_id', (int) $id))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->default($record?->supplier_industry_id)
                ->searchable()
                ->disabled(fn (Get $get): bool => blank($get('business_id'))),
            TextInput::make('name')
                ->label('Sub category name')
                ->placeholder('e.g. Surgical Supplies, Laboratory Reagents')
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
        return view('livewire.supplier-sub-categories.list-supplier-sub-categories');
    }
}
