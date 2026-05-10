<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use App\Models\VendorBalanceStatementItemRow;
use App\Services\ThirdPartyPayerStatementPresenter;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ThirdPartyVendorBalanceStatementTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use Tables\Concerns\InteractsWithTable {
        getTableRecords as filamentBaseGetTableRecords;
    }

    public int $thirdPartyPayerId;

    public string $statementView = 'items';

    public function mount(int $thirdPartyPayerId, string $statementView = 'items'): void
    {
        $this->thirdPartyPayerId = $thirdPartyPayerId;
        $this->statementView = in_array($statementView, ['items', 'transactions'], true)
            ? $statementView
            : 'items';

        $payer = ThirdPartyPayer::query()->findOrFail($this->thirdPartyPayerId);
        $businessId = Auth::user()?->business_id;
        abort_unless(
            $businessId && ((int) $payer->business_id === (int) $businessId || (int) $businessId === 1),
            403
        );
    }

    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    public function getTableRecords(): Collection | Paginator | CursorPaginator
    {
        if ($this->statementView === 'items') {
            return $this->getItemizedRecordsPaginator();
        }

        return $this->filamentBaseGetTableRecords();
    }

    protected function getItemizedRecordsPaginator(): LengthAwarePaginator
    {
        $perPage = 50;

        $paginator = ThirdPartyPayerBalanceHistory::query()
            ->where('third_party_payer_id', $this->thirdPartyPayerId)
            ->with(['invoice.client', 'client'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $rows = ThirdPartyPayerStatementPresenter::rowsFromHistories($paginator->getCollection());

        $records = $rows->values()->map(function (array $row, int $idx): VendorBalanceStatementItemRow {
            $histId = (int) ($row['history_id'] ?? 0);
            $invoice = $row['invoice'] ?? null;
            $client = $row['client'] ?? null;

            $m = new VendorBalanceStatementItemRow;
            $m->exists = false;
            $m->setRawAttributes([
                'id' => $histId.'-'.$idx,
                'line_label' => (string) ($row['line_label'] ?? ''),
                'detail_description' => $row['detail_description'] ?? null,
                'reference' => $row['reference'] ?? null,
                'transaction_type' => (string) ($row['transaction_type'] ?? ''),
                'amount' => $row['amount'] ?? 0,
                'payment_method' => $row['payment_method'] ?? null,
                'payment_status' => $row['payment_status'] ?? null,
                'created_at' => $row['created_at'],
                'invoice_id' => $row['invoice_id'] ?? ($invoice instanceof Invoice ? $invoice->id : null),
                'client_id' => $client instanceof Client ? $client->id : null,
            ]);

            if ($client instanceof Client) {
                $m->setRelation('client', $client);
            }
            if ($invoice instanceof Invoice) {
                $m->setRelation('invoice', $invoice);
            }

            return $m;
        });

        return new LengthAwarePaginator(
            $records->all(),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $paginator->getPageName(),
                'query' => request()->query(),
            ]
        );
    }

    public function table(Table $table): Table
    {
        return $this->statementView === 'items'
            ? $this->configureItemsTable($table)
            : $this->configureTransactionsTable($table);
    }

    protected function configureItemsTable(Table $table): Table
    {
        return $table
            ->query(
                ThirdPartyPayerBalanceHistory::query()->whereRaw('1 = 0')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & time')
                    ->dateTime('M j, Y')
                    ->description(fn ($state, $record): ?string => $record instanceof VendorBalanceStatementItemRow && $record->created_at
                        ? $record->created_at->format('H:i:s')
                        : null)
                    ->sortable(false),

                TextColumn::make('line_label')
                    ->label('Item / line')
                    ->wrap()
                    ->description(function ($state, $record): ?string {
                        if (! $record instanceof VendorBalanceStatementItemRow) {
                            return null;
                        }
                        $detail = $record->detail_description;
                        $line = $record->line_label;

                        return $detail && $detail !== $line ? $detail : null;
                    })
                    ->sortable(false),

                TextColumn::make('client.name')
                    ->label('Client')
                    ->placeholder('N/A')
                    ->description(function ($state, $record): ?string {
                        if (! $record instanceof VendorBalanceStatementItemRow) {
                            return null;
                        }
                        $c = $record->relationLoaded('client') ? $record->getRelation('client') : null;

                        return $c && isset($c->client_id) ? 'ID: '.$c->client_id : null;
                    })
                    ->sortable(false),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('N/A')
                    ->url(function ($state, $record): ?string {
                        if (! $record instanceof VendorBalanceStatementItemRow) {
                            return null;
                        }
                        $inv = $record->relationLoaded('invoice') ? $record->getRelation('invoice') : null;

                        return $inv instanceof Invoice ? route('invoices.show', $inv->id) : null;
                    })
                    ->color('primary')
                    ->sortable(false),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->placeholder('N/A')
                    ->sortable(false),

                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'credit' ? 'success' : 'danger')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '')
                    ->sortable(false),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, $record): string {
                        if (! $record instanceof VendorBalanceStatementItemRow) {
                            return '';
                        }
                        $type = (string) $record->transaction_type;
                        $amount = (float) $record->amount;
                        $prefix = $type === 'credit' ? '+' : '-';

                        return $prefix.number_format($amount, 2).' UGX';
                    })
                    ->color(fn ($state, $record): string => $record instanceof VendorBalanceStatementItemRow && $record->transaction_type === 'credit' ? 'success' : 'danger')
                    ->sortable(false),

                TextColumn::make('payment_method')
                    ->label('Payment method')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace('_', ' ', $state)) : 'N/A')
                    ->sortable(false),

                TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : 'N/A')
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending_payment' => 'warning',
                        default => 'danger',
                    })
                    ->sortable(false),
            ])
            ->paginated([50])
            ->defaultPaginationPageOption(50)
            ->striped();
    }

    protected function configureTransactionsTable(Table $table): Table
    {
        return $table
            ->query(
                ThirdPartyPayerBalanceHistory::query()
                    ->where('third_party_payer_id', $this->thirdPartyPayerId)
                    ->with(['invoice', 'client'])
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & time')
                    ->dateTime('M j, Y')
                    ->description(fn (ThirdPartyPayerBalanceHistory $record): string => $record->created_at->format('H:i:s'))
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Client')
                    ->placeholder('N/A')
                    ->description(fn (ThirdPartyPayerBalanceHistory $record): ?string => $record->client?->client_id
                        ? 'ID: '.$record->client->client_id
                        : null)
                    ->searchable(),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('N/A')
                    ->url(fn (ThirdPartyPayerBalanceHistory $record): ?string => $record->invoice_id
                        ? route('invoices.show', $record->invoice_id)
                        : null)
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->placeholder('N/A')
                    ->searchable(),

                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'credit' ? 'success' : 'danger')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '')
                    ->sortable(),

                TextColumn::make('change_amount')
                    ->label('Amount')
                    ->formatStateUsing(function (ThirdPartyPayerBalanceHistory $record): string {
                        $prefix = $record->transaction_type === 'credit' ? '+' : '-';

                        return $prefix.number_format(abs((float) $record->change_amount), 2).' UGX';
                    })
                    ->color(fn (ThirdPartyPayerBalanceHistory $record): string => $record->transaction_type === 'credit' ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Payment method')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace('_', ' ', $state)) : 'N/A'),

                TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : 'N/A')
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending_payment' => 'warning',
                        default => 'danger',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->striped();
    }

    public function render()
    {
        return view('livewire.third-party-vendor-balance-statement-table');
    }
}
