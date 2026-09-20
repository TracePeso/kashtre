<?php

namespace App\Livewire\ItemUnits;

use App\Domain\Units\Models\CoreUnit;
use App\Domain\Units\Models\LegacyUnitMapping;
use App\Domain\Units\Services\InventoryUnitGateway;
use App\Domain\Units\Services\UnitCatalogService;
use App\Models\Business;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Support\SharedUnits;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Validation\ValidationException;
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
                    ->label('Unit')
                    ->formatStateUsing(fn (ItemUnit $record): string => SharedUnits::itemUnitLabel($record))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->state(fn (ItemUnit $record): string => SharedUnits::sourceLabel($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Shared catalog' => 'success',
                        'Local' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable(),

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
                    ->label('Link to catalog')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (ItemUnit $record): bool => SharedUnits::enabled()
                        && SharedUnits::sourceLabel($record) === 'Custom name')
                    ->modalHeading(fn (ItemUnit $record): string => 'Link "'.$record->name.'" to a catalog unit')
                    ->modalDescription('Use this only for leftover custom names. Prefer picking a catalog unit when adding a new one.')
                    ->form(function (ItemUnit $record): array {
                        $options = $this->coreUnitOptionsForBusiness((int) $record->business_id);
                        $default = $this->currentMappedUnitId($record);

                        return [
                            Forms\Components\Select::make('core_unit_id')
                                ->label('Catalog unit')
                                ->options($options)
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->required()
                                ->default($default)
                                ->helperText(count($options) === 0
                                    ? 'No catalog units found. Ask Kashtre admin to add units under Settings → Units.'
                                    : 'Search by code, name, or symbol (e.g. BOX, tablet, ea).'),
                        ];
                    })
                    ->action(function (ItemUnit $record, array $data): void {
                        $tenant = app(UnitCatalogService::class)
                            ->tenantKeyForBusiness((int) $record->business_id);
                        $gateway = app(InventoryUnitGateway::class);
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
                                ->title('Could not link unit.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $gateway->assignMapping($mapping, $unit, Auth::id());

                        Item::query()
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
                            ->title('Linked to '.$unit->code.' ('.$unit->symbol.').')
                            ->success()
                            ->send();
                    }),

                EditAction::make()
                    ->modalHeading('Edit unit')
                    ->form(fn (ItemUnit $record) => [
                        TextInput::make('name')
                            ->label('Unit name')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Names are not renamed after use. Retire this unit and add a new one instead.'),
                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Optional note')
                            ->nullable(),
                    ])
                    ->successNotificationTitle('Unit updated.'),

                DeleteAction::make()
                    ->modalHeading('Remove unit')
                    ->successNotificationTitle('Unit removed.')
                    ->before(function (DeleteAction $action, ItemUnit $record): void {
                        $inUse = Item::query()
                            ->where('business_id', $record->business_id)
                            ->where(function ($q) use ($record) {
                                $q->where('uom_id', $record->id)
                                    ->orWhere('order_unit_id', $record->id);
                            })
                            ->exists();

                        if ($inUse) {
                            Notification::make()
                                ->title('This unit is used on items. Change those items first.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
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
                    ->label('Add unit')
                    ->modalHeading('Add a unit for this business')
                    ->createAnother(false)
                    ->form([
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->placeholder('Select a business')
                            ->options(Business::where('id', '!=', 1)->pluck('name', 'id'))
                            ->required()
                            ->default(Auth::user()->business_id)
                            ->disabled(fn () => Auth::user()->business_id !== 1)
                            ->dehydrated()
                            ->live(),

                        Forms\Components\Radio::make('source')
                            ->label('How to add')
                            ->options([
                                'catalog' => 'Pick from shared catalog',
                                'local' => 'Add a local packaging unit',
                            ])
                            ->default('catalog')
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('core_unit_public_id')
                            ->label('Catalog unit')
                            ->options(fn (Forms\Get $get) => SharedUnits::unusedCatalogOptions(
                                (int) ($get('business_id') ?: Auth::user()->business_id)
                            ))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn (Forms\Get $get) => $get('source') === 'catalog')
                            ->required(fn (Forms\Get $get) => $get('source') === 'catalog')
                            ->helperText('Tablet, box, mg, mL, and other shared units. Kashtre admin adds new ones under Settings → Units.'),

                        TextInput::make('name')
                            ->label('Local unit name')
                            ->placeholder('e.g. Hospital pack')
                            ->visible(fn (Forms\Get $get) => $get('source') === 'local')
                            ->required(fn (Forms\Get $get) => $get('source') === 'local'),

                        TextInput::make('symbol')
                            ->label('Symbol')
                            ->placeholder('e.g. pack')
                            ->visible(fn (Forms\Get $get) => $get('source') === 'local'),

                        TextInput::make('code')
                            ->label('Code')
                            ->placeholder('Optional, e.g. HOSP_PACK')
                            ->visible(fn (Forms\Get $get) => $get('source') === 'local'),

                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Optional note')
                            ->visible(fn (Forms\Get $get) => $get('source') === 'local')
                            ->nullable(),
                    ])
                    ->using(function (array $data): ItemUnit {
                        $businessId = (int) ($data['business_id'] ?? Auth::user()->business_id);
                        if ($businessId < 2) {
                            throw ValidationException::withMessages([
                                'business_id' => 'Choose a hospital business.',
                            ]);
                        }

                        if (($data['source'] ?? 'catalog') === 'local') {
                            return SharedUnits::addLocalPackagingUnit(
                                $businessId,
                                (string) ($data['name'] ?? ''),
                                (string) ($data['symbol'] ?? $data['name'] ?? ''),
                                $data['code'] ?? null,
                                $data['description'] ?? null,
                            );
                        }

                        $core = CoreUnit::query()
                            ->forTenant((string) $businessId)
                            ->where('public_id', $data['core_unit_public_id'] ?? '')
                            ->first();

                        if (! $core) {
                            throw ValidationException::withMessages([
                                'core_unit_public_id' => 'Choose a catalog unit.',
                            ]);
                        }

                        return SharedUnits::adoptCatalogUnit($businessId, $core);
                    })
                    ->successNotificationTitle('Unit added. It is available on item sale and order unit pickers.'),
            ]);
    }

    protected function currentMappedUnitId(ItemUnit $record): ?int
    {
        $map = $this->mappingFor($record);

        return $map?->status === 'MAPPED' ? $map->unit_id : null;
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

    /**
     * @return array<int, string>
     */
    protected function coreUnitOptionsForBusiness(int $businessId): array
    {
        return SharedUnits::inventoryCatalogUnits($businessId)
            ->mapWithKeys(fn (CoreUnit $u) => [
                (string) $u->id => SharedUnits::catalogLabel($u),
            ])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.item-units.list-item-units');
    }
}
