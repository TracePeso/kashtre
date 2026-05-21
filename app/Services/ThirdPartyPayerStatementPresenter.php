<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use App\Support\InsurerStatementInvoiceItems;
use Illuminate\Support\Collection;

/**
 * Builds item-oriented rows from payer ledger lines for AP / vendor statements.
 */
class ThirdPartyPayerStatementPresenter
{
    /**
     * Map ledger rows to display rows. Debits tied to an invoice with line items are expanded
     * to one row per item with amounts allocated by line total (same idea as AR itemization).
     * Credits stay one row each with a readable label.
     *
     * @param  Collection<int, ThirdPartyPayerBalanceHistory>  $histories
     * @return Collection<int, array<string, mixed>>
     */
    public static function rowsFromHistories(Collection $histories): Collection
    {
        return $histories->flatMap(fn (ThirdPartyPayerBalanceHistory $h) => self::rowsForHistory($h));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function rowsForHistory(ThirdPartyPayerBalanceHistory $h): Collection
    {
        $invoice = $h->invoice;
        $h->loadMissing('thirdPartyPayer');
        $payer = $h->thirdPartyPayer;
        $lines = self::normalizedInvoiceLines($invoice, $payer);

        if ($h->transaction_type === 'debit' && $lines->isNotEmpty()) {
            $totalDebit = abs((float) $h->change_amount);
            $matched = self::matchInvoiceItemLabel((string) ($h->description ?? ''), $invoice);
            if ($matched !== null) {
                return collect([self::buildRow($h, $matched, $totalDebit, $h->description)]);
            }

            $subtotal = $lines->sum(fn (array $line) => self::lineAmount($line));

            if ($totalDebit > 0 && $subtotal > 0 && abs($subtotal - $totalDebit) > 0.02) {
                return self::expandedDebitRows($h, $lines, $totalDebit, $subtotal);
            }

            if ($totalDebit > 0 && $lines->count() === 1) {
                $line = $lines->first();
                $name = trim((string) ($line['name'] ?? $line['displayName'] ?? 'Line item'));
                $qty = (float) ($line['quantity'] ?? 1);
                $qtyDisp = ($qty != floor($qty)) ? $qty : (int) $qty;
                $label = $qtyDisp > 1 ? "{$name} (×{$qtyDisp})" : $name;

                return collect([self::buildRow($h, $label, $totalDebit, $h->description)]);
            }
        }

        return collect([self::singleLedgerRow($h)]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return Collection<int, array<string, mixed>>
     */
    private static function expandedDebitRows(
        ThirdPartyPayerBalanceHistory $h,
        Collection $lines,
        float $totalDebit,
        float $subtotal
    ): Collection {
        $count = $lines->count();
        $rows = collect();
        $remaining = $totalDebit;

        foreach ($lines->values() as $idx => $line) {
            $share = self::lineAmount($line) / $subtotal;
            $isLast = ($idx === $count - 1);
            $portion = $isLast ? max(0, round($remaining, 2)) : round($share * $totalDebit, 2);
            $remaining -= $portion;

            $name = trim((string) ($line['name'] ?? $line['displayName'] ?? ''));
            if ($name === '') {
                $name = 'Line item';
            }
            $qty = (float) ($line['quantity'] ?? 1);
            $qtyDisp = ($qty != floor($qty)) ? $qty : (int) $qty;
            $label = $qtyDisp > 1 ? "{$name} (×{$qtyDisp})" : $name;

            $parentDesc = trim((string) ($h->description ?? ''));
            $detail = ($count > 1 && $parentDesc !== '' && stripos($parentDesc, $name) === false)
                ? $parentDesc
                : ($h->description ?? null);

            $rows->push(self::buildRow($h, $label, max(0.0, $portion), $detail));
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function singleLedgerRow(ThirdPartyPayerBalanceHistory $h): array
    {
        $invoice = $h->invoice;
        $desc = (string) ($h->description ?? '');
        $matched = self::matchInvoiceItemLabel($desc, $invoice);
        $lineLabel = $matched ?? self::fallbackLineLabel($invoice, $desc);

        return self::buildRow(
            $h,
            $lineLabel !== '' ? $lineLabel : '—',
            abs((float) $h->change_amount),
            $h->description
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildRow(
        ThirdPartyPayerBalanceHistory $h,
        string $lineLabel,
        float $amount,
        ?string $detailDescription
    ): array {
        $invoice = $h->invoice;

        return [
            'history_id' => $h->id,
            'created_at' => $h->created_at,
            'line_label' => $lineLabel,
            'detail_description' => $detailDescription,
            'client' => $h->client ?? $invoice?->client,
            'invoice' => $invoice,
            'reference' => $h->reference_number ?: $invoice?->invoice_number,
            'invoice_id' => $h->invoice_id,
            'transaction_type' => $h->transaction_type,
            'amount' => $amount,
            'payment_method' => $h->payment_method,
            'payment_status' => $h->payment_status,
        ];
    }

    private static function normalizedInvoiceLines(?Invoice $invoice, ?ThirdPartyPayer $payer = null): Collection
    {
        if ($invoice === null) {
            return collect();
        }

        if ($payer !== null) {
            $items = InsurerStatementInvoiceItems::linesPayableByPayer($invoice, $payer);
        } else {
            $items = $invoice->items ?? [];
        }

        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->filter(fn ($line) => is_array($line))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function lineAmount(array $line): float
    {
        if (isset($line['total_amount']) && is_numeric($line['total_amount'])) {
            return max(0.0, (float) $line['total_amount']);
        }

        $price = isset($line['price']) && is_numeric($line['price']) ? (float) $line['price'] : 0.0;
        $qty = isset($line['quantity']) && is_numeric($line['quantity']) ? (float) $line['quantity'] : 1.0;

        return max(0.0, $price * $qty);
    }

    public static function matchInvoiceItemLabel(?string $description, ?Invoice $invoice): ?string
    {
        if ($invoice === null || $description === null || trim($description) === '') {
            return null;
        }

        $items = $invoice->items ?? [];
        if (! is_array($items) || $items === []) {
            return null;
        }

        foreach ($items as $line) {
            if (! is_array($line)) {
                continue;
            }
            $name = trim((string) ($line['name'] ?? $line['displayName'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (stripos($description, $name) !== false) {
                $qty = (float) ($line['quantity'] ?? 1);
                $qtyDisp = ($qty != floor($qty)) ? $qty : (int) $qty;

                return $qtyDisp > 1 ? "{$name} (×{$qtyDisp})" : $name;
            }
        }

        return null;
    }

    private static function fallbackLineLabel(?Invoice $invoice, string $description): string
    {
        if ($description !== '') {
            return $description;
        }

        $items = $invoice?->items ?? [];
        if (! is_array($items) || $items === []) {
            return '';
        }

        if (count($items) === 1 && is_array($items[0])) {
            $name = trim((string) ($items[0]['name'] ?? $items[0]['displayName'] ?? ''));

            return $name !== '' ? $name : '';
        }

        $names = collect($items)
            ->filter(fn ($line) => is_array($line))
            ->map(fn ($line) => trim((string) ($line['name'] ?? $line['displayName'] ?? '')))
            ->filter()
            ->take(3)
            ->implode(', ');

        return $names !== '' ? $names.' (itemized)' : '';
    }
}
