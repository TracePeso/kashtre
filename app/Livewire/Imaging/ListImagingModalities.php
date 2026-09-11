<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingModality;
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
 * Settings > Imaging Module Settings > Manage Imaging Modalities — the
 * reusable master list ImagingProtocol's Modality field picks from, and the
 * source of the DICOM code Orthanc worklist entries are tagged with. Adding
 * a row here (or toggling Involves Ionizing Radiation) needs no code change,
 * replacing the previous hardcoded MODALITY_OPTIONS/IONIZING_MODALITIES/
 * DICOM_MODALITY_CODES consts scattered across the Imaging module.
 */
class ListImagingModalities extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected function formFields(): array
    {
        $businessId = Auth::user()->business_id;

        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('System-wide (all businesses)')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->default($businessId !== 1 ? $businessId : null)
                ->disabled(fn () => $businessId !== 1)
                ->helperText('Leave blank to make this modality available to every business.'),

            Forms\Components\TextInput::make('code')
                ->label('Code')
                ->placeholder('e.g. XRAY')
                ->required()
                ->maxLength(255)
                ->unique(table: ImagingModality::class, column: 'code', ignoreRecord: true),

            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->placeholder('e.g. X-Ray')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('dicom_code')
                ->label('DICOM Code')
                ->placeholder('e.g. DX')
                ->maxLength(16)
                ->helperText('The real DICOM Modality (0008,0060) code — required for this modality\'s studies to reach a PACS worklist. Leave blank only if this modality never goes through Orthanc.'),

            Forms\Components\Toggle::make('is_ionizing')
                ->label('Involves Ionizing Radiation')
                ->helperText('Default for new protocols using this modality — still overridable per protocol.')
                ->extraAttributes(['class' => 'kt-toggle']),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->extraAttributes(['class' => 'kt-toggle']),
        ];
    }

    protected function mutateFormData(array $data): array
    {
        $businessId = Auth::user()->business_id;

        // The Business select is disabled (not just visually read-only) for
        // non-super-admins, and Filament does not dehydrate disabled fields —
        // force it server-side rather than trusting a client-submitted value.
        if ($businessId !== 1) {
            $data['business_id'] = $businessId;
        }

        return $data;
    }

    public function table(Table $table): Table
    {
        $businessId = Auth::user()->business_id;

        $query = ImagingModality::query()->latest();

        if ($businessId !== 1) {
            $query->availableToBusiness($businessId);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('dicom_code')
                    ->label('DICOM Code')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->default('System-wide'),

                Tables\Columns\IconColumn::make('is_ionizing')
                    ->label('Ionizing')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn () => in_array('Manage Imaging Modalities', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Imaging Modality')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->successNotificationTitle('Imaging modality updated successfully.'),

                DeleteAction::make()
                    ->visible(fn () => in_array('Manage Imaging Modalities', Auth::user()->permissions ?? []))
                    ->modalHeading('Delete Imaging Modality')
                    ->successNotificationTitle('Imaging modality deleted successfully.'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Imaging Modality')
                    ->visible(fn () => in_array('Manage Imaging Modalities', Auth::user()->permissions ?? []))
                    ->modalHeading('Add Imaging Modality')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Imaging modality created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-modalities');
    }
}
