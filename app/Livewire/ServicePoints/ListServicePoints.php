<?php

namespace App\Livewire\ServicePoints;

use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
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

class ListServicePoints extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;


    public function table(Table $table): Table
    {
        $query = ServicePoint::query()->where('business_id', '!=', 1)->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Service Point')
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
                    ->visible(fn() => in_array('Edit Service Points', Auth::user()->permissions))
                    ->modalHeading('Edit Service Point')
                    ->form(fn (ServicePoint $record) => $this->servicePointFormFields())
                    ->successNotificationTitle('Service Point updated successfully.'),

                DeleteAction::make()
                    ->visible(fn() => in_array('Delete Service Points', Auth::user()->permissions))
                    ->modalHeading('Delete Service Point')
                    ->successNotificationTitle('Service Point deleted (soft) successfully.'),
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
                    ->label('Create Service Point')
                    ->visible(fn() => in_array('Add Service Points', Auth::user()->permissions))
                    ->modalHeading('Add New Service Point')
                    ->form($this->servicePointFormFields())
                    ->createAnother(false)
                    ->after(function (ServicePoint $record) {
                        Notification::make()
                            ->title('Service Point created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function servicePointFormFields(): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('Select a business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default(Auth::user()->business_id !== 1 ? Auth::user()->business_id : null)
                ->disabled(fn () => Auth::user()->business_id !== 1)
                ->dehydrated()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('branch_id', null)),

            Forms\Components\Select::make('branch_id')
                ->label('Branch')
                ->placeholder('Select a branch')
                ->options(fn (Get $get): array => $this->branchOptionsForBusiness($get('business_id')))
                ->required()
                ->searchable()
                ->disabled(fn (Get $get): bool => ! $get('business_id')),

            TextInput::make('name')
                ->label('Service Point Name')
                ->placeholder('Enter service point name')
                ->required(),

            Textarea::make('description')
                ->label('Description')
                ->placeholder('Enter service point description')
                ->nullable(),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function branchOptionsForBusiness(mixed $businessId): array
    {
        if (! $businessId) {
            return [];
        }

        return Branch::query()
            ->where('business_id', (int) $businessId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function render(): View
    {
        return view('livewire.service_points.list-service-points');
    }
}
