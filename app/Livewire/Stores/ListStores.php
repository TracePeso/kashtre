<?php

namespace App\Livewire\Stores;

use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryModuleConfig;
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
                    ->formatStateUsing(fn (?string $state, Store $record): string => $record->isCrashCart()
                        ? 'Crash cart'
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
                    ->successNotificationTitle('Store updated successfully.'),
                Action::make('deployCrashCart')
                    ->label('Deploy')
                    ->color('danger')
                    ->icon('heroicon-o-truck')
                    ->requiresConfirmation()
                    ->modalHeading('Deploy crash cart')
                    ->modalDescription('Locks inbound stock until reconciliation. Record usage after deploy or while reconciling.')
                    ->visible(fn (Store $record): bool => $this->canManageCrashCartStatus($record)
                        && $record->isCrashCart()
                        && $record->crash_cart_status === Store::CRASH_CART_READY)
                    ->action(function (Store $record) {
                        try {
                            app(InventoryCrashCartService::class)->deploy($record, Auth::user());
                            Notification::make()->title('Crash cart deployed')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Cannot deploy')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('reconcileCrashCart')
                    ->label('Start reconcile')
                    ->color('warning')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->requiresConfirmation()
                    ->modalHeading('Start reconciliation')
                    ->modalDescription('Record used items via Record Usage (Crash cart), then restock and Mark Ready.')
                    ->visible(fn (Store $record): bool => $this->canManageCrashCartStatus($record)
                        && $record->isCrashCart()
                        && $record->crash_cart_status === Store::CRASH_CART_DEPLOYED)
                    ->action(function (Store $record) {
                        try {
                            app(InventoryCrashCartService::class)->startReconcile($record, Auth::user());
                            Notification::make()->title('Crash cart reconciling')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Cannot start reconcile')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('readyCrashCart')
                    ->label('Mark Ready')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->modalHeading('Mark crash cart Ready')
                    ->modalDescription('Enter the new seal number. Used items since deploy become an internal replenishment draft from the parent End Store.')
                    ->form(fn (Store $record): array => [
                        Forms\Components\TextInput::make('seal_number')
                            ->label('New seal number')
                            ->required()
                            ->maxLength(64)
                            ->placeholder('e.g. SEAL-1042'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->maxLength(500),
                        Forms\Components\Placeholder::make('current_seal')
                            ->label('Previous seal')
                            ->content($record->crash_cart_seal_number
                                ? $record->crash_cart_seal_number.($record->crash_cart_sealed_at
                                    ? ' ('.$record->crash_cart_sealed_at->format('d M Y H:i').')'
                                    : '')
                                : 'None'),
                    ])
                    ->visible(fn (Store $record): bool => $this->canManageCrashCartStatus($record)
                        && $record->isCrashCart()
                        && $record->crash_cart_status === Store::CRASH_CART_RECONCILING)
                    ->action(function (Store $record, array $data) {
                        try {
                            $result = app(InventoryCrashCartService::class)->markReady(
                                $record,
                                Auth::user(),
                                (string) ($data['seal_number'] ?? ''),
                                isset($data['notes']) ? trim((string) $data['notes']) : null
                            );

                            $order = $result['order'] ?? null;
                            $body = $order
                                ? 'Seal saved. Replenishment draft '.$order->order_number.' created — open Orders to submit/approve.'
                                : 'Seal saved. No crash-cart usage since deploy, so no replenishment ticket was created.';

                            Notification::make()
                                ->title('Crash cart Ready')
                                ->body($body)
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Cannot mark ready')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            throw $e;
                        }
                    }),
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
                    ->mutateFormDataUsing(fn (array $data) => array_merge($data, [
                        'distribution_type' => Store::DISTRIBUTION_END,
                    ]))
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
            Forms\Components\Toggle::make('is_crash_cart')
                ->label('This is a crash cart')
                ->helperText('Enables Ready → Deployed → Reconciling status. Inbound stock is locked while Deployed.')
                ->visible(fn (Get $get): bool => $this->businessAllowsCrashCart(
                    $this->resolvedBusinessId($get('business_id'))
                ))
                ->default(false)
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    $set('crash_cart_status', $state ? Store::CRASH_CART_READY : null);
                }),
            Forms\Components\Hidden::make('crash_cart_status'),
            Forms\Components\Placeholder::make('type_note')
                ->label('Store type')
                ->content(fn (Get $get): string => $get('is_crash_cart')
                    ? 'Crash cart — sealed emergency stock under an End Store. Not for client sales.'
                    : 'Satellite store — floor stock under an End Store. Not for client sales.'),
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
                Forms\Components\Toggle::make('is_crash_cart')
                    ->label('This is a crash cart')
                    ->helperText('Enables Ready → Deployed → Reconciling status. Inbound stock is locked while Deployed.')
                    ->visible(fn (Get $get): bool => $this->businessAllowsCrashCart(
                        $this->resolvedBusinessId($get('business_id') ?: $record->business_id)
                    ))
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $set('crash_cart_status', $state ? Store::CRASH_CART_READY : null);
                    }),
                Forms\Components\Hidden::make('crash_cart_status'),
                Forms\Components\Placeholder::make('type_note')
                    ->label('Store type')
                    ->content(fn (Get $get): string => $get('is_crash_cart')
                        ? 'Crash cart'
                        : 'Satellite store'),
                Forms\Components\Placeholder::make('crash_status_note')
                    ->label('Cart status')
                    ->content(fn () => $record->crashCartStatusLabel() ?? '—')
                    ->visible(fn (): bool => $record->isCrashCart()),
                Forms\Components\Placeholder::make('crash_seal_note')
                    ->label('Current seal')
                    ->content(fn () => $record->crash_cart_seal_number
                        ? $record->crash_cart_seal_number.($record->crash_cart_sealed_at
                            ? ' · sealed '.$record->crash_cart_sealed_at->format('d M Y H:i')
                            : '')
                        : 'Not sealed yet')
                    ->visible(fn (): bool => $record->isCrashCart()),
                Forms\Components\Placeholder::make('crash_order_note')
                    ->label('Last replenishment')
                    ->content(function () use ($record) {
                        $order = $record->lastCrashCartReplenishmentOrder;
                        if (! $order) {
                            return 'None';
                        }

                        return $order->order_number.' ('.$order->status.')';
                    })
                    ->visible(fn (): bool => $record->isCrashCart()),
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

    protected function canManageCrashCartStatus(Store $record): bool
    {
        if (! $this->businessAllowsCrashCart((int) $record->business_id)) {
            return false;
        }

        $permissions = Auth::user()->permissions ?? [];

        return in_array('Edit Stores', $permissions, true)
            || in_array('Add Stores', $permissions, true)
            || in_array('Record Inventory Usage', $permissions, true);
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
