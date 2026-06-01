<?php

namespace App\Livewire\Departments;

use App\Models\Department;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListDepartments extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = Department::query()->where('business_id', '!=', 1)->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Department')
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
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Deleted At')
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
                    ->visible(fn() => in_array('Edit Departments', Auth::user()->permissions))
                    ->modalHeading('Edit Department')
                    ->form(fn(Department $record) => [
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->disabled(fn() => Auth::user()->business_id !== 1),

                        TextInput::make('name')
                            ->label('Department Name')
                            ->placeholder('Enter department name')
                            ->required(),

                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Enter department description')
                            ->nullable(),
                    ])
                    ->successNotificationTitle('Department updated successfully.'),

                DeleteAction::make()
                    ->visible(fn() => in_array('Delete Departments', Auth::user()->permissions))
                    ->modalHeading('Delete Department')
                    ->successNotificationTitle('Department deleted (soft) successfully.'),
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
                    ->label('Create Department')
                    ->visible(fn() => in_array('Add Departments', Auth::user()->permissions))
                    ->modalHeading('Add New Department')
                    ->form([
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->default(Auth::user()->business_id)
                            ->disabled(fn() => Auth::user()->business_id !== 1),

                        TextInput::make('name')
                            ->label('Department Name')
                            ->placeholder('Enter department name')
                            ->required(),

                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Enter department description')
                            ->nullable(),
                    ])
                    ->createAnother(false)
                    ->after(function (Department $record) {
                        Notification::make()
                            ->title('Department created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.departments.list-departments');
    }
}
