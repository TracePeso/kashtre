<?php

namespace App\Livewire\Inventory;

use App\Models\ClientSpace;
use App\Models\ClientSpaceStoreAssignment;
use App\Models\Store;
use App\Support\InventoryBusinessContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
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

class ListClientSpaceStoreAssignments extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public bool $canManage = false;

    public function mount(): void
    {
        $this->canManage = ! InventoryBusinessContext::isAdminBrowsing()
            && in_array('Edit Business Settings', Auth::user()->permissions ?? [], true);
    }

    public function table(Table $table): Table
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        $query = ClientSpaceStoreAssignment::query()
            ->with(['clientSpace.branch', 'store.parent'])
            ->where('business_id', $businessId)
            ->latest();

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('clientSpace.name')
                    ->label('Client Space')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ClientSpaceStoreAssignment $record): ?string => $record->clientSpace?->branch?->name
                        ? 'Branch: '.$record->clientSpace->branch->name
                        : null),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('End Store')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ClientSpaceStoreAssignment $record): ?string => $record->store?->parent
                        ? 'Under '.$record->store->parent->name
                        : null),
                Tables\Columns\TextColumn::make('fulfillment_strategy')
                    ->label('Strategy')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, ClientSpaceStoreAssignment $record): string => $record->strategyLabel())
                    ->color(fn (ClientSpaceStoreAssignment $record): string => $record->fulfillment_strategy === ClientSpaceStoreAssignment::STRATEGY_BATCH_AND_STAGE
                        ? 'warning'
                        : 'primary'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->placeholder('All'),
                Tables\Filters\SelectFilter::make('fulfillment_strategy')
                    ->label('Strategy')
                    ->options(ClientSpaceStoreAssignment::strategyOptions()),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn () => $this->canManage)
                    ->modalHeading('Edit space routing')
                    ->form(fn (ClientSpaceStoreAssignment $record) => $this->formSchema($businessId, $record))
                    ->successNotificationTitle('Space routing updated.'),
                DeleteAction::make()
                    ->visible(fn () => $this->canManage)
                    ->modalHeading('Remove space routing')
                    ->successNotificationTitle('Space routing removed.'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => $this->canManage)
                    ->label('Assign End Store')
                    ->modalHeading('Assign Client Space → End Store')
                    ->modalDescription('Maps a Client Space (ward / clinic) to the End Store that receives its paid inventory queue lines (SRD §4.1).')
                    ->form($this->formSchema($businessId))
                    ->disabled(fn (): bool => $this->clientSpaceOptions($businessId) === [])
                    ->tooltip(fn (): ?string => $this->clientSpaceOptions($businessId) === []
                        ? 'Every Client Space already has an End Store. Create another Client Space, or Edit / Delete an existing row.'
                        : null)
                    ->mutateFormDataUsing(function (array $data) use ($businessId): array {
                        $data['business_id'] = $businessId;
                        $data['is_active'] = (bool) ($data['is_active'] ?? true);

                        return $data;
                    })
                    ->createAnother(false)
                    ->after(function () {
                        Notification::make()
                            ->title('Space routing created.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Client Space → End Store mappings')
            ->emptyStateDescription('Assign each Client Space to an End Store so paid goods route to the correct pharmacy queue.')
            ->emptyStateIcon('heroicon-o-map-pin')
            ->emptyStateActions([
                CreateAction::make()
                    ->visible(fn () => $this->canManage)
                    ->label('Assign End Store')
                    ->modalHeading('Assign Client Space → End Store')
                    ->form($this->formSchema($businessId))
                    ->mutateFormDataUsing(function (array $data) use ($businessId): array {
                        $data['business_id'] = $businessId;
                        $data['is_active'] = (bool) ($data['is_active'] ?? true);

                        return $data;
                    })
                    ->createAnother(false),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function formSchema(int $businessId, ?ClientSpaceStoreAssignment $record = null): array
    {
        return [
            Select::make('client_space_id')
                ->label('Client Space')
                ->options(fn () => $this->clientSpaceOptions($businessId, $record?->client_space_id))
                ->required()
                ->searchable()
                ->disabled($record !== null)
                ->dehydrated()
                ->helperText($record
                    ? 'Client Space cannot be changed. Remove and recreate to remap a different space.'
                    : 'Only spaces without an existing assignment are listed.'),
            Select::make('store_id')
                ->label('End Store')
                ->options(fn () => $this->endStoreOptions($businessId))
                ->required()
                ->searchable()
                ->helperText('Only End Stores appear here. Create them under Manage Stores first.'),
            Select::make('fulfillment_strategy')
                ->label('Fulfillment strategy')
                ->options(ClientSpaceStoreAssignment::strategyOptions())
                ->required()
                ->default(ClientSpaceStoreAssignment::STRATEGY_DISCRETE_IMMEDIATE)
                ->native(false)
                ->helperText('Outpatient = immediate discrete pick. Inpatient = batch, stage, and ward handoff.'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Inactive mappings are ignored when routing the inventory queue.'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function clientSpaceOptions(int $businessId, ?int $keepId = null): array
    {
        $assignedIds = ClientSpaceStoreAssignment::query()
            ->where('business_id', $businessId)
            ->when($keepId, fn ($q) => $q->where('client_space_id', '!=', $keepId))
            ->pluck('client_space_id')
            ->all();

        return ClientSpace::query()
            ->with('branch')
            ->where('business_id', $businessId)
            ->where(function ($query) use ($assignedIds, $keepId) {
                if ($assignedIds !== []) {
                    $query->whereNotIn('id', $assignedIds);
                }
                if ($keepId) {
                    $query->orWhere('id', $keepId);
                }
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ClientSpace $space) => [
                $space->id => $space->name.($space->branch?->name ? ' ('.$space->branch->name.')' : ''),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected function endStoreOptions(int $businessId): array
    {
        return Store::query()
            ->with('parent')
            ->where('business_id', $businessId)
            ->where('distribution_type', Store::DISTRIBUTION_END)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Store $store) => [$store->id => $store->selectLabel()])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.inventory.list-client-space-store-assignments');
    }
}
