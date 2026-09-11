<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingCriticalFindingType;
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
 * Settings > Manage Critical Findings — the reusable dictionary of named
 * critical-finding conditions (Intracranial Bleed, Pneumothorax, ...) that
 * radiologists pick from when flagging a report, instead of a bare boolean.
 */
class ListImagingCriticalFindingTypes extends Component implements HasForms, HasTable
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
                ->helperText('Leave blank to make this finding available to every business.'),

            Forms\Components\TextInput::make('code')
                ->label('Code')
                ->placeholder('e.g. INTRACRANIAL_BLEED')
                ->required()
                ->maxLength(255)
                ->unique(table: ImagingCriticalFindingType::class, column: 'code', ignoreRecord: true),

            Forms\Components\TextInput::make('label')
                ->label('Label')
                ->placeholder('e.g. Intracranial Bleed')
                ->required()
                ->maxLength(255),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
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

        $query = ImagingCriticalFindingType::query()->latest();

        if ($businessId !== 1) {
            $query->availableToBusiness($businessId);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->default('System-wide'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn () => in_array('Manage Imaging Critical Findings', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Critical Finding Type')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->successNotificationTitle('Critical finding type updated successfully.'),

                DeleteAction::make()
                    ->visible(fn () => in_array('Manage Imaging Critical Findings', Auth::user()->permissions ?? []))
                    ->modalHeading('Delete Critical Finding Type')
                    ->successNotificationTitle('Critical finding type deleted successfully.'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Critical Finding Type')
                    ->visible(fn () => in_array('Manage Imaging Critical Findings', Auth::user()->permissions ?? []))
                    ->modalHeading('Add Critical Finding Type')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Critical finding type created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-critical-finding-types');
    }
}
