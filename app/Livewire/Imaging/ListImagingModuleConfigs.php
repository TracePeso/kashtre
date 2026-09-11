<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ImagingModuleConfig;
use App\Models\User;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Settings > Manage Imaging Module — the first-ever admin UI for
 * ImagingModuleConfig (has existed since the module's foundations chunk,
 * but peer_review_rate was only ever settable via tinker until now).
 * One row per business — Kashtre-super-admin only, same as every other
 * config page under Settings.
 */
class ListImagingModuleConfigs extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected function commonFields(): array
    {
        return [
            Forms\Components\TextInput::make('peer_review_rate')
                ->label('Peer Review Rate (%)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->default(ImagingModuleConfig::DEFAULT_PEER_REVIEW_RATE)
                ->required(),

            Forms\Components\Select::make('peer_review_eligible_modalities')
                ->label('Eligible Modalities')
                ->multiple()
                ->options(fn () => \App\Models\ImagingModality::active()->pluck('name', 'code'))
                ->helperText('Leave blank for every modality to be eligible for peer review.'),

            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->rows(2),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];
    }

    protected function createFormFields(): array
    {
        $existingBusinessIds = ImagingModuleConfig::pluck('business_id')->toArray();

        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->placeholder('Select a business')
                ->options(Business::where('id', '!=', 1)->whereNotIn('id', $existingBusinessIds)->pluck('name', 'id'))
                ->required()
                ->reactive(),

            ...$this->commonFields(),

            Forms\Components\Select::make('peer_review_reviewer_pool_user_ids')
                ->label('Reviewer Pool')
                ->multiple()
                ->options(fn ($get) => $get('business_id')
                    ? User::where('business_id', $get('business_id'))->where('status', 'active')->pluck('name', 'id')
                    : [])
                ->helperText('Leave blank for any user with the review permission to be eligible.'),
        ];
    }

    protected function editFormFields(ImagingModuleConfig $record): array
    {
        return [
            ...$this->commonFields(),

            Forms\Components\Select::make('peer_review_reviewer_pool_user_ids')
                ->label('Reviewer Pool')
                ->multiple()
                ->options(User::where('business_id', $record->business_id)->where('status', 'active')->pluck('name', 'id'))
                ->helperText('Leave blank for any user with the review permission to be eligible.'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ImagingModuleConfig::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business'),

                Tables\Columns\TextColumn::make('peer_review_rate')
                    ->label('Peer Review Rate')
                    ->formatStateUsing(fn (int $state) => "{$state}%"),

                // getStateUsing(), not formatStateUsing(): for an array-cast
                // attribute, Filament's TextColumn treats the raw state as a
                // *list* and calls formatStateUsing once per item (a scalar)
                // rather than once with the whole array — so a `?array $state`
                // closure there throws a TypeError as soon as the list is
                // non-empty. getStateUsing() computes the scalar summary
                // directly, before that list-rendering branch ever runs.
                Tables\Columns\TextColumn::make('peer_review_eligible_modalities')
                    ->label('Eligible Modalities')
                    ->getStateUsing(fn (ImagingModuleConfig $record) => $record->peer_review_eligible_modalities
                        ? implode(', ', $record->peer_review_eligible_modalities)
                        : 'All'),

                Tables\Columns\TextColumn::make('peer_review_reviewer_pool_user_ids')
                    ->label('Reviewer Pool')
                    ->getStateUsing(fn (ImagingModuleConfig $record) => $record->peer_review_reviewer_pool_user_ids
                        ? count($record->peer_review_reviewer_pool_user_ids).' user(s)'
                        : 'Any'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn () => in_array('Manage Imaging Module', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Imaging Module Config')
                    ->form(fn (ImagingModuleConfig $record) => $this->editFormFields($record))
                    ->mutateFormDataUsing(function (array $data) {
                        $data['updated_by'] = Auth::id();

                        return $data;
                    })
                    ->successNotificationTitle('Imaging module config updated successfully.'),

                DeleteAction::make()
                    ->visible(fn () => in_array('Manage Imaging Module', Auth::user()->permissions ?? [])),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Imaging Module Config')
                    ->visible(fn () => in_array('Manage Imaging Module', Auth::user()->permissions ?? []))
                    ->modalHeading('Add Imaging Module Config')
                    ->form(fn () => $this->createFormFields())
                    ->mutateFormDataUsing(function (array $data) {
                        $data['created_by'] = Auth::id();

                        return $data;
                    })
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Imaging module config created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-imaging-module-configs');
    }
}
