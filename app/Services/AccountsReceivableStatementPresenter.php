<?php

namespace App\Services;

use App\Models\AccountsReceivable;
use Illuminate\Support\Collection;

/**
 * Line-level breakdown of outstanding AR for itemized statement views.
 */
class AccountsReceivableStatementPresenter
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function linesForReceivable(AccountsReceivable $ar): array
    {
        $balance = (float) $ar->balance;
        $invoice = $ar->invoice;
        $items = is_array($invoice?->items) ? $invoice->items : [];

        if ($invoice === null || $items === []) {
            return [[
                'ar_id' => $ar->id,
                'business' => $ar->business,
                'client' => $ar->client,
                'invoice' => null,
                'item_name' => 'Receivable (no invoice lines)',
                'qty' => null,
                'allocated_balance' => round($balance, 2),
                'invoice_date' => $ar->invoice_date,
                'due_date' => $ar->due_date,
                'days_past_due' => $ar->days_past_due,
                'status' => $ar->status,
                'aging_bucket' => $ar->aging_bucket,
            ]];
        }

        $subtotal = self::invoiceItemsSubtotal($items);
        $lines = [];

        foreach ($items as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lineTotal = self::lineAmount($line);
            $share = $subtotal > 0 ? ($lineTotal / $subtotal) : (1 / max(1, count($items)));
            $name = trim((string) ($line['name'] ?? $line['displayName'] ?? ''));
            if ($name === '') {
                $name = 'Line item';
            }

            $qtyRaw = $line['quantity'] ?? 1;
            $qty = is_numeric($qtyRaw) ? (float) $qtyRaw : 1;

            $lines[] = [
                'ar_id' => $ar->id,
                'business' => $ar->business,
                'client' => $ar->client,
                'invoice' => $invoice,
                'item_name' => $name,
                'qty' => $qty,
                'allocated_balance' => round($share * $balance, 2),
                'invoice_date' => $ar->invoice_date,
                'due_date' => $ar->due_date,
                'days_past_due' => $ar->days_past_due,
                'status' => $ar->status,
                'aging_bucket' => $ar->aging_bucket,
            ];
        }

        return $lines !== [] ? $lines : [[
            'ar_id' => $ar->id,
            'business' => $ar->business,
            'client' => $ar->client,
            'invoice' => $invoice,
            'item_name' => 'Invoice total',
            'qty' => null,
            'allocated_balance' => round($balance, 2),
            'invoice_date' => $ar->invoice_date,
            'due_date' => $ar->due_date,
            'days_past_due' => $ar->days_past_due,
            'status' => $ar->status,
            'aging_bucket' => $ar->aging_bucket,
        ]];
    }

    /**
     * @param  Collection<int, AccountsReceivable>  $receivables
     */
    public static function flattenedLines(Collection $receivables): Collection
    {
        return $receivables->flatMap(fn (AccountsReceivable $ar) => self::linesForReceivable($ar));
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private static function invoiceItemsSubtotal(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sum += self::lineAmount($line);
        }

        return max($sum, 0.0);
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
}
