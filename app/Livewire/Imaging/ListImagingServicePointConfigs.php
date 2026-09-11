<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingServicePointConfig;
use App\Models\Room;
use App\Models\Store;
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

/**
 * Settings > Manage Imaging Rooms — the first-ever admin UI for
 * ImagingServicePointConfig (DICOM AE Title mapping has existed since
 * Chunk 10, tinker-only until now). Maps a real Room (Settings > Manage
 * Rooms) — not a ServicePoint — to its DICOM AE Title and the Inventory
 * Store its consumption should deplete from.
 */
class ListImagingServicePointConfigs extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected function formFields(): array
    {
        $businessId = Auth::user()->business_id;

        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('Select a business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default($businessId !== 1 ? $businessId : null)
                ->disabled(fn () => $businessId !== 1)
                ->reactive(),

            Forms\Components\Select::make('main_room_id')
                ->label('Room')
                ->placeholder('Select a room')
                ->options(fn ($get) => $get('business_id')
                    ? Room::where('business_id', $get('business_id'))->pluck('name', 'id')
                    : [])
                ->searchable()
                ->required()
                ->unique(
                    table: ImagingServicePointConfig::class,
                    column: 'main_room_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule, $get) => $rule->where('business_id', $get('business_id')),
                )
                ->validationMessages([
                    'unique' => 'This room already has a config for this business.',
                ]),

            Forms\Components\Select::make('inventory_store_id')
                ->label('Inventory Store')
                ->placeholder('Select the store this room deducts from')
                ->options(fn ($get) => $get('business_id')
                    ? Store::forBusiness($get('business_id'))->pluck('name', 'id')
                    : [])
                ->searchable()
                ->helperText('Consumption for studies performed at this room deducts from this store instead of the default (user store / highest-stock) fallback.'),

            Forms\Components\TextInput::make('hardware_ae_title')
                ->label('Hardware AE Title')
                ->placeholder('e.g. CT_ROOM_1')
                ->maxLength(255)
                ->helperText('DICOM Application Entity Title for the physical scanner in this room.'),
        ];
    }

    protected function mutateFormData(array $data): array
    {
        $businessId = Auth::user()->business_id;

        if ($businessId !== 1) {
            $data['business_id'] = $businessId;
        }

        return $data;
    }

    public function table(Table $table): Table
    {
        $businessId = Auth::user()->business_id;

        $query = ImagingServicePointConfig::query()->latest();

        if ($businessId !== 1) {
            $query->where('business_id', $businessId);
        }

        return $table
            ->query($query)
            ->emptyStateHeading('No imaging room configs')
            ->emptyStateDescription('Create an imaging room config to get started.')
            ->columns([
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business'),

                Tables\Columns\TextColumn::make('room')
                    ->label('Room')
                    ->getStateUsing(fn (ImagingServicePointConfig $record) => $record->resolveRoom()?->name ?? '—'),

                Tables\Columns\TextColumn::make('store')
                    ->label('Inventory Store')
                    ->getStateUsing(fn (ImagingServicePointConfig $record) => $record->resolveInventoryStore()?->name ?? '—'),

                Tables\Columns\TextColumn::make('hardware_ae_title')
                    ->label('AE Title')
                    ->placeholder('—'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn () => in_array('Manage Imaging Service Point Configs', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Imaging Room Config')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->successNotificationTitle('Imaging room config updated successfully.'),

                DeleteAction::make()
                    ->visible(fn () => in_array('Manage Imaging Service Point Configs', Auth::user()->permissions ?? [])),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Imaging Room Config')
                    ->visible(fn () => in_array('Manage Imaging Service Point Configs', Auth::user()->permissions ?? []))
                    ->modalHeading('Add Imaging Room Config')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Imaging room config created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-service-point-configs');
    }
}
