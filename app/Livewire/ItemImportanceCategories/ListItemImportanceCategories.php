<?php

namespace App\Livewire\ItemImportanceCategories;

use App\Models\Business;
use App\Models\Item;
use App\Models\ItemImportanceCategory;
use Filament\Forms;
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

class ListItemImportanceCategories extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = ItemImportanceCategory::query()
            ->with('business')
            ->where('business_id', '!=', 1)
            ->orderBy('name');

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Code')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
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
                    ->visible(fn () => in_array('Edit Item Categories', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit item category')
                    ->form(fn (ItemImportanceCategory $record) => [
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->disabled(fn () => Auth::user()->business_id !== 1),
                        Forms\Components\TextInput::make('name')
                            ->label('Category name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Code')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Used internally when linking items and orders.'),
                        Forms\Components\Textarea::make('description')
                            ->nullable()
                            ->rows(2),
                    ])
                    ->successNotificationTitle('Item category updated.'),
                DeleteAction::make()
                    ->visible(fn () => in_array('Delete Item Categories', Auth::user()->permissions ?? []))
                    ->before(function (ItemImportanceCategory $record) {
                        $inUse = Item::query()
                            ->where('business_id', $record->business_id)
                            ->where('importance_category', $record->slug)
                            ->exists();

                        if ($inUse) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete category')
                                ->body('One or more items still use this category. Reassign them first.')
                                ->send();

                            $this->halt();
                        }
                    })
                    ->successNotificationTitle('Item category deleted.'),
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
                    ->visible(fn () => in_array('Add Item Categories', Auth::user()->permissions ?? []))
                    ->label('Create category')
                    ->modalHeading('Add item category')
                    ->modalDescription('Categories classify goods by importance for inventory ordering and filtering.')
                    ->form([
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->default(Auth::user()->business_id !== 1 ? Auth::user()->business_id : null)
                            ->disabled(fn () => Auth::user()->business_id !== 1),
                        Forms\Components\TextInput::make('name')
                            ->label('Category name')
                            ->placeholder('e.g. Essential')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->nullable()
                            ->rows(2),
                    ])
                    ->createAnother(false)
                    ->after(fn () => Notification::make()
                        ->title('Item category created.')
                        ->success()
                        ->send()),
            ]);
    }

    public function render(): View
    {
        return view('livewire.item-importance-categories.list-item-importance-categories');
    }
}
