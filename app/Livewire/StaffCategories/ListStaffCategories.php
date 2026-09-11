<?php

namespace App\Livewire\StaffCategories;

use App\Models\Business;
use App\Models\StaffCategory;
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

class ListStaffCategories extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        abort_unless(Auth::user()?->business_id === 1, 403);

        $query = StaffCategory::query()
            ->where('business_id', '!=', 1)
            ->latest();

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Staff category')
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
                    ->visible(fn () => in_array('Edit Staff Categories', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Staff Category')
                    ->form(fn (StaffCategory $record) => $this->categoryForm($record))
                    ->successNotificationTitle('Staff category updated successfully.'),
                DeleteAction::make()
                    ->label('Delete')
                    ->visible(fn () => in_array('Edit Staff Categories', Auth::user()->permissions ?? []))
                    ->modalHeading('Delete Staff Category')
                    ->successNotificationTitle('Staff category deleted successfully.'),
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
                    ->visible(fn () => in_array('Add Staff Categories', Auth::user()->permissions ?? []))
                    ->label('Create Staff Category')
                    ->modalHeading('Add Staff Category')
                    ->form($this->categoryForm())
                    ->createAnother(false)
                    ->after(fn () => Notification::make()
                        ->title('Staff category created successfully.')
                        ->success()
                        ->send()),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function categoryForm(?StaffCategory $record = null): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('Select a business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default($record?->business_id),
            TextInput::make('name')
                ->label('Category name')
                ->placeholder('e.g. Clinical, Administrative, Support')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label('Description')
                ->placeholder('Optional notes for HR reporting')
                ->nullable()
                ->rows(2),
        ];
    }

    public function render(): View
    {
        return view('livewire.staff-categories.list-staff-categories');
    }
}
