<?php

namespace App\Livewire\Stores;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListStores extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = Store::query()
            ->with(['business', 'branch', 'parent'])
            ->withCount('children')
            ->where('business_id', '!=', 1)
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name');

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Store')
                    ->searchable()
                    ->description(fn (Store $record): ?string => $record->isChild() && $record->parent
                        ? 'Under ' . $record->parent->name
                        : ($record->children_count > 0 ? $record->children_count . ' linked end store(s)' : null)),
                Tables\Columns\TextColumn::make('distribution_type')
                    ->label('Store type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Store $record): string => $record->distributionTypeLabel())
                    ->color(fn (Store $record): string => $record->isInterimDistributionStore() ? 'warning' : 'primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Distribution store')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('distribution_type')
                    ->label('Store type')
                    ->options(Store::distributionTypeOptions())
                    ->searchable(),
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
                    ->label('Edit')
                    ->visible(fn () => in_array('Edit Stores', Auth::user()->permissions ?? []))
                    ->modalHeading(fn (Store $record) => $record->isEndStore() ? 'Edit End Store' : 'Edit Distribution Store')
                    ->form(fn (Store $record) => $this->storeForm($record))
                    ->successNotificationTitle('Store updated successfully.'),
                DeleteAction::make()
                    ->visible(fn () => in_array('Delete Stores', Auth::user()->permissions ?? []))
                    ->before(function (Store $record) {
                        if ($record->children()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete distribution store')
                                ->body('Reassign or delete linked end stores first.')
                                ->send();

                            $this->halt();
                        }
                    })
                    ->successNotificationTitle('Store deleted successfully.'),
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
                    ->visible(fn () => in_array('Add Stores', Auth::user()->permissions ?? []))
                    ->label('Create Distribution Store')
                    ->modalHeading('Add Distribution Store')
                    ->modalDescription('Warehouse or hub that supplies end stores. You can link end stores under it afterwards.')
                    ->form($this->parentStoreForm())
                    ->mutateFormDataUsing(fn (array $data) => array_merge($data, [
                        'parent_id' => null,
                        'distribution_type' => Store::DISTRIBUTION_INTERIM,
                    ]))
                    ->createAnother(false)
                    ->after(fn () => $this->notifyCreated()),
                CreateAction::make('createChildStore')
                    ->visible(fn () => in_array('Add Stores', Auth::user()->permissions ?? []))
                    ->label('Create End Store')
                    ->modalHeading('Add End Store')
                    ->modalDescription('Customer-facing POS or dispensing location. Select a business, then choose the distribution store it belongs to.')
                    ->form($this->childStoreForm())
                    ->mutateFormDataUsing(fn (array $data) => array_merge($data, [
                        'distribution_type' => Store::DISTRIBUTION_END,
                    ]))
                    ->createAnother(false)
                    ->after(fn () => $this->notifyCreated()),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function parentStoreForm(): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default(Auth::user()->business_id)
                ->disabled(fn () => Auth::user()->business_id !== 1)
                ->live(),
            Forms\Components\Select::make('branch_id')
                ->label('Branch')
                ->options(fn (Get $get) => $this->branchOptions($get('business_id')))
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('name')
                ->label('Store name')
                ->required()
                ->maxLength(255),
            ...$this->distributionTypeFields(forParentStore: true),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable()
                ->rows(2),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function childStoreForm(): array
    {
        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->default(fn () => Auth::user()->business_id !== 1 ? Auth::user()->business_id : null)
                ->disabled(fn () => Auth::user()->business_id !== 1)
                ->dehydrated(false)
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),
            Forms\Components\Select::make('parent_id')
                ->label('Distribution store')
                ->options(fn (Get $get) => $this->parentStoreOptions(
                    businessId: $this->resolvedBusinessId($get('business_id'))
                ))
                ->required()
                ->searchable()
                ->disabled(fn (Get $get) => ! $this->resolvedBusinessId($get('business_id')))
                ->helperText(fn (Get $get) => $this->resolvedBusinessId($get('business_id'))
                    ? 'The end store inherits the distribution store’s branch.'
                    : 'Select a business first.'),
            Forms\Components\TextInput::make('name')
                ->label('End store name')
                ->required()
                ->maxLength(255),
            ...$this->distributionTypeFields(forChildStore: true),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable()
                ->rows(2),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function storeForm(Store $record): array
    {
        if ($record->isChild()) {
            return [
                Forms\Components\Select::make('business_id')
                    ->label('Business')
                    ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                    ->default($record->business_id)
                    ->disabled(fn () => Auth::user()->business_id !== 1)
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),
                Forms\Components\Select::make('parent_id')
                    ->label('Distribution store')
                    ->options(fn (Get $get) => $this->parentStoreOptions(
                        excludeId: $record->id,
                        businessId: $this->resolvedBusinessId($get('business_id') ?: $record->business_id)
                    ))
                    ->required()
                    ->searchable()
                    ->disabled($record->children()->exists()),
                Forms\Components\TextInput::make('name')
                    ->label('Store name')
                    ->required(),
                ...$this->distributionTypeFields(
                    forChildStore: ! $record->hasChildren(),
                    record: $record,
                ),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->nullable(),
            ];
        }

        return [
            Forms\Components\Select::make('business_id')
                ->label('Business')
                ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                ->required()
                ->disabled(fn () => Auth::user()->business_id !== 1)
                ->live(),
            Forms\Components\Select::make('branch_id')
                ->label('Branch')
                ->options(fn (Get $get) => $this->branchOptions($get('business_id') ?: $record->business_id))
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('name')
                ->label('Store name')
                ->required(),
            ...$this->distributionTypeFields(forParentStore: true, record: $record),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable(),
            Forms\Components\Placeholder::make('children_note')
                ->label('Linked end stores')
                ->content(fn () => $record->children()->count() > 0
                    ? $record->children()->count() . ' end store(s) linked to this distribution store.'
                    : 'No end stores linked yet. Use “Create End Store” to add one under this distribution store.'),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function distributionTypeFields(
        bool $forParentStore = false,
        bool $forChildStore = false,
        ?Store $record = null,
    ): array {
        $hasChildren = $record?->hasChildren() ?? false;
        $lockedToDistribution = $forParentStore || $hasChildren;

        return [
            Forms\Components\Select::make('distribution_type')
                ->label('Store type')
                ->options($lockedToDistribution
                    ? [Store::DISTRIBUTION_INTERIM => 'Distribution store']
                    : ($forChildStore
                        ? [Store::DISTRIBUTION_END => 'End store']
                        : Store::distributionTypeOptions()))
                ->default($lockedToDistribution ? Store::DISTRIBUTION_INTERIM : Store::DISTRIBUTION_END)
                ->required()
                ->disabled($lockedToDistribution)
                ->dehydrated(true)
                ->native(false)
                ->helperText(match (true) {
                    $hasChildren => 'Stores with linked end stores are always distribution stores.',
                    $forParentStore => 'Distribution stores are warehouses or hubs that supply end stores.',
                    $forChildStore => 'End stores are customer-facing (POS or dispensing).',
                    default => 'Choose distribution store for warehouses, or end store for customer-facing locations.',
                }),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function parentStoreOptions(?int $excludeId = null, ?int $businessId = null): array
    {
        $businessId = $this->resolvedBusinessId($businessId);

        if (! $businessId) {
            return [];
        }

        $query = Store::query()
            ->with('parent')
            ->where('business_id', $businessId)
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get()
            ->filter(fn (Store $store) => $store->canAcceptChildStores())
            ->mapWithKeys(fn (Store $store) => [$store->id => $store->selectLabel()])
            ->all();
    }

    protected function resolvedBusinessId(?int $businessId): ?int
    {
        if (Auth::user()->business_id !== 1) {
            return (int) Auth::user()->business_id;
        }

        return $businessId ? (int) $businessId : null;
    }

    /**
     * @return array<int|string, string>
     */
    protected function branchOptions(?int $businessId): array
    {
        if (! $businessId) {
            return [];
        }

        return Branch::where('business_id', $businessId)->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function notifyCreated(): void
    {
        Notification::make()
            ->title('Store created successfully.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.stores.list-stores');
    }
}
