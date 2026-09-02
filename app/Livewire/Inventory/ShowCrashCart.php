<?php

namespace App\Livewire\Inventory;

use App\Models\Client;
use App\Models\CrashCartEvent;
use App\Models\CrashCartItem;
use App\Models\InventoryUsageEvent;
use App\Models\Store;
use App\Services\Inventory\InventoryCrashCartService;
use App\Services\Inventory\InventoryRecordUsageService;
use App\Support\InventoryBusinessContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ShowCrashCart extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public Store $cart;

    /** @var string overview|usage|history */
    public string $activeTab = 'overview';

    /** @var array<int, array{item_id: int, item_name: string, par: float, used: float, remaining: float, on_hand: float}> */
    public array $balanceRows = [];

    public function mount(Store $cart): void
    {
        abort_unless((int) $cart->business_id === (int) InventoryBusinessContext::effectiveBusinessId(), 404);
        abort_unless($cart->isCrashCart(), 404);

        $this->cart = $cart->load(['parent:id,name', 'branch:id,name']);
        $this->refreshBalances();
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'usage', 'history'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function refreshBalances(): void
    {
        $this->cart->refresh();
        $this->cart->loadMissing(['parent:id,name', 'branch:id,name', 'crashCartItems.item']);

        $this->balanceRows = app(InventoryCrashCartService::class)
            ->balances($this->cart)
            ->keyBy('item_id')
            ->all();
    }

    /**
     * @return array{lines: list<array{item_id: int, item_name: string, quantity: float, parent_on_hand: float}>, shortages: list<string>, parent: ?Store}
     */
    public function restockPlan(): array
    {
        if (! $this->cart->isCrashCartOpen()) {
            return ['lines' => [], 'shortages' => [], 'parent' => $this->cart->parent];
        }

        return app(InventoryCrashCartService::class)->restockPlan($this->cart);
    }

    public function usageCount(): int
    {
        return InventoryUsageEvent::query()
            ->where('business_id', $this->cart->business_id)
            ->where('store_id', $this->cart->id)
            ->where('context', InventoryUsageEvent::CONTEXT_CRASH_CART)
            ->count();
    }

    public function historyCount(): int
    {
        return CrashCartEvent::query()
            ->where('store_id', $this->cart->id)
            ->count();
    }

    public function table(Table $table): Table
    {
        return match ($this->activeTab) {
            'usage' => $this->usageTable($table),
            'history' => $this->historyTable($table),
            default => $this->overviewTable($table),
        };
    }

    protected function overviewTable(Table $table): Table
    {
        $isOpen = $this->cart->isCrashCartOpen();
        $plan = $this->restockPlan();
        $canRestock = $isOpen && ($plan['shortages'] ?? []) === [];

        return $table
            ->query(
                CrashCartItem::query()
                    ->where('store_id', $this->cart->id)
                    ->with('item:id,name,code,strength')
                    ->orderBy('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('par_quantity')
                    ->label('Par')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $this->formatQty($state)),
                Tables\Columns\TextColumn::make('used')
                    ->label('Used')
                    ->alignEnd()
                    ->visible($isOpen)
                    ->state(fn (CrashCartItem $record): float => (float) ($this->balanceRows[$record->item_id]['used'] ?? 0))
                    ->formatStateUsing(fn ($state): string => $this->formatQty($state)),
                Tables\Columns\TextColumn::make('remaining')
                    ->label('Remaining')
                    ->alignEnd()
                    ->visible($isOpen)
                    ->state(fn (CrashCartItem $record): float => (float) ($this->balanceRows[$record->item_id]['remaining'] ?? 0))
                    ->formatStateUsing(fn ($state): string => $this->formatQty($state))
                    ->color(fn ($state): string => (float) $state <= 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('on_hand')
                    ->label('On hand')
                    ->alignEnd()
                    ->state(fn (CrashCartItem $record): float => (float) ($this->balanceRows[$record->item_id]['on_hand'] ?? 0))
                    ->formatStateUsing(fn ($state): string => $this->formatQty($state)),
            ])
            ->headerActions([
                Action::make('breakSeal')
                    ->label('Break seal')
                    ->color('danger')
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (): bool => $this->cart->isCrashCartSealed()
                        && $this->cart->crashCartItems()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Break seal?')
                    ->modalDescription('This opens the crash cart so usage can be recorded against the manifest.')
                    ->modalSubmitActionLabel('Yes, break seal')
                    ->action(function (): void {
                        try {
                            app(InventoryCrashCartService::class)->breakSeal($this->cart, Auth::user());
                            $this->refreshBalances();
                            $this->resetTable();
                            Notification::make()
                                ->title('Seal broken')
                                ->body('Record usage against the cart manifest.')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Cannot break seal')
                                ->body(collect($e->errors())->flatten()->first() ?? 'Validation failed.')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('restockReseal')
                    ->label('Restock & reseal')
                    ->color('success')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (): bool => $this->cart->isCrashCartOpen())
                    ->disabled(fn () => ! $canRestock)
                    ->tooltip(fn (): ?string => $canRestock
                        ? null
                        : 'End Store is short on one or more lines.')
                    ->modalHeading('Restock & reseal')
                    ->modalSubmitActionLabel('Yes, restock & reseal')
                    ->modalDescription(fn (): HtmlString => $this->restockModalDescription($plan))
                    ->form([
                        TextInput::make('seal_number')
                            ->label('New seal #')
                            ->maxLength(64)
                            ->placeholder('Auto if blank'),
                    ])
                    ->action(function (array $data) use ($canRestock): void {
                        if (! $canRestock) {
                            Notification::make()
                                ->title('Cannot restock')
                                ->body('End Store does not have enough stock for every short line.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $result = app(InventoryCrashCartService::class)->restockAndReseal(
                                $this->cart,
                                Auth::user(),
                                isset($data['seal_number']) ? trim((string) $data['seal_number']) : null
                            );
                            $this->cart = $result['store'];
                            $this->refreshBalances();
                            $this->resetTable();

                            $pulled = count($result['restocked']);
                            Notification::make()
                                ->title('Restocked & resealed')
                                ->body($pulled === 0
                                    ? 'Cart resealed (seal '.$result['seal_number'].'). Nothing pulled from the End Store.'
                                    : 'Pulled '.$pulled.' line'.($pulled === 1 ? '' : 's').' and resealed (seal '.$result['seal_number'].').')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Restock blocked')
                                ->body(collect($e->errors())->flatten()->first() ?? 'Validation failed.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->paginated([25, 50])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('No manifest items')
            ->emptyStateDescription('Edit this crash cart under Manage Stores to set the manifesto.');
    }

    protected function usageTable(Table $table): Table
    {
        $isOpen = $this->cart->isCrashCartOpen();

        return $table
            ->query(
                InventoryUsageEvent::query()
                    ->with(['client:id,name,client_id', 'item:id,name', 'recordedBy:id,name'])
                    ->where('business_id', $this->cart->business_id)
                    ->where('store_id', $this->cart->id)
                    ->where('context', InventoryUsageEvent::CONTEXT_CRASH_CART)
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label(fn (): string => inventory_label('client'))
                    ->description(fn (InventoryUsageEvent $record): ?string => $record->client?->client_id)
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $this->formatQty($state)),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Note')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('By')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make('recordUsage')
                    ->label('Record usage')
                    ->icon('heroicon-o-plus')
                    ->visible($isOpen)
                    ->disabled(fn (): bool => $this->availableManifestOptions() === [])
                    ->tooltip(fn (): ?string => $this->availableManifestOptions() === []
                        ? 'No items left available on this cart.'
                        : null)
                    ->modalHeading('Record crash cart usage')
                    ->modalSubmitActionLabel('Save usage')
                    ->createAnother(false)
                    ->form([
                        Select::make('client_id')
                            ->label(fn (): string => inventory_label('client'))
                            ->options(fn (): array => Client::query()
                                ->where('business_id', $this->cart->business_id)
                                ->orderBy('name')
                                ->get(['id', 'name', 'client_id'])
                                ->mapWithKeys(fn (Client $c) => [
                                    $c->id => $c->name.' ['.$c->client_id.']',
                                ])
                                ->all())
                            ->searchable()
                            ->required(),
                        Select::make('item_id')
                            ->label('Manifest item')
                            ->options(fn (): array => $this->availableManifestOptions())
                            ->required()
                            ->reactive(),
                        TextInput::make('quantity')
                            ->label('How many?')
                            ->numeric()
                            ->minValue(0.0001)
                            ->default(1)
                            ->required(),
                        TextInput::make('notes')
                            ->label('Note')
                            ->maxLength(500)
                            ->placeholder('Optional'),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $svc = app(InventoryCrashCartService::class);
                            $svc->assertManifestItem($this->cart, (int) $data['item_id']);

                            app(InventoryRecordUsageService::class)->record([
                                'business_id' => (int) $this->cart->business_id,
                                'context' => InventoryUsageEvent::CONTEXT_CRASH_CART,
                                'client_id' => (int) $data['client_id'],
                                'item_id' => (int) $data['item_id'],
                                'store_id' => (int) $this->cart->id,
                                'quantity' => $data['quantity'],
                                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
                                'occurred_at' => now(),
                            ], Auth::user());

                            $this->refreshBalances();
                            $this->resetTable();

                            Notification::make()
                                ->title('Usage recorded')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Could not record usage')
                                ->body(collect($e->errors())->flatten()->first() ?? 'Validation failed.')
                                ->danger()
                                ->send();
                        }
                    })
                    ->successNotification(null),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('No usage yet')
            ->emptyStateDescription($isOpen
                ? 'Record usage from this tab while the seal is broken.'
                : 'Break the seal on the Overview tab to record usage.');
    }

    protected function historyTable(Table $table): Table
    {
        return $table
            ->query(
                CrashCartEvent::query()
                    ->with(['recordedBy:id,name', 'parentStore:id,name'])
                    ->where('store_id', $this->cart->id)
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (CrashCartEvent $record): string => $record->label())
                    ->color(fn (CrashCartEvent $record): string => match ($record->event_type) {
                        CrashCartEvent::TYPE_BREAK_SEAL => 'danger',
                        CrashCartEvent::TYPE_RESTOCK_RESEAL => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('seal_number')
                    ->label('Seal')
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->copyable(),
                Tables\Columns\TextColumn::make('details')
                    ->label('Details')
                    ->wrap()
                    ->state(fn (CrashCartEvent $record): string => $this->historyDetails($record)),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('By')
                    ->placeholder('—'),
            ])
            ->paginated([25, 50])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('No history yet')
            ->emptyStateDescription('Seal breaks and restock & reseal events will appear here.');
    }

    /**
     * @return array<int, string>
     */
    protected function availableManifestOptions(): array
    {
        $this->refreshBalances();

        $options = [];
        foreach ($this->balanceRows as $row) {
            $available = min((float) $row['remaining'], (float) $row['on_hand']);
            if ($available <= 0) {
                continue;
            }
            $options[(int) $row['item_id']] = $row['item_name'].' ('.$this->formatQty($available).' available)';
        }

        return $options;
    }

    /**
     * @param  array{lines: list<array{item_id: int, item_name: string, quantity: float, parent_on_hand: float}>, shortages: list<string>, parent: ?Store}  $plan
     */
    protected function restockModalDescription(array $plan): HtmlString
    {
        $parentName = e($plan['parent']?->name ?? $this->cart->parent?->name ?? 'End Store');
        $lines = $plan['lines'] ?? [];
        $shortages = $plan['shortages'] ?? [];

        if ($shortages !== []) {
            $items = collect($shortages)->map(fn ($s) => '<li>'.e($s).'</li>')->implode('');

            return new HtmlString(
                '<p class="text-sm text-red-700">Cannot restock until the End Store has enough of every short line.</p>'
                .'<ul class="mt-2 list-disc pl-5 text-sm text-red-700">'.$items.'</ul>'
            );
        }

        if ($lines === []) {
            return new HtmlString(
                '<p class="text-sm text-gray-600">Cart is already at par. Reseal without pulling stock from <strong>'.$parentName.'</strong>.</p>'
            );
        }

        $rows = collect($lines)->map(function (array $line) {
            return '<tr>'
                .'<td class="py-1 pr-4">'.e($line['item_name']).'</td>'
                .'<td class="py-1 pr-4 text-right tabular-nums">'.e($this->formatQty($line['quantity'])).'</td>'
                .'<td class="py-1 text-right tabular-nums">'.e($this->formatQty($line['parent_on_hand'])).'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<p class="text-sm text-gray-600 mb-2">Pull missing qty from <strong>'.$parentName.'</strong>, refill to par, then seal again.</p>'
            .'<table class="w-full text-sm"><thead><tr class="text-left text-gray-500">'
            .'<th class="pb-1">Item</th><th class="pb-1 text-right">Pull</th><th class="pb-1 text-right">Parent on hand</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>'
        );
    }

    protected function historyDetails(CrashCartEvent $record): string
    {
        if ($record->event_type === CrashCartEvent::TYPE_BREAK_SEAL) {
            return 'Seal opened for usage';
        }

        $lines = $record->lines ?? [];
        $parent = $record->parentStore?->name;

        if ($lines === []) {
            return 'Resealed with no stock pull'.($parent ? ' (from '.$parent.')' : '');
        }

        $parts = [];
        foreach ($lines as $line) {
            $parts[] = $this->formatQty($line['quantity'] ?? 0)
                .' × '.($line['item_name'] ?? ('Item #'.($line['item_id'] ?? '?')));
        }

        $detail = 'Pulled '.implode(', ', $parts);
        if ($parent) {
            $detail .= ' from '.$parent;
        }

        return $detail;
    }

    protected function formatQty(mixed $state): string
    {
        return rtrim(rtrim(number_format((float) $state, 2), '0'), '.') ?: '0';
    }

    public function render(): View
    {
        return view('livewire.inventory.show-crash-cart');
    }
}
