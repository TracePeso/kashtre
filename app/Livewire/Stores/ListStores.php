<?php

namespace App\Livewire\Stores;

use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryModuleConfig;
use App\Models\Item;
use App\Models\Store;
use App\Services\Inventory\InventoryCrashCartService;
use App\Support\InventoryBusinessContext;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ListStores extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = Store::query()
            ->with(['business', 'branch', 'parent'])
            ->withCount([
                'children',
                'endStoreChildren',
                'satelliteChildren',
            ])
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
                    ->description(function (Store $record): ?string {
                        if ($record->isSatelliteStore() && $record->parent) {
                            $label = $record->isCrashCart() ? 'Crash cart under ' : 'Satellite under ';

                            return $label.$record->parent->name;
                        }

                        if ($record->isEndStore() && $record->parent) {
                            $bits = ['Under ' . $record->parent->name];
                            if (($record->satellite_children_count ?? 0) > 0) {
                                $bits[] = $record->satellite_children_count . ' satellite(s)';
                            }

                            return implode(' · ', $bits);
                        }

                        if ($record->isInterimDistributionStore()) {
                            $bits = [];
                            if (($record->end_store_children_count ?? 0) > 0) {
                                $bits[] = $record->end_store_children_count . ' end store(s)';
                            }
                            if (($record->satellite_children_count ?? 0) > 0) {
                                $bits[] = $record->satellite_children_count . ' satellite(s)';
                            }

                            return $bits !== [] ? implode(' · ', $bits) : null;
                        }

                        return null;
                    }),
                Tables\Columns\TextColumn::make('distribution_type')
                    ->label('Store type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Store $record): string => $record->isSatelliteStore()
                        ? ($record->satelliteRoleLabel() ?? 'Satellite')
                        : $record->distributionTypeLabel())
                    ->color(fn (Store $record): string => $record->isCrashCart()
                        ? 'danger'
                        : $record->distributionTypeBadgeColor())
                    ->sortable(),
                Tables\Columns\TextColumn::make('crash_cart_status')
                    ->label('Cart status')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state, Store $record): ?string => $record->crashCartStatusLabel())
                    ->color(fn (Store $record): string => $record->crashCartStatusBadgeColor()),
                Tables\Columns\TextColumn::make('crash_cart_seal_number')
                    ->label('Seal')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent store')
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
                    ->modalHeading(fn (Store $record) => match (true) {
                        $record->isCrashCart() => 'Edit Crash Cart',
                        $record->isSatelliteStore() => 'Edit Satellite Store',
                        $record->isEndStore() => 'Edit End Store',
                        default => 'Edit Distribution Store',
                    })
                    ->form(fn (Store $record) => $this->storeForm($record))
                    ->mutateRecordDataUsing(function (array $data, Store $record): array {
                        if ($record->isCrashCart()) {
                            $data['crash_cart_items'] = $record->crashCartItems()
                                ->get(['item_id', 'par_quantity'])
                                ->map(fn ($line) => [
                                    'item_id' => $line->item_id,
                                    'par_quantity' => $line->par_quantity,
                                ])
                                ->all();
                        }

                        return $data;
                    })
                    ->using(function (Store $record, array $data): Store {
                        $manifest = $data['crash_cart_items'] ?? null;
                        unset($data['crash_cart_items']);

                        if ($record->isEndStore() || ($data['distribution_type'] ?? null) === Store::DISTRIBUTION_END) {
                            $strategy = $data['default_fulfillment_strategy']
                                ?? $record->default_fulfillment_strategy
                                ?? \App\Support\InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE;
                            $data['supports_approved_pool'] = \App\Support\InventoryFulfillmentStrategy::supportsApprovedPool($strategy);
                        }

                        $record->update($data);

                        if ($record->isCrashCart() && is_array($manifest)) {
                            app(InventoryCrashCartService::class)->syncManifest(
                                $record->fresh(),
                                $manifest,
                                Auth::user()
                            );
                        }

                        return $record->fresh();
                    })
                    ->successNotificationTitle('Store updated successfully.'),
                DeleteAction::make()
                    ->visible(fn () => in_array('Delete Stores', Auth::user()->permissions ?? []))
                    ->before(function (Store $record) {
                        if ($record->children()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete store')
                                ->body('Reassign or delete linked child stores first.')
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
                    ->modalDescription('Main warehouse or hub. Supplies End Stores below it.')
                    ->form($this->distributionStoreForm())
                    ->mutateFormDataUsing(fn (array $data) => array_merge($data, [
                        'parent_id' => null,
                        'distribution_type' => Store::DISTRIBUTION_INTERIM,
                    ]))
                    ->createAnother(false)
                    ->after(fn () => $this->notifyCreated()),
                CreateAction::make('createEndStore')
                    ->visible(fn () => in_array('Add Stores', Auth::user()->permissions ?? []))
                    ->label('Create End Store')
                    ->modalHeading('Add End Store')
                    ->modalDescription('Dispensary / POS gate. Must sit under a Distribution Store. Only End Stores sell or dispense to clients.')
                    ->form($this->endStoreForm())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['distribution_type'] = Store::DISTRIBUTION_END;
                        $strategy = $data['default_fulfillment_strategy']
                            ?? \App\Support\InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE;
                        $data['supports_approved_pool'] = \App\Support\InventoryFulfillmentStrategy::supportsApprovedPool($strategy);

                        return $data;
                    })
                    ->createAnother(false)
                    ->after(fn () => $this->notifyCreated()),
                CreateAction::make('createSatelliteStore')
                    ->visible(fn () => InventoryBusinessContext::floorStockEnabled()
                        && in_array('Add Stores', Auth::user()->permissions ?? []))
                    ->label('Create Satellite Store')
                    ->modalHeading('Add Satellite Store')
                    ->modalDescription('Floor stock under an End Store (e.g. Ward Stock, ICU, Theatre, Crash Cart). Not used for client sales.')
                    ->form($this->satelliteStoreForm())
                    ->mutateFormDataUsing(fn (array $data) => array_merge($data, [
                        'distribution_type' => Store::DISTRIBUTION_SATELLITE,
                    ]))
                    ->using(function (array $data): Store {
                        $manifest = $data['crash_cart_items'] ?? [];
                        unset($data['crash_cart_items']);

                        if (($data['satellite_role'] ?? null) === Store::SATELLITE_ROLE_CRASH_CART) {
                            $data['crash_cart_sealed_at'] = now();
                        }

                        $store = Store::create($data);

                        if ($store->isCrashCart()) {
                            app(InventoryCrashCartService::class)->syncManifest(
                                $store,
                                is_array($manifest) ? $manifest : [],
                                Auth::user()
                            );
                        }

                        return $store;
                    })
                    ->createAnother(false)
                    ->after(fn () => $this->notifyCreated()),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function distributionStoreForm(): array
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
            Forms\Components\Hidden::make('distribution_type')
                ->default(Store::DISTRIBUTION_INTERIM),
            Forms\Components\Placeholder::make('type_note')
                ->label('Store type')
                ->content('Distribution store — warehouse / hub.'),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable()
                ->rows(2),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function endStoreForm(): array
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
                ->options(fn (Get $get) => $this->distributionParentOptions(
                    businessId: $this->resolvedBusinessId($get('business_id'))
                ))
                ->required()
                ->searchable()
                ->disabled(fn (Get $get) => ! $this->resolvedBusinessId($get('business_id')))
                ->helperText(fn (Get $get) => $this->resolvedBusinessId($get('business_id'))
                    ? 'Create a Distribution Store first if the list is empty. The End Store inherits its branch.'
                    : 'Select a business first.'),
            Forms\Components\TextInput::make('name')
                ->label('End store name')
                ->placeholder('e.g. Main Pharmacy, Outpatient Dispensary')
                ->required()
                ->maxLength(255),
            Forms\Components\Hidden::make('distribution_type')
                ->default(Store::DISTRIBUTION_END),
            Forms\Components\Select::make('default_fulfillment_strategy')
                ->label('Default fulfillment strategy')
                ->options(\App\Support\InventoryFulfillmentStrategy::options())
                ->default(\App\Support\InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE)
                ->required()
                ->native(false)
                ->helperText('Outpatient = immediate dispense (no Approved Pool). Inpatient (batch & stage) credits Approved Pool after release. Used when POS/invoice does not override.'),
            Forms\Components\TextInput::make('reorder_level_days')
                ->label('Reorder level (days)')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(5)
                ->helperText('Draft replenishment when current stock days fall below this.'),
            Forms\Components\TextInput::make('max_stock_days')
                ->label('Maximum stock level (days)')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(30)
                ->helperText('Target coverage days for internal replenishment.'),
            Forms\Components\Placeholder::make('type_note')
                ->label('Store type')
                ->content('End store — only this type can sell / dispense to clients.'),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable()
                ->rows(2),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function satelliteStoreForm(): array
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
                ->label('End store')
                ->options(fn (Get $get) => $this->endStoreParentOptions(
                    businessId: $this->resolvedBusinessId($get('business_id'))
                ))
                ->required()
                ->searchable()
                ->live()
                ->disabled(fn (Get $get) => ! $this->resolvedBusinessId($get('business_id')))
                ->helperText(fn (Get $get) => $this->resolvedBusinessId($get('business_id'))
                    ? 'Create an End Store first if the list is empty. Satellite inherits its branch.'
                    : 'Select a business first.'),
            Forms\Components\TextInput::make('name')
                ->label('Satellite store name')
                ->placeholder('e.g. Ward Stock, ICU Stock, Crash Cart A')
                ->required()
                ->maxLength(255),
            Forms\Components\Hidden::make('distribution_type')
                ->default(Store::DISTRIBUTION_SATELLITE),
            Forms\Components\Select::make('satellite_role')
                ->label('Satellite role')
                ->options(fn (Get $get): array => $this->satelliteRoleFormOptions(
                    $this->resolvedBusinessId($get('business_id'))
                ))
                ->default(Store::SATELLITE_ROLE_NORMAL)
                ->required()
                ->native(false)
                ->live()
                ->helperText('Crash carts are satellites under an End Store with a fixed manifest. Break seal → record usage.')
                ->afterStateUpdated(function ($state, Set $set) {
                    $set('crash_cart_status', $state === Store::SATELLITE_ROLE_CRASH_CART
                        ? Store::CRASH_CART_READY
                        : null);
                    $set('is_crash_cart', $state === Store::SATELLITE_ROLE_CRASH_CART);
                }),
            Forms\Components\Hidden::make('is_crash_cart')->default(false),
            Forms\Components\Hidden::make('crash_cart_status'),
            ...$this->crashCartManifestFields(),
            Forms\Components\Placeholder::make('type_note')
                ->label('Store type')
                ->content(fn (Get $get): string => $get('satellite_role') === Store::SATELLITE_ROLE_CRASH_CART
                    ? 'Crash cart — fixed manifest under an End Store. Break seal to use items.'
                    : 'Satellite store — floor stock under an End Store. Not for client sales.'),
            Forms\Components\TextInput::make('reorder_level_days')
                ->label('Reorder level (days)')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(5),
            Forms\Components\TextInput::make('max_stock_days')
                ->label('Maximum stock level (days)')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(30),
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
        if ($record->isSatelliteStore()) {
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
                    ->label('End store')
                    ->options(fn (Get $get) => $this->endStoreParentOptions(
                        excludeId: $record->id,
                        businessId: $this->resolvedBusinessId($get('business_id') ?: $record->business_id)
                    ))
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('name')
                    ->label('Satellite store name')
                    ->required(),
                Forms\Components\Hidden::make('distribution_type')
                    ->default(Store::DISTRIBUTION_SATELLITE),
                Forms\Components\Select::make('satellite_role')
                    ->label('Satellite role')
                    ->options(fn (Get $get): array => $this->satelliteRoleFormOptions(
                        $this->resolvedBusinessId($get('business_id') ?: $record->business_id),
                        $record->satellite_role
                    ))
                    ->required()
                    ->native(false)
                    ->live()
                    ->helperText('Crash carts are satellites under an End Store with a fixed manifest. Break seal → record usage.')
                    ->afterStateUpdated(function ($state, Set $set) {
                        $set('crash_cart_status', $state === Store::SATELLITE_ROLE_CRASH_CART
                            ? Store::CRASH_CART_READY
                            : null);
                        $set('is_crash_cart', $state === Store::SATELLITE_ROLE_CRASH_CART);
                    }),
                Forms\Components\Hidden::make('is_crash_cart'),
                Forms\Components\Hidden::make('crash_cart_status'),
                ...$this->crashCartManifestFields(forEdit: true, record: $record),
                Forms\Components\Placeholder::make('type_note')
                    ->label('Store type')
                    ->content(fn (Get $get): string => $get('satellite_role') === Store::SATELLITE_ROLE_CRASH_CART
                        ? 'Satellite · Crash cart'
                        : 'Satellite'),
                Forms\Components\Placeholder::make('crash_status_note')
                    ->label('Cart status')
                    ->content(fn () => $record->crashCartStatusLabel() ?? '—')
                    ->visible(fn (Get $get): bool => $get('satellite_role') === Store::SATELLITE_ROLE_CRASH_CART),
                Forms\Components\Placeholder::make('crash_seal_note')
                    ->label('Current seal')
                    ->content(fn () => $record->crash_cart_seal_number
                        ? $record->crash_cart_seal_number.($record->crash_cart_sealed_at
                            ? ' · sealed '.$record->crash_cart_sealed_at->format('d M Y H:i')
                            : '')
                        : 'Not sealed yet')
                    ->visible(fn (Get $get): bool => $get('satellite_role') === Store::SATELLITE_ROLE_CRASH_CART
                        && $record->isCrashCartSealed()),
                Forms\Components\TextInput::make('reorder_level_days')
                    ->label('Reorder level (days)')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                Forms\Components\TextInput::make('max_stock_days')
                    ->label('Maximum stock level (days)')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->nullable(),
            ];
        }

        if ($record->isEndStore() || $record->isChild()) {
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
                    ->options(fn (Get $get) => $this->distributionParentOptions(
                        excludeId: $record->id,
                        businessId: $this->resolvedBusinessId($get('business_id') ?: $record->business_id)
                    ))
                    ->required()
                    ->searchable()
                    ->disabled($record->satelliteChildren()->exists()),
                Forms\Components\TextInput::make('name')
                    ->label('End store name')
                    ->required(),
                Forms\Components\Hidden::make('distribution_type')
                    ->default(Store::DISTRIBUTION_END),
                Forms\Components\Select::make('default_fulfillment_strategy')
                    ->label('Default fulfillment strategy')
                    ->options(\App\Support\InventoryFulfillmentStrategy::options())
                    ->default(fn () => $record->default_fulfillment_strategy
                        ?: \App\Support\InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE)
                    ->required()
                    ->native(false)
                    ->helperText('Outpatient = immediate dispense (no Approved Pool). Inpatient (batch & stage) credits Approved Pool after release. Used when POS/invoice does not override.'),
                Forms\Components\TextInput::make('reorder_level_days')
                    ->label('Reorder level (days)')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->helperText('Draft replenishment when current stock days fall below this.'),
                Forms\Components\TextInput::make('max_stock_days')
                    ->label('Maximum stock level (days)')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->helperText('Target coverage days for internal replenishment.'),
                Forms\Components\Placeholder::make('type_note')
                    ->label('Store type')
                    ->content(
                        ($record->satellite_children_count ?? $record->satelliteChildren()->count()) > 0
                            ? 'End store — has satellite floor-stock children.'
                            : 'End store — client sales / dispensing gate.'
                    ),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->nullable(),
                Forms\Components\Placeholder::make('satellites_note')
                    ->label('Linked satellites')
                    ->content(fn () => $record->satelliteChildren()->count() > 0
                        ? $record->satelliteChildren()->count() . ' satellite store(s) linked under this End Store.'
                        : 'No satellites yet. Use “Create Satellite Store” for ward / ICU / crash-cart stock.'),
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
            Forms\Components\Hidden::make('distribution_type')
                ->default(Store::DISTRIBUTION_INTERIM),
            Forms\Components\TextInput::make('reorder_level_days')
                ->label('Reorder level (days)')
                ->numeric()
                ->minValue(0)
                ->step(0.01),
            Forms\Components\TextInput::make('max_stock_days')
                ->label('Maximum stock level (days)')
                ->numeric()
                ->minValue(0)
                ->step(0.01),
            Forms\Components\Placeholder::make('type_note')
                ->label('Store type')
                ->content('Distribution store — warehouse / hub.'),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->nullable(),
            Forms\Components\Placeholder::make('children_note')
                ->label('Linked end stores')
                ->content(fn () => $record->endStoreChildren()->count() > 0
                    ? $record->endStoreChildren()->count() . ' end store(s) linked to this distribution store.'
                    : 'No end stores linked yet. Use “Create End Store” to add a dispensary under this hub.'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function distributionParentOptions(?int $excludeId = null, ?int $businessId = null): array
    {
        $businessId = $this->resolvedBusinessId($businessId);

        if (! $businessId) {
            return [];
        }

        $query = Store::query()
            ->with('parent')
            ->where('business_id', $businessId)
            ->where('distribution_type', Store::DISTRIBUTION_INTERIM)
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get()
            ->filter(fn (Store $store) => $store->canAcceptEndStoreChildren())
            ->mapWithKeys(fn (Store $store) => [$store->id => $store->selectLabel()])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected function endStoreParentOptions(?int $excludeId = null, ?int $businessId = null): array
    {
        $businessId = $this->resolvedBusinessId($businessId);

        if (! $businessId) {
            return [];
        }

        $query = Store::query()
            ->with('parent')
            ->where('business_id', $businessId)
            ->where('distribution_type', Store::DISTRIBUTION_END)
            ->orderBy('name');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get()
            ->filter(fn (Store $store) => $store->canAcceptSatelliteChildren())
            ->mapWithKeys(fn (Store $store) => [$store->id => $store->selectLabel()])
            ->all();
    }

    protected function resolveFormBusinessId(Get $get, ?Store $record = null): ?int
    {
        $candidates = [
            $get('business_id'),
            $get('../../business_id'),
            $record?->business_id,
        ];

        $parentId = $get('parent_id') ?: $get('../../parent_id');
        if ($parentId) {
            $candidates[] = Store::query()->whereKey($parentId)->value('business_id');
        }

        foreach ($candidates as $candidate) {
            $resolved = $this->resolvedBusinessId($candidate ? (int) $candidate : null);
            if ($resolved) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function crashCartManifestFields(bool $forEdit = false, ?Store $record = null): array
    {
        return [
            Forms\Components\TextInput::make('crash_cart_seal_number')
                ->label('Seal number')
                ->maxLength(100)
                ->required(fn (Get $get): bool => $get('satellite_role') === Store::SATELLITE_ROLE_CRASH_CART
                    && ! ($forEdit && $record?->isCrashCartOpen()))
                ->disabled(fn (): bool => $forEdit && ($record?->isCrashCartOpen() ?? false))
                ->dehydrated(fn (): bool => ! ($forEdit && ($record?->isCrashCartOpen() ?? false)))
                ->visible(fn (Get $get): bool => $get('satellite_role') === Store::SATELLITE_ROLE_CRASH_CART),
            Forms\Components\Repeater::make('crash_cart_items')
                ->label('Fixed cart contents')
                ->schema([
                    Forms\Components\Select::make('item_id')
                        ->label('Item')
                        ->options(fn (Get $get) => $this->goodItemOptions(
                            $this->resolveFormBusinessId($get, $record)
                        ))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    Forms\Components\TextInput::make('par_quantity')
                        ->label('Quantity')
                        ->numeric()
                        ->minValue(0.0001)
                        ->step(0.0001)
                        ->required(),
                ])
                ->minItems(1)
                ->defaultItems(1)
                ->addActionLabel('Add item')
                ->columns(2)
                ->disabled(fn (): bool => $forEdit && ($record?->isCrashCartOpen() ?? false))
                ->dehydrated(fn (): bool => ! ($forEdit && ($record?->isCrashCartOpen() ?? false)))
                ->visible(fn (Get $get): bool => $get('satellite_role') === Store::SATELLITE_ROLE_CRASH_CART)
                ->helperText(fn (Get $get): string => $this->resolveFormBusinessId($get, $record)
                    ? 'Every crash cart must have a complete, known item list. Stock is set to these quantities while sealed.'
                    : 'Select a business and End store first to load items.'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function goodItemOptions(?int $businessId): array
    {
        if (! $businessId) {
            return [];
        }

        return Item::query()
            ->where('business_id', $businessId)
            ->where('type', 'good')
            ->orderBy('name')
            ->get(['id', 'name', 'strength'])
            ->mapWithKeys(fn (Item $item) => [
                $item->id => trim($item->name.($item->strength ? ' '.$item->strength : '')),
            ])
            ->all();
    }

    protected function resolvedBusinessId(?int $businessId): ?int
    {
        if (Auth::user()->business_id !== 1) {
            return (int) Auth::user()->business_id;
        }

        return $businessId ? (int) $businessId : null;
    }

    protected function businessAllowsCrashCart(?int $businessId): bool
    {
        if (! $businessId) {
            return false;
        }

        $config = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->first();

        return $config?->crashCartEnabled() ?? false;
    }

    /**
     * @return array<string, string>
     */
    protected function satelliteRoleFormOptions(?int $businessId, ?string $currentRole = null): array
    {
        $options = [
            Store::SATELLITE_ROLE_NORMAL => 'Normal floor stock',
        ];

        if ($this->businessAllowsCrashCart($businessId)
            || $currentRole === Store::SATELLITE_ROLE_CRASH_CART) {
            $options[Store::SATELLITE_ROLE_CRASH_CART] = 'Crash cart';
        }

        return $options;
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
