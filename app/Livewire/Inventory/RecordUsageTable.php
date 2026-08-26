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
                    ->modalDescription('Enter what was used. Pool, stock, and billing are applied automatically.')
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
                                'occurred_at' => now(),
                            ], Auth::user());

                            Notification::make()
                                ->title('Saved')
                                ->body($events->count() > 1
                                    ? $events->count().' lines (pool + floor).'
                                    : null)
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Could not save')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->danger()
                                ->send();

                            throw $e;
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('Could not save')
                                ->body($e->getMessage() ?: 'Something went wrong.')
                                ->danger()
                                ->send();

                            throw ValidationException::withMessages([
                                'context' => $e->getMessage() ?: 'Something went wrong.',
                            ]);
                        }
                    })
                    ->successNotification(null),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime('d M H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('context')
                    ->label('Type')
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
                    ->wrap(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2), '0'), '.')),
                Tables\Columns\TextColumn::make('resolution')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, InventoryUsageEvent $record): string => $record->resolutionLabel())
                    ->color(fn (InventoryUsageEvent $record): string => $record->resolution === InventoryUsageEvent::RESOLUTION_APPROVED_POOL
                        ? 'success'
                        : 'warning'),
                Tables\Columns\TextColumn::make('store.name')
                    ->label(fn (): string => inventory_label('store'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('main_billing_status')
                    ->label('Billing')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'completed' => 'Invoiced',
                        'failed' => 'Failed',
                        'skipped' => '—',
                        default => $state ? ucfirst($state) : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('context')
                    ->label('Type')
                    ->options(InventoryUsageEvent::contextOptions()),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (InventoryUsageEvent $record): string => route('inventory.usage.show', $record)),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Nothing recorded yet')
            ->emptyStateDescription('Use Record usage when something is given or used on the floor.')
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
                ->pluck('name', 'id')
                ->all()
            : [];

        $crashCartStoreOptions = ($floorStockEnabled && $crashCartEnabled)
            ? Store::query()
                ->where('business_id', $businessId)
                ->crashCarts()
                ->orderBy('name')
                ->get(['id', 'name', 'crash_cart_status', 'satellite_role', 'is_crash_cart', 'distribution_type'])
                ->mapWithKeys(fn (Store $store) => [
                    $store->id => $store->name.' ('.$store->crashCartStatusLabel().')',
                ])
                ->all()
            : [];

        $contextOptions = [
            InventoryUsageEvent::CONTEXT_PATIENT => 'Patient',
            InventoryUsageEvent::CONTEXT_ADMINISTRATIVE => 'Admin / ward',
            InventoryUsageEvent::CONTEXT_WASTAGE_OPERATIONAL => 'Broken / spill',
            InventoryUsageEvent::CONTEXT_WASTAGE_EXPIRED => 'Expired',
        ];
        if ($floorStockEnabled && $crashCartEnabled) {
            $contextOptions[InventoryUsageEvent::CONTEXT_CRASH_CART] = 'Crash cart';
        }
        if (! $floorStockEnabled) {
            unset(
                $contextOptions[InventoryUsageEvent::CONTEXT_ADMINISTRATIVE],
                $contextOptions[InventoryUsageEvent::CONTEXT_CRASH_CART],
                $contextOptions[InventoryUsageEvent::CONTEXT_WASTAGE_OPERATIONAL],
                $contextOptions[InventoryUsageEvent::CONTEXT_WASTAGE_EXPIRED],
            );
        }

        $stockContexts = [
            InventoryUsageEvent::CONTEXT_ADMINISTRATIVE,
            InventoryUsageEvent::CONTEXT_CRASH_CART,
            InventoryUsageEvent::CONTEXT_WASTAGE_OPERATIONAL,
            InventoryUsageEvent::CONTEXT_WASTAGE_EXPIRED,
        ];

        return [
            Select::make('context')
                ->label('What kind?')
                ->options($contextOptions)
                ->required()
                ->live()
                ->native(false)
                ->default(InventoryUsageEvent::CONTEXT_PATIENT)
                ->afterStateUpdated(function (callable $set) {
                    $set('store_id', null);
                    $set('item_id', null);
                    $set('quantity', null);
                    $set('admin_purpose', null);
                }),
            Select::make('client_id')
                ->label(fn (): string => inventory_label('client'))
                ->options(function () use ($businessId): array {
                    return Client::query()
                        ->where('business_id', $businessId)
                        ->orderBy('name')
                        ->get(['id', 'name', 'client_id'])
                        ->mapWithKeys(fn (Client $client) => [
                            $client->id => trim(($client->name ?? '').' ['.$client->client_id.']'),
                        ])
                        ->all();
                })
                ->searchable()
                ->required(fn (Get $get): bool => in_array($get('context'), [
                    InventoryUsageEvent::CONTEXT_PATIENT,
                    InventoryUsageEvent::CONTEXT_CRASH_CART,
                ], true))
                ->visible(fn (Get $get): bool => in_array($get('context'), [
                    InventoryUsageEvent::CONTEXT_PATIENT,
                    InventoryUsageEvent::CONTEXT_CRASH_CART,
                ], true))
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('item_id', null)),
            Select::make('store_id')
                ->label(fn (): string => inventory_label('store'))
                ->options(fn (Get $get) => $get('context') === InventoryUsageEvent::CONTEXT_CRASH_CART
                    ? $crashCartStoreOptions
                    : $floorStoreOptions)
                ->searchable()
                ->live()
                ->required(fn (Get $get): bool => in_array($get('context'), $stockContexts, true))
                ->visible(fn (Get $get): bool => $floorStockEnabled && (
                    $get('context') === InventoryUsageEvent::CONTEXT_PATIENT
                    || in_array($get('context'), $stockContexts, true)
                ))
                ->placeholder(fn (Get $get): string => $get('context') === InventoryUsageEvent::CONTEXT_PATIENT
                    ? 'Optional — only if using floor stock'
                    : 'Select store')
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

                                return [$row->item_id => $name.' ('.$qty.')'];
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

                                return [$row->item_id => $name.' (pool '.$qty.')'];
                            })
                            ->all();
                    }

                    return [];
                })
                ->searchable()
                ->required()
                ->live()
                ->disabled(function (Get $get) use ($floorStockEnabled, $stockContexts): bool {
                    if (in_array($get('context'), $stockContexts, true)) {
                        return ! $get('store_id');
                    }

                    return ! $get('client_id') && (! $floorStockEnabled || ! $get('store_id'));
                }),
            TextInput::make('quantity')
                ->label('How many?')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->placeholder('1'),
            Select::make('admin_purpose')
                ->label('Purpose')
                ->options(fn (): array => InventoryModuleConfig::query()
                    ->where('business_id', $businessId)
                    ->first()
                    ?->adminUsagePurposeOptions() ?? InventoryModuleConfig::defaultAdminUsagePurposes())
                ->searchable()
                ->required()
                ->visible(fn (Get $get): bool => $get('context') === InventoryUsageEvent::CONTEXT_ADMINISTRATIVE),
            Textarea::make('notes')
                ->label('Note')
                ->placeholder('Optional')
                ->rows(1)
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
