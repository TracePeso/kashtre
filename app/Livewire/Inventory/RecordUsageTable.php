<?php

namespace App\Livewire\Inventory;

use App\Models\Client;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryUsageEvent;
use App\Models\Item;
use App\Models\PatientApprovedPoolLine;
use App\Models\Store;
use App\Services\Inventory\InventoryRecordUsageService;
use App\Support\InventoryBusinessContext;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class RecordUsageTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        $query = InventoryUsageEvent::query()
            ->with([
                'client:id,name',
                'item:id,name,strength',
                'store:id,name',
                'recordedBy:id,name',
                'invoice:id,invoice_number',
            ])
            ->where('business_id', $businessId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        return $table
            ->query($query)
            ->defaultPaginationPageOption(25)
            ->headerActions([
                CreateAction::make()
                    ->label('Record usage')
                    ->modalHeading('Record usage')
                    ->modalSubmitActionLabel('Save')
                    ->createAnother(false)
                    ->form($this->usageForm($businessId))
                    ->action(function (array $data) use ($businessId): void {
                        try {
                            $events = app(InventoryRecordUsageService::class)->record([
                                'business_id' => $businessId,
                                'context' => $data['context'],
                                'client_id' => $data['client_id'] ?? null,
                                'item_id' => (int) $data['item_id'],
                                'store_id' => isset($data['store_id']) ? (int) $data['store_id'] : null,
                                'quantity' => $data['quantity'],
                                'notes' => $this->composeUsageNotes($data),
                                'occurred_at' => $data['occurred_at'] ?? now(),
                            ], Auth::user());

                            Notification::make()
                                ->title('Usage recorded')
                                ->body($events->count() > 1
                                    ? $events->count().' entries created (Approved Pool + floor stock).'
                                    : null)
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Cannot record usage')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->danger()
                                ->send();

                            throw $e;
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('Cannot record usage')
                                ->body($e->getMessage() ?: 'Something went wrong while saving.')
                                ->danger()
                                ->persistent()
                                ->send();

                            throw ValidationException::withMessages([
                                'context' => $e->getMessage() ?: 'Something went wrong while saving.',
                            ]);
                        }
                    })
                    ->successNotification(null),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('context')
                    ->label('Context')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, InventoryUsageEvent $record): string => $record->contextLabel())
                    ->color(fn (InventoryUsageEvent $record): string => match ($record->context) {
                        InventoryUsageEvent::CONTEXT_PATIENT => 'info',
                        InventoryUsageEvent::CONTEXT_CRASH_CART => 'danger',
                        InventoryUsageEvent::CONTEXT_WASTAGE_OPERATIONAL,
                        InventoryUsageEvent::CONTEXT_WASTAGE_EXPIRED => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('client.name')
                    ->label(fn (): string => inventory_label('client'))
                    ->placeholder('—')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('item.name')
                    ->label(fn (): string => inventory_label('item'))
                    ->searchable()
                    ->wrap()
                    ->description(fn (InventoryUsageEvent $record): ?string => $record->item?->strength
                        ? 'Strength: '.$record->item->strength
                        : null),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2), '0'), '.')),
                Tables\Columns\TextColumn::make('resolution')
                    ->label('Resolved via')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, InventoryUsageEvent $record): string => $record->resolutionLabel())
                    ->color(fn (InventoryUsageEvent $record): string => $record->resolution === InventoryUsageEvent::RESOLUTION_APPROVED_POOL
                        ? 'success'
                        : 'warning'),
                Tables\Columns\IconColumn::make('billed_main_module')
                    ->label('Bill')
                    ->boolean()
                    ->trueIcon('heroicon-o-banknotes')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('main_billing_status')
                    ->label('Billing')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'completed' => 'Invoiced',
                        'failed' => 'Failed',
                        'skipped' => 'Skipped',
                        default => $state ? ucfirst($state) : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'skipped' => 'gray',
                        default => 'gray',
                    })
                    ->description(fn (InventoryUsageEvent $record): ?string => $record->invoice?->invoice_number)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label(fn (): string => inventory_label('store'))
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('context')
                    ->options(InventoryUsageEvent::contextOptions()),
                Tables\Filters\SelectFilter::make('resolution')
                    ->label('Resolved via')
                    ->options(InventoryUsageEvent::resolutionOptions()),
                Tables\Filters\TernaryFilter::make('billed_main_module')
                    ->label('Bill Main Module')
                    ->boolean(),
                Tables\Filters\SelectFilter::make('client_id')
                    ->label(fn (): string => inventory_label('client'))
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (InventoryUsageEvent $record): string => route('inventory.usage.show', $record)),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No usage recorded yet')
            ->emptyStateDescription(fn (): string => 'Record '.strtolower(inventory_label('client')).' usage (Approved Pool first, then floor stock) or administrative usage from '.strtolower(inventory_label('store')).' stock.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function usageForm(int $businessId): array
    {
        $floorStockEnabled = InventoryBusinessContext::floorStockEnabled();
        $crashCartEnabled = InventoryBusinessContext::crashCartEnabled();

        $floorStoreOptions = $floorStockEnabled
            ? Store::query()
                ->where('business_id', $businessId)
                ->whereIn('distribution_type', [
                    Store::DISTRIBUTION_END,
                    Store::DISTRIBUTION_SATELLITE,
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'distribution_type', 'is_crash_cart'])
                ->mapWithKeys(fn (Store $store) => [
                    $store->id => $store->name.(
                        $store->isCrashCart()
                            ? ' (Crash cart)'
                            : ($store->isSatelliteStore() ? ' (Satellite)' : ' (End Store)')
                    ),
                ])
                ->all()
            : [];

        $crashCartStoreOptions = ($floorStockEnabled && $crashCartEnabled)
            ? Store::query()
                ->where('business_id', $businessId)
                ->where('distribution_type', Store::DISTRIBUTION_SATELLITE)
                ->where('is_crash_cart', true)
                ->orderBy('name')
                ->get(['id', 'name', 'crash_cart_status'])
                ->mapWithKeys(fn (Store $store) => [
                    $store->id => $store->name.' — '.($store->crashCartStatusLabel() ?? 'Ready'),
                ])
                ->all()
            : [];

        $contextOptions = InventoryUsageEvent::contextOptions();
        if (! $floorStockEnabled) {
            unset(
                $contextOptions[InventoryUsageEvent::CONTEXT_ADMINISTRATIVE],
                $contextOptions[InventoryUsageEvent::CONTEXT_CRASH_CART],
                $contextOptions[InventoryUsageEvent::CONTEXT_WASTAGE_OPERATIONAL],
                $contextOptions[InventoryUsageEvent::CONTEXT_WASTAGE_EXPIRED],
            );
        }
        if (! $crashCartEnabled) {
            unset($contextOptions[InventoryUsageEvent::CONTEXT_CRASH_CART]);
        }

        $stockContexts = [
            InventoryUsageEvent::CONTEXT_ADMINISTRATIVE,
            InventoryUsageEvent::CONTEXT_CRASH_CART,
            InventoryUsageEvent::CONTEXT_WASTAGE_OPERATIONAL,
            InventoryUsageEvent::CONTEXT_WASTAGE_EXPIRED,
        ];

        return [
            Select::make('context')
                ->label('Context')
                ->options($contextOptions)
                ->placeholder('Select usage context')
                ->required()
                ->live()
                ->default(InventoryUsageEvent::CONTEXT_PATIENT)
                ->afterStateUpdated(function (callable $set) {
                    $set('store_id', null);
                    $set('item_id', null);
                    $set('quantity', null);
                }),
            Select::make('client_id')
                ->label(fn (): string => inventory_label('client'))
                ->options(function () use ($businessId): array {
                    $clients = Client::query()
                        ->where('business_id', $businessId)
                        ->orderBy('name')
                        ->get(['id', 'name', 'client_id']);

                    return $clients->mapWithKeys(function (Client $client) {
                        $name = trim((string) ($client->name ?? ''));
                        $clientCode = (string) ($client->client_id ?? '');

                        return [
                            $client->id => $name !== ''
                                ? sprintf('%s [%s]', $name, $clientCode)
                                : sprintf('Client [%s]', $clientCode),
                        ];
                    })->all();
                })
                ->searchable()
                ->placeholder('Search by name or client ID')
                ->required(fn (Get $get): bool => in_array($get('context'), [
                    InventoryUsageEvent::CONTEXT_PATIENT,
                    InventoryUsageEvent::CONTEXT_CRASH_CART,
                ], true))
                ->visible(fn (Get $get): bool => in_array($get('context'), [
                    InventoryUsageEvent::CONTEXT_PATIENT,
                    InventoryUsageEvent::CONTEXT_CRASH_CART,
                ], true))
                ->helperText(fn (Get $get): ?string => $get('context') === InventoryUsageEvent::CONTEXT_CRASH_CART
                    ? 'Required so Main Module can create the postpaid billing packet.'
                    : null)
                ->live()
                ->afterStateUpdated(function (callable $set) {
                    $set('item_id', null);
                }),
            Select::make('store_id')
                ->label(fn (): string => inventory_label('store'))
                ->options(fn (Get $get) => $get('context') === InventoryUsageEvent::CONTEXT_CRASH_CART
                    ? $crashCartStoreOptions
                    : $floorStoreOptions)
                ->searchable()
                ->placeholder('Select '.strtolower(inventory_label('store')))
                ->live()
                ->helperText(function (Get $get): string {
                    return match ($get('context')) {
                        InventoryUsageEvent::CONTEXT_PATIENT => 'Select a '.strtolower(inventory_label('store')).' to use floor stock. Leave empty to use Approved Pool only.',
                        InventoryUsageEvent::CONTEXT_CRASH_CART => 'Only Reconciling crash carts. Deploy → emergency (no docs) → Start reconcile → record usage here.',
                        InventoryUsageEvent::CONTEXT_WASTAGE_EXPIRED => 'Expired wastage reduces stock but is excluded from reorder averages.',
                        default => 'Only items with stock at this '.strtolower(inventory_label('store')).' will be listed.',
                    };
                })
                ->required(fn (Get $get): bool => in_array($get('context'), $stockContexts, true))
                ->visible(fn (Get $get): bool => $floorStockEnabled && (
                    $get('context') === InventoryUsageEvent::CONTEXT_PATIENT
                    || in_array($get('context'), $stockContexts, true)
                ))
                ->afterStateUpdated(function (callable $set) {
                    $set('item_id', null);
                    $set('quantity', null);
                }),
            Select::make('item_id')
                ->label(fn (): string => inventory_label('item'))
                ->options(function (Get $get) use ($businessId, $floorStockEnabled) {
                    $storeId = $floorStockEnabled && $get('store_id') ? (int) $get('store_id') : null;

                    if ($storeId) {
                        $stockRows = InventoryStockLevel::query()
                            ->where('business_id', $businessId)
                            ->where('store_id', $storeId)
                            ->where('quantity_suom', '>', 0)
                            ->orderByDesc('quantity_suom')
                            ->get(['item_id', 'quantity_suom']);

                        if ($stockRows->isEmpty()) {
                            return [];
                        }

                        $names = Item::query()
                            ->whereIn('id', $stockRows->pluck('item_id'))
                            ->pluck('name', 'id');

                        return $stockRows
                            ->mapWithKeys(function ($row) use ($names) {
                                $name = $names[$row->item_id] ?? ('Item #'.$row->item_id);
                                $qty = rtrim(rtrim(number_format((float) $row->quantity_suom, 2), '0'), '.');

                                return [$row->item_id => $name.' — '.$qty.' on hand'];
                            })
                            ->all();
                    }

                    if ($get('context') === InventoryUsageEvent::CONTEXT_PATIENT && $get('client_id')) {
                        $poolRows = PatientApprovedPoolLine::query()
                            ->where('business_id', $businessId)
                            ->where('client_id', (int) $get('client_id'))
                            ->where('quantity_remaining', '>', 0)
                            ->selectRaw('item_id, SUM(quantity_remaining) as available')
                            ->groupBy('item_id')
                            ->get();

                        if ($poolRows->isEmpty()) {
                            return [];
                        }

                        $names = Item::query()
                            ->whereIn('id', $poolRows->pluck('item_id'))
                            ->pluck('name', 'id');

                        return $poolRows
                            ->mapWithKeys(function ($row) use ($names) {
                                $name = $names[$row->item_id] ?? ('Item #'.$row->item_id);
                                $qty = rtrim(rtrim(number_format((float) $row->available, 2), '0'), '.');

                                return [$row->item_id => $name.' — '.$qty.' in pool'];
                            })
                            ->all();
                    }

                    return [];
                })
                ->searchable()
                ->placeholder('Select '.strtolower(inventory_label('item')))
                ->required()
                ->live()
                ->disabled(function (Get $get) use ($floorStockEnabled, $stockContexts): bool {
                    if (in_array($get('context'), $stockContexts, true)) {
                        return ! $get('store_id');
                    }

                    return ! $get('client_id') && (! $floorStockEnabled || ! $get('store_id'));
                })
                ->helperText(function (Get $get) use ($floorStockEnabled, $stockContexts): ?string {
                    if (in_array($get('context'), $stockContexts, true) && ! $get('store_id')) {
                        return 'Select a store first to see available items.';
                    }

                    if ($get('context') === InventoryUsageEvent::CONTEXT_PATIENT && ! $get('client_id') && (! $floorStockEnabled || ! $get('store_id'))) {
                        return $floorStockEnabled
                            ? 'Select a client (pool) and/or a store (floor stock) first.'
                            : 'Select a client to use Approved Pool balance.';
                    }

                    if ($floorStockEnabled && $get('store_id') && $get('item_id') === null) {
                        return 'Showing items with stock at the selected store only.';
                    }

                    return null;
                }),
            TextInput::make('quantity')
                ->label('Quantity')
                ->numeric()
                ->placeholder('e.g. 1')
                ->required()
                ->minValue(0.01)
                ->live()
                ->helperText(function (Get $get) use ($businessId, $floorStockEnabled): ?string {
                    if (! $get('item_id')) {
                        return null;
                    }

                    $parts = [];

                    if ($get('context') === InventoryUsageEvent::CONTEXT_PATIENT && $get('client_id')) {
                        $pool = (float) PatientApprovedPoolLine::query()
                            ->where('business_id', $businessId)
                            ->where('client_id', (int) $get('client_id'))
                            ->where('item_id', (int) $get('item_id'))
                            ->sum('quantity_remaining');
                        $parts[] = 'Pool: '.rtrim(rtrim(number_format($pool, 2), '0'), '.');
                    }

                    if ($floorStockEnabled && $get('store_id')) {
                        $onHand = (float) (InventoryStockLevel::query()
                            ->where('business_id', $businessId)
                            ->where('store_id', (int) $get('store_id'))
                            ->where('item_id', (int) $get('item_id'))
                            ->value('quantity_suom') ?? 0);
                        $parts[] = 'On hand: '.rtrim(rtrim(number_format($onHand, 2), '0'), '.');
                    }

                    return $parts !== [] ? implode(' · ', $parts) : null;
                }),
            DateTimePicker::make('occurred_at')
                ->label('When')
                ->placeholder('Select date and time')
                ->default(now())
                ->seconds(false)
                ->required(),
            Select::make('admin_purpose')
                ->label('Administrative purpose')
                ->options(fn (): array => InventoryModuleConfig::query()
                    ->where('business_id', $businessId)
                    ->first()
                    ?->adminUsagePurposeOptions() ?? InventoryModuleConfig::defaultAdminUsagePurposes())
                ->searchable()
                ->placeholder('Select purpose (e.g. Cleaning)')
                ->required()
                ->visible(fn (Get $get): bool => $get('context') === InventoryUsageEvent::CONTEXT_ADMINISTRATIVE),
            Textarea::make('notes')
                ->label('Notes')
                ->placeholder('Optional — e.g. bedside administration, spill, expired batch removed')
                ->rows(2)
                ->maxLength(500),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function composeUsageNotes(array $data): ?string
    {
        $parts = [];
        if (! blank($data['admin_purpose'] ?? null)) {
            $parts[] = 'Purpose: '.$data['admin_purpose'];
        }
        if (! blank($data['notes'] ?? null)) {
            $parts[] = (string) $data['notes'];
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    public function render(): View
    {
        return view('livewire.inventory.record-usage-table');
    }
}
