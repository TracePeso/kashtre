<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingReadinessCheckType;
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
 * Settings > Manage Imaging Readiness Checks — the reusable master list that
 * ImagingProtocol's Preparation Requirements / Readiness Checks fields pick
 * from, instead of free-typed tags per protocol.
 */
class ListImagingReadinessCheckTypes extends Component implements HasForms, HasTable
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
                ->helperText('Leave blank to make this check type available to every business.'),

            Forms\Components\TextInput::make('code')
                ->label('Code')
                ->placeholder('e.g. fasting')
                ->required()
                ->maxLength(255)
                ->unique(table: ImagingReadinessCheckType::class, column: 'code', ignoreRecord: true),

            Forms\Components\TextInput::make('label')
                ->label('Label')
                ->placeholder('e.g. Fasting')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('category')
                ->label('Category')
                ->options([
                    ImagingReadinessCheckType::CATEGORY_PREPARATION => 'Preparation Requirement',
                    ImagingReadinessCheckType::CATEGORY_READINESS => 'Readiness Check',
                ])
                ->required(),

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

        $query = ImagingReadinessCheckType::query()->latest();

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

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === ImagingReadinessCheckType::CATEGORY_PREPARATION
                        ? 'Preparation'
                        : 'Readiness'),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->default('System-wide'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        ImagingReadinessCheckType::CATEGORY_PREPARATION => 'Preparation Requirement',
                        ImagingReadinessCheckType::CATEGORY_READINESS => 'Readiness Check',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn () => in_array('Manage Imaging Readiness Checks', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Readiness Check Type')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->successNotificationTitle('Readiness check type updated successfully.'),

                DeleteAction::make()
                    ->visible(fn () => in_array('Manage Imaging Readiness Checks', Auth::user()->permissions ?? []))
                    ->modalHeading('Delete Readiness Check Type')
                    ->successNotificationTitle('Readiness check type deleted successfully.'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Readiness Check Type')
                    ->visible(fn () => in_array('Manage Imaging Readiness Checks', Auth::user()->permissions ?? []))
                    ->modalHeading('Add Readiness Check Type')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Readiness check type created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-readiness-check-types');
    }
}
