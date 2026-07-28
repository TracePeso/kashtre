<?php

namespace App\Livewire\Imaging;

use App\Models\Business;
use App\Models\ContrastVial;
use App\Models\Item;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;
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
 * Imaging > Manage Contrast Vials — operational tracking of real contrast
 * vials/kits through their lifecycle (Pillar 9.1), feeding into the
 * Contrast Administration form on the study page as an optional picker.
 */
class ListContrastVials extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected function itemOptions(?string $search, ?int $businessId): array
    {
        return Item::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->where('type', 'good')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->limit(20)
            ->pluck('name', 'id')
            ->toArray();
    }

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

            Forms\Components\Select::make('item_id')
                ->label('Catalog Item (optional)')
                ->placeholder('Search the item catalog')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search, $get) => $this->itemOptions($search, $get('business_id')))
                ->getOptionLabelUsing(fn ($value, $get) => Item::query()
                    ->when($get('business_id'), fn ($q) => $q->where('business_id', $get('business_id')))
                    ->find($value)?->name ?? $value)
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set, $get) {
                    $item = Item::query()
                        ->when($get('business_id'), fn ($q) => $q->where('business_id', $get('business_id')))
                        ->find($state);

                    if ($item) {
                        $set('agent_name', $item->name);
                    }
                }),

            Forms\Components\TextInput::make('agent_name')
                ->label('Agent Name')
                ->placeholder('e.g. Iohexol 350 mg/mL')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('lot_number')
                ->label('Lot Number')
                ->maxLength(255),

            Forms\Components\TextInput::make('total_volume_ml')
                ->label('Total Volume (mL)')
                ->numeric()
                ->minValue(0.01)
                ->required(),

            Forms\Components\TextInput::make('stability_hours')
                ->label('Stability Window (hours)')
                ->numeric()
                ->minValue(1)
                ->helperText('Leave blank if this vial has no stability-hour limit once opened.'),
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

        $query = ContrastVial::query()->latest();

        if ($businessId !== 1) {
            $query->forBusiness($businessId);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('agent_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lot_number')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ContrastVial::STATUS_UNOPENED => 'gray',
                        ContrastVial::STATUS_ONBOARD => 'success',
                        ContrastVial::STATUS_EXPIRED => 'danger',
                        ContrastVial::STATUS_EXHAUSTED => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('remaining_volume_ml')
                    ->label('Remaining / Total')
                    ->getStateUsing(fn (ContrastVial $record) => "{$record->remaining_volume_ml} / {$record->total_volume_ml} mL"),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Opened At')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(array_combine(ContrastVial::STATUSES, ContrastVial::STATUSES)),
                ...($businessId === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                Action::make('onboard')
                    ->label('Onboard')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ContrastVial $record) => $record->isStatus(ContrastVial::STATUS_UNOPENED)
                        && in_array('Manage Contrast Vials', Auth::user()->permissions ?? []))
                    ->action(function (ContrastVial $record) {
                        $record->markOnboard();

                        Notification::make()
                            ->title('Vial onboarded.')
                            ->success()
                            ->send();
                    }),

                EditAction::make()
                    ->visible(fn () => in_array('Manage Contrast Vials', Auth::user()->permissions ?? []))
                    ->modalHeading('Edit Contrast Vial')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->successNotificationTitle('Contrast vial updated successfully.'),

                DeleteAction::make()
                    ->visible(fn () => in_array('Manage Contrast Vials', Auth::user()->permissions ?? [])),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Contrast Vial')
                    ->visible(fn () => in_array('Manage Contrast Vials', Auth::user()->permissions ?? []))
                    ->modalHeading('Add Contrast Vial')
                    ->form(fn () => $this->formFields())
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateFormData($data))
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Contrast vial created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.imaging.list-contrast-vials');
    }
}
