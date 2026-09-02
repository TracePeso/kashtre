<?php

namespace App\Livewire\Sections;

use App\Models\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListSections extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = \App\Models\Section::query()->where('business_id', '!=', 1)->latest();
        if (auth()->check() && auth()->user()->business_id !== 1) {
            $query->where('business_id', auth()->user()->business_id);
        }
        return $table
            ->query($query)
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                \Filament\Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                \Filament\Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ...((auth()->check() && auth()->user()->business_id === 1) ? [
                    \Filament\Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(\App\Models\Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make()
                    ->visible(fn() => in_array('Edit Sections', Auth::user()->permissions))
                    ->modalHeading('Edit Section')
                    ->form(fn(\App\Models\Section $record) => [
                        \Filament\Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(\App\Models\Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->disabled(fn() => auth()->user()->business_id !== 1),
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Section Name')
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->nullable(),
                    ])
                    ->successNotificationTitle('Section updated successfully.'),
            ])
            ->headerActions([
                \Filament\Tables\Actions\CreateAction::make()
                    ->visible(fn() => in_array('Add Sections', Auth::user()->permissions))
                    ->label('Create Section')
                    ->modalHeading('Add New Section')
                    ->form([
                        \Filament\Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(\App\Models\Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->default(auth()->user()->business_id)
                            ->disabled(fn() => auth()->user()->business_id !== 1),
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Section Name')
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->nullable(),
                    ])
                    ->createAnother(false)
                    ->after(function (\App\Models\Section $record) {
                        \Filament\Notifications\Notification::make()
                            ->title('Section created successfully.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    public function render(): View
    {
        return view('livewire.sections.list-sections');
    }
}
