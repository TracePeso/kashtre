<?php

namespace App\Livewire\ItemUnits;

use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\Models\LegacyUnitMapping;
use App\Domain\Units\Services\InventoryUnitGateway;
use App\Domain\Units\Services\UnitCatalogService;
use App\Models\ItemUnit;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListItemUnits extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $query = ItemUnit::query()->where('business_id', '!=', 1)->latest();

        if (Auth::check() && Auth::user()->business_id !== 1) {
            $query->where('business_id', Auth::user()->business_id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Item Unit')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('engine_mapping')
                    ->label('Engine mapping')
                    ->state(fn (ItemUnit $record): string => $this->mappingLabel($record))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'Mapped') => 'success',
                        $state === 'PENDING' => 'warning',
                        $state === 'UNMATCHED' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Deleted At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                ...(Auth::check() && Auth::user()->business_id === 1 ? [
                    Tables\Filters\SelectFilter::make('business_id')
                        ->label('Filter by Business')
                        ->options(Business::pluck('name', 'id'))
                        ->searchable()
                        ->multiple(),
                ] : []),
            ])
            ->actions([
                Action::make('mapToCoreUnit')
                    ->label(fn (ItemUnit $record): string => str_starts_with($this->mappingLabel($record), 'Mapped')
                        ? 'Remap'
                        : 'Map')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (): bool => (bool) config('units.enabled'))
                    ->modalHeading(fn (ItemUnit $record): string => 'Map "'.$record->name.'" to core unit')
                    ->modalDescription('Link this Item Unit name to a Shared Unit Engine catalog unit so packaging dual-run can resolve it.')
                    ->form(function (ItemUnit $record): array {
                        // Evaluate options when the modal opens. Nested option closures
                        // break Filament/Livewire rehydration (empty select / broken search UI).
                        $options = $this->coreUnitOptionsForBusiness((int) $record->business_id);
                        $default = $this->currentMappedUnitId($record);

                        return [
                            Forms\Components\Select::make('core_unit_id')
                                ->label('Core unit')
                                ->options($options)
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->required()
                                ->default($default)
                                ->helperText(count($options) === 0
                                    ? 'No catalog units found. Run: php artisan units:install'
                                    : 'Search by code, name, or symbol (e.g. BOX, Carton, ea).'),
                        ];
                    })
                    ->action(function (ItemUnit $record, array $data): void {
                        $tenant = app(UnitCatalogService::class)
                            ->tenantKeyForBusiness((int) $record->business_id);
                        $gateway = app(InventoryUnitGateway::class);

                        // Ensure a mapping row exists (PENDING if unmatched).
                        $gateway->mapLegacyName($tenant, $record->name);

                        $mapping = LegacyUnitMapping::query()
                            ->where('tenant_key', $tenant)
                            ->where('source_module', 'INVENTORY')
                            ->where('source_table', 'item_units')
                            ->where('source_value', strtolower(trim($record->name)))
                            ->first();

                        $unit = CoreUnit::query()
                            ->forTenant($tenant)
                            ->whereKey($data['core_unit_id'])
                            ->first();

                        if (! $mapping || ! $unit) {
                            Notification::make()
                                ->title('Could not map unit.')
                                ->body(count($this->coreUnitOptionsForBusiness((int) $record->business_id)) === 0
                                    ? 'Catalog is empty. Run php artisan units:install first.'
                                    : 'Mapping row or core unit was not found.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $gateway->assignMapping($mapping, $unit, Auth::id());

                        // Refresh item sale/order public IDs that use this Item Unit.
                        \App\Models\Item::query()
                            ->where('business_id', $record->business_id)
                            ->where(function ($q) use ($record) {
                                $q->where('uom_id', $record->id)
                                    ->orWhere('order_unit_id', $record->id);
                            })
                            ->orderBy('id')
                            ->chunkById(100, function ($items) use ($gateway) {
                                foreach ($items as $item) {
                                    $gateway->ensureItemUnitLinks($item);
                                }
                            });

                        Notification::make()
                            ->title('Mapped to '.$unit->code.' ('.$unit->symbol.').')
                            ->success()
                            ->send();
                    }),

                EditAction::make()
                    ->modalHeading('Edit Item Unit')
                    ->form(fn (ItemUnit $record) => [
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::pluck('name', 'id'))
                            ->required()
                            ->disabled(fn () => Auth::user()->business_id !== 1),

                        TextInput::make('name')
                            ->label('Item Unit Name')
                            ->placeholder('Enter item unit name')
                            ->required()
                            ->rule(function (Forms\Get $get, ?ItemUnit $record) {
                                return function (string $attribute, $value, $fail) use ($get, $record) {
                                    $businessId = $get('business_id') ?: $record?->business_id;
                                    if (! $businessId || trim((string) $value) === '') {
                                        return;
                                    }

                                    $exists = ItemUnit::query()
                                        ->where('business_id', $businessId)
                                        ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $value))])
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->exists();

                                    if ($exists) {
                                        $fail('This business already has an item unit with that name. Reuse it on items instead.');
                                    }
                                };
                            }),

                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Enter item unit description')
                            ->nullable(),
                    ])
                    ->successNotificationTitle('Item Unit updated successfully.')
                    ->after(function (ItemUnit $record) {
                        if (config('units.enabled')) {
                            $tenant = app(UnitCatalogService::class)
                                ->tenantKeyForBusiness((int) $record->business_id);
                            app(InventoryUnitGateway::class)
                                ->mapLegacyName($tenant, $record->name);
                        }
                    }),

                DeleteAction::make()
                    ->modalHeading('Delete Item Unit')
                    ->successNotificationTitle('Item Unit deleted (soft) successfully.'),
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
                    ->label('Create Item Unit')
                    ->modalHeading('Add New Item Unit')
                    ->form([
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::pluck('name', 'id'))
                            ->required()
                            ->default(Auth::user()->business_id)
                            ->disabled(fn () => Auth::user()->business_id !== 1)
                            ->live(),

                        TextInput::make('name')
                            ->label('Item Unit Name')
                            ->placeholder('Enter item unit name')
                            ->required()
                            ->rule(function (Forms\Get $get) {
                                return function (string $attribute, $value, $fail) use ($get) {
                                    $businessId = $get('business_id');
                                    if (! $businessId || trim((string) $value) === '') {
                                        return;
                                    }

                                    $exists = ItemUnit::query()
                                        ->where('business_id', $businessId)
                                        ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $value))])
                                        ->exists();

                                    if ($exists) {
                                        $fail('This business already has an item unit with that name. Reuse it on items instead.');
                                    }
                                };
                            }),

                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Enter item unit description')
                            ->nullable(),
                    ])
                    ->createAnother(false)
                    ->after(function (ItemUnit $record) {
                        if (config('units.enabled')) {
                            $tenant = app(UnitCatalogService::class)
                                ->tenantKeyForBusiness((int) $record->business_id);
                            app(InventoryUnitGateway::class)
                                ->mapLegacyName($tenant, $record->name);
                        }

                        Notification::make()
                            ->title('Item Unit created successfully.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected function mappingLabel(ItemUnit $record): string
    {
        if (! config('units.enabled')) {
            return '—';
        }

        $map = $this->mappingFor($record);

        if (! $map) {
            return 'Not linked';
        }

        if ($map->status === 'MAPPED' && $map->unit) {
            return 'Mapped · '.$map->unit->code;
        }

        return (string) $map->status;
    }

    protected function mappingFor(ItemUnit $record): ?LegacyUnitMapping
    {
        $tenant = app(UnitCatalogService::class)
            ->tenantKeyForBusiness((int) $record->business_id);

        return LegacyUnitMapping::query()
            ->with('unit')
            ->where('tenant_key', $tenant)
            ->where('source_module', 'INVENTORY')
            ->where('source_table', 'item_units')
            ->where('source_value', strtolower(trim($record->name)))
            ->first();
    }

    protected function currentMappedUnitId(ItemUnit $record): ?int
    {
        $map = $this->mappingFor($record);

        return $map?->status === 'MAPPED' ? $map->unit_id : null;
    }

    /**
     * @return array<int, string>
     */
    protected function coreUnitOptionsForBusiness(int $businessId): array
    {
        $tenant = app(UnitCatalogService::class)->tenantKeyForBusiness($businessId);

        return CoreUnit::query()
            ->forTenant($tenant)
            ->active()
            ->orderBy('canonical_name')
            ->get(['id', 'code', 'canonical_name', 'symbol'])
            ->mapWithKeys(fn (CoreUnit $u) => [
                (string) $u->id => trim($u->code.' — '.$u->canonical_name.' ('.$u->symbol.')'),
            ])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.item-units.list-item-units');
    }
}
