<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryModuleConfig;
use App\Models\Store;
use App\Services\Inventory\InventoryFulfillmentDispenseService;
use App\Services\Inventory\InventoryFulfillmentStageService;
use App\Services\Inventory\InventoryHandoffTokenService;
use App\Services\ClinicalModuleIntegrationService;
use App\Support\InventoryBusinessContext;
use App\Support\InventoryFulfillmentStrategy;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class EndStoreFulfillmentQueue extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    /** @var string all|outpatient|inpatient|stat */
    public string $consoleTab = 'all';

    public ?string $selectedStoreId = null;

    /** @var string|null Banner after staging — Clinical owns the nurse code. */
    public ?string $lastHandoffRef = null;

    public ?string $lastHandoffBasket = null;

    public function clearLastHandoffBanner(): void
    {
        $this->lastHandoffRef = null;
        $this->lastHandoffBasket = null;
    }

    public function setConsoleTab(string $tab): void
    {
        if (! in_array($tab, ['all', 'outpatient', 'inpatient', 'stat'], true)) {
            return;
        }

        $this->consoleTab = $tab;
        $this->resetTable();
    }

    public function updatedSelectedStoreId(): void
    {
        $this->resetTable();
    }

    /**
     * @return array<int|string, string>
     */
    public function endStoreOptions(): array
    {
        return Store::query()
            ->where('business_id', InventoryBusinessContext::effectiveBusinessId())
            ->where('distribution_type', Store::DISTRIBUTION_END)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function openCount(): int
    {
        return $this->baseCountQuery()->whereIn('status', $this->openStatuses())->count();
    }

    public function outpatientOpenCount(): int
    {
        return $this->baseCountQuery()
            ->where('fulfillment_strategy', InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE)
            ->whereIn('status', $this->openStatuses())
            ->count();
    }

    public function inpatientOpenCount(): int
    {
        return $this->baseCountQuery()
            ->where('fulfillment_strategy', InventoryFulfillmentStrategy::BATCH_AND_STAGE)
            ->whereIn('status', $this->openStatuses())
            ->count();
    }

    public function statOpenCount(): int
    {
        return $this->baseCountQuery()
            ->where('priority', InventoryFulfillmentLine::PRIORITY_STAT)
            ->whereIn('status', $this->openStatuses())
            ->count();
    }

    public function unacknowledgedStatCount(): int
    {
        return $this->baseCountQuery()
            ->where('priority', InventoryFulfillmentLine::PRIORITY_STAT)
            ->whereIn('status', $this->openStatuses())
            ->whereNull('acknowledged_at')
            ->count();
    }

    public function table(Table $table): Table
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        $query = InventoryFulfillmentLine::query()
            ->with([
                'client:id,name',
                'item:id,strength,code',
                'invoice:id,invoice_number',
                'store:id,name',
            ])
            ->where('business_id', $businessId)
            ->orderByRaw("CASE WHEN status IN ('pending','picking','staged','partial') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN priority = 'stat' THEN 0 WHEN priority = 'urgent' THEN 1 ELSE 2 END")
            ->orderByRaw('CASE WHEN priority = \'stat\' AND acknowledged_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('queued_at');

        $this->applyConsoleScope($query);

        return $table
            ->query($query)
            ->defaultPaginationPageOption(25)
            ->recordClasses(fn (InventoryFulfillmentLine $record): ?string => $record->isStat() && $record->acknowledged_at === null
                ? 'fi-ta-row-stat-unacked'
                : null)
            ->columns([
                Tables\Columns\TextColumn::make('queued_at')
                    ->label('Queued')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->description(fn (InventoryFulfillmentLine $record): ?string => $record->isStat() && $record->acknowledged_at === null
                        ? 'Awaiting STAT ack'
                        : null),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('End Store')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: (bool) $this->selectedStoreId),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, InventoryFulfillmentLine $record): string => $record->statusLabel())
                    ->color(fn (InventoryFulfillmentLine $record): string => match ($record->status) {
                        InventoryFulfillmentLine::STATUS_COMPLETED => 'success',
                        InventoryFulfillmentLine::STATUS_STAGED => 'info',
                        InventoryFulfillmentLine::STATUS_PICKING => 'warning',
                        InventoryFulfillmentLine::STATUS_PARTIAL => 'warning',
                        InventoryFulfillmentLine::STATUS_CANCELLED => 'gray',
                        default => 'primary',
                    }),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, InventoryFulfillmentLine $record): string => $record->priorityLabel())
                    ->color(fn (InventoryFulfillmentLine $record): string => match ($record->priority) {
                        InventoryFulfillmentLine::PRIORITY_STAT => 'danger',
                        InventoryFulfillmentLine::PRIORITY_URGENT => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Item')
                    ->searchable()
                    ->wrap()
                    ->description(fn (InventoryFulfillmentLine $record): ?string => collect([
                        $record->item?->strength ? 'Strength: '.$record->item->strength : null,
                        $record->isInpatient() && $record->basket_key
                            ? 'Basket: '.$record->basket_key
                            : null,
                    ])->filter()->implode(' · ') ?: null),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, InventoryFulfillmentLine $record): string => rtrim(rtrim(number_format((float) $state, 2), '0'), '.')
                        .((float) $record->quantity_fulfilled > 0
                            ? ' / '.rtrim(rtrim(number_format((float) $record->quantity_fulfilled, 2), '0'), '.').' done'
                            : '')),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->placeholder('—')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('fulfillment_strategy')
                    ->label('Path')
                    ->formatStateUsing(fn (?string $state, InventoryFulfillmentLine $record): string => match ($record->fulfillment_strategy) {
                        InventoryFulfillmentStrategy::BATCH_AND_STAGE => 'Inpatient',
                        InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE => 'Outpatient',
                        default => $record->strategyLabel(),
                    })
                    ->badge()
                    ->color(fn (InventoryFulfillmentLine $record): string => $record->fulfillment_strategy === InventoryFulfillmentStrategy::BATCH_AND_STAGE
                        ? 'warning'
                        : 'info')
                    ->toggleable(isToggledHiddenByDefault: in_array($this->consoleTab, ['outpatient', 'inpatient'], true)),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InventoryFulfillmentLine::statusOptions())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('priority')
                    ->options(InventoryFulfillmentLine::priorityOptions())
                    ->multiple()
                    ->visible(fn (): bool => $this->consoleTab !== 'stat'),
                Tables\Filters\SelectFilter::make('fulfillment_strategy')
                    ->label('Path')
                    ->options([
                        InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE => 'Outpatient',
                        InventoryFulfillmentStrategy::BATCH_AND_STAGE => 'Inpatient',
                    ])
                    ->visible(fn (): bool => $this->consoleTab === 'all' || $this->consoleTab === 'stat'),
                Tables\Filters\TernaryFilter::make('acknowledged_at')
                    ->label('STAT acknowledged')
                    ->nullable()
                    ->placeholder('All STAT')
                    ->trueLabel('Acknowledged')
                    ->falseLabel('Unacknowledged')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('acknowledged_at'),
                        false: fn (Builder $q) => $q->whereNull('acknowledged_at'),
                        blank: fn (Builder $q) => $q,
                    )
                    ->visible(fn (): bool => $this->consoleTab === 'stat'),
            ])
            ->actions([
                Action::make('acknowledgeStat')
                    ->label('Ack STAT')
                    ->button()
                    ->size('sm')
                    ->icon('heroicon-o-bell-alert')
                    ->color('danger')
                    ->visible(fn (InventoryFulfillmentLine $record) => $record->isStat()
                        && $record->isOpen()
                        && $record->acknowledged_at === null)
                    ->action(function (InventoryFulfillmentLine $record) {
                        $record->update([
                            'acknowledged_at' => now(),
                            'acknowledged_by' => Auth::id(),
                        ]);
                        Notification::make()->title('STAT acknowledged')->success()->send();
                    })
                    ->after(fn () => $this->resetTable()),

                Action::make('completeDispense')
                    ->label('Dispense')
                    ->button()
                    ->size('sm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form(function (InventoryFulfillmentLine $record): array {
                        $config = InventoryModuleConfig::query()
                            ->where('business_id', $record->business_id)
                            ->first();

                        $fields = [];

                        if ($config?->batchLotTrackingEnabled()) {
                            $fields[] = TextInput::make('batch_lot')
                                ->label('Batch / lot')
                                ->placeholder('Enter batch or lot number')
                                ->required()
                                ->maxLength(100);
                        }

                        if ($config?->serialNumberTrackingEnabled()) {
                            $fields[] = TagsInput::make('serials')
                                ->label('Serial numbers')
                                ->required()
                                ->placeholder('Type a serial and press Enter')
                                ->helperText('Enter one serial per unit dispensed.');
                        }

                        return $fields;
                    })
                    ->modalHeading('Complete dispense')
                    ->modalDescription(fn (InventoryFulfillmentLine $record): string => sprintf(
                        $record->supportsApprovedPool()
                            ? 'Dispense %s × %s from %s? Stock will leave this End Store, the Approved Pool will be updated, and the Main Module ticket will close.'
                            : 'Dispense %s × %s from %s? Stock will leave this End Store and the Main Module ticket will be marked complete (Approved Pool disabled for this End Store).',
                        rtrim(rtrim(number_format((float) $record->quantity - (float) $record->quantity_fulfilled, 2), '0'), '.'),
                        $record->item_name,
                        $record->store?->name ?? 'this End Store'
                    ))
                    ->modalSubmitActionLabel('Confirm dispense')
                    ->visible(fn (InventoryFulfillmentLine $record) => $record->isOutpatient() && $record->isOpen() && ! $record->isStaged())
                    ->action(function (InventoryFulfillmentLine $record, array $data) {
                        try {
                            $updated = app(InventoryFulfillmentDispenseService::class)
                                ->complete($record, Auth::user(), null, [
                                    'batch_lot' => $data['batch_lot'] ?? null,
                                    'serials' => $data['serials'] ?? [],
                                ]);

                            $record->fill($updated->getAttributes());
                            $record->syncOriginal();

                            Notification::make()
                                ->title('Dispense completed')
                                ->body($updated->supportsApprovedPool()
                                    ? 'Status is now '.$updated->statusLabel().'. Stock deducted and Approved Pool updated.'
                                    : 'Status is now '.$updated->statusLabel().'. Stock deducted and ticket completed (no Approved Pool for this End Store).')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Cannot dispense')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Dispense failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    })
                    ->after(fn () => $this->resetTable()),

                Action::make('stageBasket')
                    ->label(fn (InventoryFulfillmentLine $record): string => sprintf(
                        'Stage all (%d)',
                        max(1, $this->basketStageableCount($record))
                    ))
                    ->button()
                    ->size('sm')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->form([
                        TextInput::make('tote_barcode')
                            ->label('Tote barcode')
                            ->placeholder('Scan or type the tote label')
                            ->required()
                            ->maxLength(100)
                            ->helperText('One tote for the whole basket.'),
                    ])
                    ->modalHeading('Stage whole basket')
                    ->modalDescription(fn (InventoryFulfillmentLine $record): string => sprintf(
                        'Stage all %d open item(s) for %s into one tote. Stock stays here until release.',
                        max(1, $this->basketStageableCount($record)),
                        $record->client?->name ?? 'this client'
                    ))
                    ->modalSubmitActionLabel('Stage all')
                    ->visible(fn (InventoryFulfillmentLine $record) => $record->isInpatient()
                        && $record->isStageable())
                    ->action(function (InventoryFulfillmentLine $record, array $data) {
                        try {
                            $tote = trim((string) ($data['tote_barcode'] ?? ''));
                            if ($tote === '') {
                                throw ValidationException::withMessages([
                                    'tote_barcode' => 'Tote barcode is required before staging.',
                                ]);
                            }

                            $result = app(InventoryFulfillmentStageService::class)
                                ->stageBasket(
                                    $record,
                                    Auth::user(),
                                    true,
                                    $tote
                                );

                            $this->lastHandoffRef = $result['token']->uuid;
                            $this->lastHandoffBasket = $record->client?->name
                                ?? ('Basket '.$record->basket_key);

                            $count = $result['lines']->count();
                            $clinical = app(ClinicalModuleIntegrationService::class);
                            $body = $clinical->handoffBypassEnabled()
                                ? "Staged {$count} item(s). Release with code {$clinical->handoffBypassCode()} (test bypass) when ready."
                                : "Staged {$count} item(s). Release when the nurse gives their 5-digit code.";

                            Notification::make()
                                ->title('Basket staged')
                                ->body($body)
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Cannot stage')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Stage failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    })
                    ->after(fn () => $this->resetTable()),

                Action::make('releaseHandoff')
                    ->label(fn (InventoryFulfillmentLine $record): string => sprintf(
                        'Release all (%d)',
                        max(1, $this->basketStagedCount($record))
                    ))
                    ->button()
                    ->size('sm')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->form(function (InventoryFulfillmentLine $record): array {
                        $session = app(InventoryHandoffTokenService::class)->activeSessionForLine($record);
                        $lineIds = array_values(array_map('intval', $session?->fulfillment_line_ids ?? [$record->id]));
                        $stagedLines = InventoryFulfillmentLine::query()
                            ->whereIn('id', $lineIds)
                            ->where('status', InventoryFulfillmentLine::STATUS_STAGED)
                            ->orderBy('id')
                            ->get();

                        $config = InventoryModuleConfig::query()
                            ->where('business_id', InventoryBusinessContext::effectiveBusinessId())
                            ->first();

                        $fields = [
                            TextInput::make('code')
                                ->label('Release code')
                                ->placeholder('5-digit code')
                                ->helperText(function (): string {
                                    $clinical = app(ClinicalModuleIntegrationService::class);
                                    if ($clinical->handoffBypassEnabled()) {
                                        return 'Test bypass: '.$clinical->handoffBypassCode().'. Or use the nurse’s code when Clinical is connected.';
                                    }

                                    return 'Enter the 5-digit code from the ward nurse.';
                                })
                                ->required()
                                ->minLength(5)
                                ->maxLength(5)
                                ->rule('regex:/^\d{5}$/')
                                ->autocomplete(false),
                        ];

                        if ($config?->batchLotTrackingEnabled() || $config?->serialNumberTrackingEnabled()) {
                            foreach ($stagedLines as $staged) {
                                $prefix = 'trace_'.$staged->id.'_';
                                if ($config->batchLotTrackingEnabled()) {
                                    $fields[] = TextInput::make($prefix.'batch_lot')
                                        ->label('Batch / lot — '.$staged->item_name)
                                        ->required()
                                        ->maxLength(120);
                                }
                                if ($config->serialNumberTrackingEnabled()) {
                                    $fields[] = TagsInput::make($prefix.'serials')
                                        ->label('Serials — '.$staged->item_name)
                                        ->required()
                                        ->placeholder('Type a serial and press Enter')
                                        ->helperText('One serial per unit released for this line.');
                                }
                            }
                        }

                        return $fields;
                    })
                    ->modalHeading('Release whole basket')
                    ->modalDescription(fn (InventoryFulfillmentLine $record): string => sprintf(
                        'Release all %d staged item(s) for %s from %s.',
                        max(1, $this->basketStagedCount($record)),
                        $record->client?->name ?? 'this client',
                        $record->store?->name ?? 'this End Store'
                    ))
                    ->modalSubmitActionLabel('Release all')
                    ->visible(fn (InventoryFulfillmentLine $record) => $record->isInpatient() && $record->isStaged())
                    ->action(function (InventoryFulfillmentLine $record, array $data) {
                        try {
                            $store = $record->store ?? Store::query()->find($record->store_id);
                            if (! $store) {
                                throw ValidationException::withMessages(['store_id' => 'Missing End Store on this line.']);
                            }

                            $handoff = app(InventoryHandoffTokenService::class);
                            $session = $handoff->activeSessionForLine($record);
                            $traceabilityByLineId = [];
                            foreach ($data as $key => $value) {
                                if (! is_string($key) || ! str_starts_with($key, 'trace_')) {
                                    continue;
                                }
                                if (preg_match('/^trace_(\d+)_(batch_lot|serials)$/', $key, $m)) {
                                    $lineId = (int) $m[1];
                                    $field = $m[2];
                                    $traceabilityByLineId[$lineId] ??= [];
                                    $traceabilityByLineId[$lineId][$field === 'batch_lot' ? 'batch_lot' : 'serials'] = $value;
                                }
                            }

                            $result = $handoff->release(
                                $store,
                                (string) ($data['code'] ?? ''),
                                Auth::user(),
                                $session,
                                [],
                                $traceabilityByLineId,
                            );

                            $done = count($result['completed']);
                            $failed = count($result['failed']);

                            if ($done === 0) {
                                $first = $result['failed'][0]['message'] ?? 'No staged items were released.';
                                Notification::make()
                                    ->title('Release failed')
                                    ->body($first)
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $parts = ["{$done} item(s) released."];
                            if ($failed > 0) {
                                $first = $result['failed'][0]['message'] ?? '';
                                $parts[] = "{$failed} failed".($first ? ": {$first}" : '.');
                            }

                            $this->clearLastHandoffBanner();

                            Notification::make()
                                ->title($failed > 0 ? 'Partially released' : 'Basket released')
                                ->body(implode(' ', $parts))
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->persistent()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Cannot release')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Release failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    })
                    ->after(fn () => $this->resetTable()),

                ActionGroup::make([
                    Action::make('changeTote')
                        ->label('Change tote')
                        ->icon('heroicon-o-archive-box')
                        ->visible(fn (InventoryFulfillmentLine $record) => $record->isInpatient() && $record->isStaged())
                        ->form([
                            TextInput::make('tote_barcode')
                                ->label('Tote barcode')
                                ->placeholder('e.g. TOTE-CHW-001 or scan label on tote')
                                ->required()
                                ->maxLength(100)
                                ->helperText('Updates the tote ID on this staged handoff.'),
                        ])
                        ->modalHeading('Change tote barcode')
                        ->modalSubmitActionLabel('Update tote')
                        ->action(function (InventoryFulfillmentLine $record, array $data) {
                            try {
                                $tote = trim((string) ($data['tote_barcode'] ?? ''));
                                if ($tote === '') {
                                    throw ValidationException::withMessages([
                                        'tote_barcode' => 'Enter the tote barcode or ID on the physical bin before staging.',
                                    ]);
                                }

                                $result = app(InventoryFulfillmentStageService::class)
                                    ->stageBasket($record, Auth::user(), true, $tote);

                                $this->lastHandoffRef = $result['token']->uuid;
                                $this->lastHandoffBasket = $record->client?->name
                                    ?? ('Basket '.$record->basket_key);

                                Notification::make()
                                    ->title('Tote updated')
                                    ->body('Handoff ref: '.$result['token']->uuid)
                                    ->success()
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title('Cannot update tote')
                                    ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title('Update failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        })
                        ->after(fn () => $this->resetTable()),
                    Action::make('setInpatient')
                        ->label('Set inpatient')
                        ->icon('heroicon-o-building-office-2')
                        ->visible(fn (InventoryFulfillmentLine $record) => $record->isOpen() && $record->isOutpatient())
                        ->requiresConfirmation()
                        ->modalHeading('Switch to inpatient (batch & stage)?')
                        ->action(function (InventoryFulfillmentLine $record) {
                            $record->update([
                                'fulfillment_strategy' => InventoryFulfillmentStrategy::BATCH_AND_STAGE,
                                'supports_approved_pool' => true,
                            ]);
                            Notification::make()->title('Set to inpatient')->success()->send();
                        })
                        ->after(fn () => $this->resetTable()),
                    Action::make('setOutpatient')
                        ->label('Set outpatient')
                        ->icon('heroicon-o-user')
                        ->visible(fn (InventoryFulfillmentLine $record) => $record->isOpen()
                            && $record->isInpatient()
                            && $record->status !== InventoryFulfillmentLine::STATUS_STAGED)
                        ->requiresConfirmation()
                        ->modalHeading('Switch to outpatient (dispense now)?')
                        ->action(function (InventoryFulfillmentLine $record) {
                            $record->update([
                                'fulfillment_strategy' => InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE,
                                'supports_approved_pool' => false,
                            ]);
                            Notification::make()->title('Set to outpatient')->success()->send();
                        })
                        ->after(fn () => $this->resetTable()),
                    Action::make('startPicking')
                        ->label('Start pick')
                        ->icon('heroicon-o-hand-raised')
                        ->visible(fn (InventoryFulfillmentLine $record) => $record->status === InventoryFulfillmentLine::STATUS_PENDING)
                        ->action(function (InventoryFulfillmentLine $record) {
                            $record->update(['status' => InventoryFulfillmentLine::STATUS_PICKING]);
                            Notification::make()->title('Picking started')->success()->send();
                        })
                        ->after(fn () => $this->resetTable()),
                    Action::make('markUrgent')
                        ->label('Mark urgent')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('warning')
                        ->visible(fn (InventoryFulfillmentLine $record) => $record->isOpen()
                            && $record->priority === InventoryFulfillmentLine::PRIORITY_NORMAL)
                        ->action(function (InventoryFulfillmentLine $record) {
                            $record->update(['priority' => InventoryFulfillmentLine::PRIORITY_URGENT]);
                            Notification::make()->title('Marked urgent')->success()->send();
                        })
                        ->after(fn () => $this->resetTable()),
                    Action::make('markStat')
                        ->label('Escalate to STAT')
                        ->icon('heroicon-o-bolt')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (InventoryFulfillmentLine $record) => $record->isOpen() && ! $record->isStat())
                        ->action(function (InventoryFulfillmentLine $record) {
                            $record->update([
                                'priority' => InventoryFulfillmentLine::PRIORITY_STAT,
                                'acknowledged_at' => null,
                                'acknowledged_by' => null,
                            ]);
                            Notification::make()->title('Escalated to STAT')->success()->send();
                        })
                        ->after(fn () => $this->resetTable()),
                    Action::make('cancel')
                        ->label('Cancel line')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (InventoryFulfillmentLine $record) => $record->isOpen())
                        ->action(function (InventoryFulfillmentLine $record) {
                            $record->update(['status' => InventoryFulfillmentLine::STATUS_CANCELLED]);
                            Notification::make()->title('Line cancelled')->success()->send();
                        })
                        ->after(fn () => $this->resetTable()),
                ])
                    ->label('More')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->size('sm')
                    ->color('gray')
                    ->visible(fn (InventoryFulfillmentLine $record) => $record->isOpen()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('stageSelectedBaskets')
                        ->label('Stage selected baskets')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->form([
                            TextInput::make('tote_barcode')
                                ->label('Tote barcode')
                                ->placeholder('Scan or type tote label')
                                ->required()
                                ->maxLength(100)
                                ->helperText('Used for each selected basket (same tote ID if they share one physical tote; otherwise stage baskets one at a time).'),
                        ])
                        ->modalHeading('Stage selected baskets')
                        ->modalDescription('Stages every open inpatient item in each selected basket.')
                        ->modalSubmitActionLabel('Stage all selected')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (EloquentCollection $records, array $data): void {
                            $tote = trim((string) ($data['tote_barcode'] ?? ''));
                            if ($tote === '') {
                                Notification::make()->title('Tote barcode required')->danger()->send();

                                return;
                            }

                            $seeds = $records
                                ->filter(fn (InventoryFulfillmentLine $line) => $line->isInpatient() && $line->isStageable())
                                ->unique(fn (InventoryFulfillmentLine $line) => $line->store_id.'|'.$line->basket_key)
                                ->values();

                            if ($seeds->isEmpty()) {
                                Notification::make()
                                    ->title('Nothing to stage')
                                    ->body('Select open inpatient lines.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $stagedBaskets = 0;
                            $stagedLines = 0;
                            $errors = [];

                            foreach ($seeds as $seed) {
                                try {
                                    $result = app(InventoryFulfillmentStageService::class)
                                        ->stageBasket($seed, Auth::user(), true, $tote);
                                    $stagedBaskets++;
                                    $stagedLines += $result['lines']->count();
                                    $this->lastHandoffRef = $result['token']->uuid;
                                    $this->lastHandoffBasket = $seed->client?->name
                                        ?? ('Basket '.$seed->basket_key);
                                } catch (\Throwable $e) {
                                    $errors[] = ($seed->client?->name ?? $seed->basket_key).': '.$e->getMessage();
                                }
                            }

                            if ($stagedBaskets > 0) {
                                Notification::make()
                                    ->title('Baskets staged')
                                    ->body("{$stagedBaskets} basket(s), {$stagedLines} item(s).")
                                    ->success()
                                    ->send();
                            }

                            if ($errors !== []) {
                                Notification::make()
                                    ->title('Some baskets failed')
                                    ->body(implode(' | ', array_slice($errors, 0, 3)))
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        })
                        ->after(fn () => $this->resetTable()),
                    BulkAction::make('releaseSelectedBaskets')
                        ->label('Release selected baskets')
                        ->icon('heroicon-o-key')
                        ->color('success')
                        ->form([
                            TextInput::make('code')
                                ->label('Release code')
                                ->placeholder('5-digit code')
                                ->helperText(function (): string {
                                    $clinical = app(ClinicalModuleIntegrationService::class);
                                    if ($clinical->handoffBypassEnabled()) {
                                        return 'Test bypass: '.$clinical->handoffBypassCode().'. Applied to every selected staged basket.';
                                    }

                                    return 'Same nurse code is used for each selected staged basket.';
                                })
                                ->required()
                                ->minLength(5)
                                ->maxLength(5)
                                ->rule('regex:/^\d{5}$/')
                                ->autocomplete(false),
                        ])
                        ->modalHeading('Release selected baskets')
                        ->modalDescription('Releases all staged items in each selected basket/session.')
                        ->modalSubmitActionLabel('Release all selected')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (EloquentCollection $records, array $data): void {
                            $seeds = $records
                                ->filter(fn (InventoryFulfillmentLine $line) => $line->isInpatient() && $line->isStaged())
                                ->unique(fn (InventoryFulfillmentLine $line) => $line->handoff_token_id
                                    ?: ($line->store_id.'|'.$line->basket_key))
                                ->values();

                            if ($seeds->isEmpty()) {
                                Notification::make()
                                    ->title('Nothing to release')
                                    ->body('Select staged inpatient lines.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $released = 0;
                            $failed = 0;
                            $errors = [];
                            $handoff = app(InventoryHandoffTokenService::class);
                            $code = (string) ($data['code'] ?? '');

                            foreach ($seeds as $seed) {
                                try {
                                    $store = $seed->store ?? Store::query()->find($seed->store_id);
                                    if (! $store) {
                                        throw ValidationException::withMessages([
                                            'store_id' => 'Missing End Store.',
                                        ]);
                                    }
                                    $session = $handoff->activeSessionForLine($seed);
                                    $result = $handoff->release($store, $code, Auth::user(), $session);
                                    $released += count($result['completed']);
                                    $failed += count($result['failed']);
                                } catch (\Throwable $e) {
                                    $failed++;
                                    $errors[] = ($seed->client?->name ?? $seed->basket_key).': '.$e->getMessage();
                                }
                            }

                            $this->clearLastHandoffBanner();

                            Notification::make()
                                ->title($failed > 0 && $released > 0 ? 'Partially released' : ($released > 0 ? 'Baskets released' : 'Release failed'))
                                ->body($released > 0
                                    ? "{$released} item(s) released.".($errors !== [] ? ' '.implode(' | ', array_slice($errors, 0, 2)) : '')
                                    : ($errors[0] ?? 'Could not release selected baskets.'))
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->persistent()
                                ->send();
                        })
                        ->after(fn () => $this->resetTable()),
                ]),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading(match ($this->consoleTab) {
                'outpatient' => 'No outpatient lines',
                'inpatient' => 'No inpatient lines',
                'stat' => 'No STAT lines',
                default => 'No queue lines',
            })
            ->emptyStateDescription(match ($this->consoleTab) {
                'stat' => 'Escalate a line to STAT from More, or wait for STAT-tagged paid goods.',
                default => 'Paid goods for End Stores appear here after payment confirmation.',
            })
            ->emptyStateIcon('heroicon-o-queue-list');
    }

    protected function basketStageableCount(InventoryFulfillmentLine $record): int
    {
        return InventoryFulfillmentLine::query()
            ->where('business_id', $record->business_id)
            ->where('store_id', $record->store_id)
            ->where('basket_key', (string) $record->basket_key)
            ->where('fulfillment_strategy', InventoryFulfillmentStrategy::BATCH_AND_STAGE)
            ->whereIn('status', [
                InventoryFulfillmentLine::STATUS_PENDING,
                InventoryFulfillmentLine::STATUS_PICKING,
                InventoryFulfillmentLine::STATUS_PARTIAL,
            ])
            ->count();
    }

    protected function basketStagedCount(InventoryFulfillmentLine $record): int
    {
        $session = app(InventoryHandoffTokenService::class)->activeSessionForLine($record);
        if ($session && is_array($session->fulfillment_line_ids) && $session->fulfillment_line_ids !== []) {
            return InventoryFulfillmentLine::query()
                ->whereIn('id', $session->fulfillment_line_ids)
                ->where('status', InventoryFulfillmentLine::STATUS_STAGED)
                ->count();
        }

        return InventoryFulfillmentLine::query()
            ->where('business_id', $record->business_id)
            ->where('store_id', $record->store_id)
            ->where('basket_key', (string) $record->basket_key)
            ->where('fulfillment_strategy', InventoryFulfillmentStrategy::BATCH_AND_STAGE)
            ->where('status', InventoryFulfillmentLine::STATUS_STAGED)
            ->count();
    }

    protected function applyConsoleScope(Builder $query): void
    {
        if ($this->selectedStoreId) {
            $query->where('store_id', (int) $this->selectedStoreId);
        }

        match ($this->consoleTab) {
            'outpatient' => $query->where(
                'fulfillment_strategy',
                InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE
            ),
            'inpatient' => $query->where(
                'fulfillment_strategy',
                InventoryFulfillmentStrategy::BATCH_AND_STAGE
            ),
            'stat' => $query->where('priority', InventoryFulfillmentLine::PRIORITY_STAT),
            default => null,
        };
    }

    protected function baseCountQuery(): Builder
    {
        $query = InventoryFulfillmentLine::query()
            ->where('business_id', InventoryBusinessContext::effectiveBusinessId());

        if ($this->selectedStoreId) {
            $query->where('store_id', (int) $this->selectedStoreId);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    protected function openStatuses(): array
    {
        return [
            InventoryFulfillmentLine::STATUS_PENDING,
            InventoryFulfillmentLine::STATUS_PICKING,
            InventoryFulfillmentLine::STATUS_STAGED,
            InventoryFulfillmentLine::STATUS_PARTIAL,
        ];
    }

    public function render(): View
    {
        return view('livewire.inventory.end-store-fulfillment-queue');
    }
}
