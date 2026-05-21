<?php

namespace App\Livewire\ClientSpaces;

use App\Models\ClientSpace;
use App\Models\Business;
use App\Models\Branch;
use Filament\Forms\Components\Select;
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

class ListClientSpaces extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = ClientSpace::query()->where('business_id', '!=', 1)->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Client Space Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
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
                    ->visible(fn() => in_array('Edit Client Spaces', Auth::user()->permissions))
                    ->modalHeading('Edit Client Space')
                    ->form(fn(ClientSpace $record) => [
                        Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->disabled(fn() => Auth::user()->business_id !== 1)
                            ->dehydrated()
                            ->reactive(),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('Select a branch (optional)')
                            ->options(function ($get) {
                                $businessId = $get('business_id');
                                return $businessId
                                    ? Branch::where('business_id', $businessId)->pluck('name', 'id')
                                    : [];
                            })
                            ->nullable()
                            ->reactive(),

                        TextInput::make('name')
                            ->label('Client Space Name')
                            ->placeholder('Enter client space name')
                            ->required(),

                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Enter description')
                            ->nullable(),
                    ])
                    ->successNotificationTitle('Client space updated successfully.'),

                DeleteAction::make()
                    ->visible(fn() => in_array('Delete Client Spaces', Auth::user()->permissions))
                    ->modalHeading('Delete Client Space')
                    ->successNotificationTitle('Client space deleted successfully.'),
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
                    ->visible(fn() => in_array('Add Client Spaces', Auth::user()->permissions))
                    ->label('Create Client Space')
                    ->modalHeading('Add New Client Space')
                    ->form([
                        Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->default(Auth::user()->business_id)
                            ->disabled(fn() => Auth::user()->business_id !== 1)
                            ->dehydrated()
                            ->reactive(),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('Select a branch (optional)')
                            ->options(function ($get) {
                                $businessId = $get('business_id');
                                return $businessId
                                    ? Branch::where('business_id', $businessId)->pluck('name', 'id')
                                    : [];
                            })
                            ->nullable()
                            ->reactive(),

                        TextInput::make('name')
                            ->label('Client Space Name')
                            ->placeholder('Enter client space name')
                            ->required(),

                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Enter description')
                            ->nullable(),
                    ])
                    ->createAnother(false)
                    ->after(function (ClientSpace $record) {
                        Notification::make()
                            ->title('Client space created successfully.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No client spaces')
            ->emptyStateDescription('Create a client space to get started.')
            ->emptyStateIcon('heroicon-o-building-office')
            ->emptyStateActions([
                CreateAction::make()
                    ->visible(fn() => in_array('Add Client Spaces', Auth::user()->permissions))
                    ->label('Create Client Space')
                    ->modalHeading('Add New Client Space')
                    ->form([
                        Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->default(Auth::user()->business_id)
                            ->disabled(fn() => Auth::user()->business_id !== 1)
                            ->dehydrated()
                            ->reactive(),

                        Select::make('branch_id')
                            ->label('Branch')
                            ->placeholder('Select a branch (optional)')
                            ->options(function ($get) {
                                $businessId = $get('business_id');
                                return $businessId
                                    ? Branch::where('business_id', $businessId)->pluck('name', 'id')
                                    : [];
                            })
                            ->nullable()
                            ->reactive(),

                        TextInput::make('name')
                            ->label('Client Space Name')
                            ->placeholder('Enter client space name')
                            ->required(),

                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Enter description')
                            ->nullable(),
                    ])
                    ->createAnother(false),
            ]);
    }

    public function render(): View
    {
        return view('livewire.client-spaces.list-client-spaces');
    }
}
