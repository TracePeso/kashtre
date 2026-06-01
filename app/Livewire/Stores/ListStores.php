<?php

namespace App\Livewire\Stores;


use App\Models\Store;
use App\Models\Business;
use App\Models\Branch;
use Filament\Forms;
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

class ListStores extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    

    public function table(Table $table): Table
    {
        $query = Store::query()->where('business_id', '!=', 1)->latest();

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
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
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
                    ->label('Edit Store')
                    ->visible(fn() => in_array('Edit Stores', Auth::user()->permissions))
                    ->modalHeading('Edit Store')
                    ->form(fn(Store $record) => [
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->disabled(fn() => Auth::user()->business_id !== 1)
                            ->reactive(),
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('Select a branch')
                            ->options(function ($get) {
                                $businessId = $get('business_id');
                                return $businessId ? Branch::where('business_id', $businessId)->pluck('name', 'id') : [];
                            })
                            ->required()
                            ->reactive(),
                        Forms\Components\TextInput::make('name')
                            ->label('Store Name')
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->nullable(),
                    ])
                    ->successNotificationTitle('Store updated successfully.'),
                DeleteAction::make()
                    ->visible(fn() => in_array('Delete Stores', Auth::user()->permissions))
                    ->modalHeading('Delete Store')
                    ->successNotificationTitle('Store deleted (soft) successfully.'),
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
                    ->visible(fn() => in_array('Add Stores', Auth::user()->permissions))
                    ->label('Create Store')
                    ->modalHeading('Add New Store')
                    ->form([
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->default(Auth::user()->business_id)
                            ->disabled(fn() => Auth::user()->business_id !== 1)
                            ->reactive(),
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('Select a branch')
                            ->options(function ($get) {
                                $businessId = $get('business_id');
                                return $businessId ? Branch::where('business_id', $businessId)->pluck('name', 'id') : [];
                            })
                            ->required()
                            ->reactive(),
                        Forms\Components\TextInput::make('name')
                            ->label('Store Name')
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->nullable(),
                    ])
                    ->createAnother(false)
                    ->after(function (Store $record) {
                        Notification::make()
                            ->title('Store created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.stores.list-stores');
    }
}
