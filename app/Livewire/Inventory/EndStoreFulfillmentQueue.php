<?php

namespace App\Livewire\Inventory;

use App\Models\ClientSpaceStoreAssignment;
use App\Models\InventoryFulfillmentLine;
use App\Models\Store;
use App\Services\Inventory\InventoryFulfillmentDispenseService;
use App\Services\Inventory\InventoryFulfillmentStageService;
use App\Services\Inventory\InventoryHandoffTokenService;
use App\Support\InventoryBusinessContext;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
            ->where('fulfillment_strategy', ClientSpaceStoreAssignment::STRATEGY_DISCRETE_IMMEDIATE)
            ->whereIn('status', $this->openStatuses())
            ->count();
    }

    public function inpatientOpenCount(): int
    {
        return $this->baseCountQuery()
            ->where('fulfillment_strategy', ClientSpaceStoreAssignment::STRATEGY_BATCH_AND_STAGE)
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
                'clientSpace:id,name',
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
                Tables\Columns\TextColumn::make('clientSpace.name')
                    ->label('Space')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fulfillment_strategy')
                    ->label('Path')
                    ->formatStateUsing(fn (?string $state, InventoryFulfillmentLine $record): string => match ($record->fulfillment_strategy) {
                        ClientSpaceStoreAssignment::STRATEGY_BATCH_AND_STAGE => 'Inpatient',
                        ClientSpaceStoreAssignment::STRATEGY_DISCRETE_IMMEDIATE => 'Outpatient',
                        default => $record->strategyLabel(),
                    })
                    ->badge()
                    ->color(fn (InventoryFulfillmentLine $record): string => $record->fulfillment_strategy === ClientSpaceStoreAssignment::STRATEGY_BATCH_AND_STAGE
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
                        ClientSpaceStoreAssignment::STRATEGY_DISCRETE_IMMEDIATE => 'Outpatient',
                        ClientSpaceStoreAssignment::STRATEGY_BATCH_AND_STAGE => 'Inpatient',
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
                    ->requiresConfirmation()
                    ->modalHeading('Complete dispense')
                    ->modalDescription(fn (InventoryFulfillmentLine $record): string => sprintf(
                        'Dispense %s × %s from %s? Stock will leave this End Store, the Approved Pool will be updated, and the Main Module ticket will close.',
                        rtrim(rtrim(number_format((float) $record->quantity - (float) $record->quantity_fulfilled, 2), '0'), '.'),
                        $record->item_name,
                        $record->store?->name ?? 'this End Store'
                    ))
                    ->modalSubmitActionLabel('Confirm dispense')
                    ->visible(fn (InventoryFulfillmentLine $record) => $record->isOutpatient() && $record->isOpen() && ! $record->isStaged())
                    ->action(function (InventoryFulfillmentLine $record) {
                        try {
                            $updated = app(InventoryFulfillmentDispenseService::class)
                                ->complete($record, Auth::user());

                            $record->fill($updated->getAttributes());
                            $record->syncOriginal();

                            Notification::make()
                                ->title('Dispense completed')
                                ->body('Status is now '.$updated->statusLabel().'. Stock deducted and Approved Pool updated.')
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
                    ->label('Stage')
                    ->button()
                    ->size('sm')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->form([
                        TextInput::make('tote_barcode')
                            ->label('Tote barcode')
                            ->placeholder('Scan or type tote barcode')
                            ->maxLength(100)
                            ->helperText('Optional physical tote ID for ward delivery.'),
                    ])
                    ->modalHeading('Stage for collection')
                    ->modalDescription(fn (InventoryFulfillmentLine $record): string => sprintf(
                        'Stage %s’s open inpatient lines. Clinical Module will alert the ward; the nurse receives the 5-digit code. Stock stays in the End Store until release.',
                        $record->client?->name ?? 'this client'
                    ))
                    ->modalSubmitActionLabel('Stage')
                    ->visible(fn (InventoryFulfillmentLine $record) => $record->isInpatient()
                        && ($record->isStageable() || $record->isStaged()))
                    ->action(function (InventoryFulfillmentLine $record, array $data) {
                        try {
                            $result = app(InventoryFulfillmentStageService::class)
                                ->stageBasket(
                                    $record,
                                    Auth::user(),
                                    true,
                                    isset($data['tote_barcode']) ? (string) $data['tote_barcode'] : null
                                );

                            $this->lastHandoffRef = $result['token']->uuid;
                            $this->lastHandoffBasket = $record->client?->name
                                ?? ('Basket '.$record->basket_key);

                            Notification::make()
                                ->title('Staged — ward notified')
                                ->body('Clinical Module was alerted for collection. Ask the nurse for their 5-digit code, then Release. Ref: '.$result['token']->uuid)
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

                Action::make('pickRoute')
                    ->label('Pick route')
                    ->button()
                    ->size('sm')
                    ->icon('heroicon-o-map')
                    ->color('gray')
                    ->url(fn (InventoryFulfillmentLine $record): string => route('inventory.fulfillment.pick-route', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (InventoryFulfillmentLine $record) => $record->isInpatient() && $record->isOpen()),

                Action::make('releaseHandoff')
                    ->label('Release')
                    ->button()
                    ->size('sm')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->form(function (InventoryFulfillmentLine $record): array {
                        $session = app(InventoryHandoffTokenService::class)->activeSessionForLine($record);
                        $lineIds = array_values(array_map('intval', $session?->fulfillment_line_ids ?? [$record->id]));
                        $options = InventoryFulfillmentLine::query()
                            ->whereIn('id', $lineIds)
                            ->where('status', InventoryFulfillmentLine::STATUS_STAGED)
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (InventoryFulfillmentLine $line) => [
                                $line->id => sprintf(
                                    '%s × %s',
                                    rtrim(rtrim(number_format((float) $line->quantity - (float) $line->quantity_fulfilled, 2), '0'), '.'),
                                    $line->item_name
                                ),
                            ])
                            ->all();

                        return [
                            TextInput::make('code')
                                ->label('Nurse handoff code (Clinical)')
                                ->placeholder('5-digit code from ward nurse')
                                ->helperText('Issued by Clinical Module after Collect Medications — not by Inventory.')
                                ->required()
                                ->minLength(5)
                                ->maxLength(5)
                                ->rule('regex:/^\d{5}$/')
                                ->autocomplete(false),
                            CheckboxList::make('flagged_line_ids')
                                ->label('Flag for correction (exclude from release)')
                                ->helperText('Flagged packages roll back to Pending; remaining lines release if the code validates.')
                                ->options($options)
                                ->columns(1)
                                ->visible(count($options) > 0),
                        ];
                    })
                    ->modalHeading('Release handoff')
                    ->modalDescription(fn (InventoryFulfillmentLine $record): string => sprintf(
                        'Enter the Clinical Module code from the ward nurse to release staged goods for %s from %s.',
                        $record->client?->name ?? 'this client',
                        $record->store?->name ?? 'this End Store'
                    ))
                    ->modalSubmitActionLabel('Validate & release')
                    ->visible(fn (InventoryFulfillmentLine $record) => $record->isInpatient() && $record->isStaged())
                    ->action(function (InventoryFulfillmentLine $record, array $data) {
                        try {
                            $store = $record->store ?? Store::query()->find($record->store_id);
                            if (! $store) {
                                throw ValidationException::withMessages(['store_id' => 'Missing End Store on this line.']);
                            }

                            $handoff = app(InventoryHandoffTokenService::class);
                            $session = $handoff->activeSessionForLine($record);

                            $result = $handoff->release(
                                $store,
                                (string) ($data['code'] ?? ''),
                                Auth::user(),
                                $session,
                                array_map('intval', $data['flagged_line_ids'] ?? []),
                            );

                            $done = count($result['completed']);
                            $flagged = count($result['flagged']);
                            $failed = count($result['failed']);

                            $parts = ["{$done} line(s) released."];
                            if ($flagged > 0) {
                                $parts[] = "{$flagged} flagged back to Pending.";
                            }
                            if ($failed > 0) {
                                $first = $result['failed'][0]['message'] ?? '';
                                $parts[] = "{$failed} failed".($first ? ": {$first}" : '.');
                            }

                            $this->clearLastHandoffBanner();

                            Notification::make()
                                ->title(($flagged > 0 || $failed > 0) && $done > 0 ? 'Partially released' : 'Handoff released')
                                ->body(implode(' ', $parts))
                                ->color(($flagged > 0 || $failed > 0) ? 'warning' : 'success')
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

    protected function applyConsoleScope(Builder $query): void
    {
        if ($this->selectedStoreId) {
            $query->where('store_id', (int) $this->selectedStoreId);
        }

        match ($this->consoleTab) {
            'outpatient' => $query->where(
                'fulfillment_strategy',
                ClientSpaceStoreAssignment::STRATEGY_DISCRETE_IMMEDIATE
            ),
            'inpatient' => $query->where(
                'fulfillment_strategy',
                ClientSpaceStoreAssignment::STRATEGY_BATCH_AND_STAGE
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
