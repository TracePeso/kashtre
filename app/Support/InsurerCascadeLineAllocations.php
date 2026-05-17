<?php

namespace App\Support;

use App\Models\BalanceHistory;
use App\Models\Invoice;
use App\Models\ThirdPartyPayer;
use Illuminate\Support\Collection;

/**
 * Per-insurer, per-line payable amounts from authorization cascade_line_items.
 */
final class InsurerCascadeLineAllocations
{
    /**
     * @return array<int, array{line: array<string, mixed>, amount: float}>
     */
    public static function payableLinesForPayer(Invoice $invoice, ThirdPartyPayer $payer): array
    {
        $snapshot = self::authorizationSnapshot($invoice);
        if ($snapshot === null) {
            return [];
        }

        $cascadeLines = $snapshot['cascade_line_items'] ?? null;
        if (! is_array($cascadeLines) || $cascadeLines === []) {
            return [];
        }

        $payerKey = self::normalizeName((string) ($payer->name ?? ''));
        if ($payerKey === '') {
            return [];
        }

        $baseLines = collect(is_array($invoice->items) ? $invoice->items : [])
            ->filter(fn ($line) => is_array($line) && empty($line['kashtre_excluded']))
            ->values();

        $out = [];
        foreach ($baseLines as $line) {
            $cascade = self::matchCascadeRow($line, $cascadeLines);
            if ($cascade === null) {
                continue;
            }

            $name = trim((string) ($line['name'] ?? $line['displayName'] ?? ''));
            $amount = self::payableAmountForPayerOnCascadeRow($payerKey, $cascade, $line);
            if ($amount <= 0.001) {
                continue;
            }

            $out[] = [
                'line' => array_merge($line, ['total_amount' => round($amount, 2)]),
                'amount' => round($amount, 2),
            ];
        }

        $scVendor = trim((string) ($snapshot['service_charge_vendor_name'] ?? ''));
        $serviceCharge = (float) ($invoice->service_charge ?? 0);
        if ($serviceCharge > 0.001 && $scVendor !== '' && self::normalizeName($scVendor) === $payerKey) {
            $out[] = [
                'line' => [
                    'name' => 'Service Fee',
                    'displayName' => 'Service Fee',
                    'quantity' => 1,
                    'total_amount' => round($serviceCharge, 2),
                    'is_service_fee' => true,
                ],
                'amount' => round($serviceCharge, 2),
            ];
        }

        return $out;
    }

    /**
     * Ledger debit rows to post for each insurer (itemized descriptions).
     *
     * @return array<int, array{insurer: string, amount: float, description: string}>
     */
    public static function vendorDebitRowsForInvoice(Invoice $invoice): array
    {
        $items = is_array($invoice->items) ? $invoice->items : [];
        if ($items === []) {
            return [];
        }

        $rows = [];
        foreach ($items as $itemData) {
            if (! is_array($itemData) || ! empty($itemData['kashtre_excluded'])) {
                continue;
            }

            $name = trim((string) ($itemData['name'] ?? $itemData['displayName'] ?? ''));
            if ($name === '') {
                continue;
            }

            $qty = (float) ($itemData['quantity'] ?? 1);
            if ($qty <= 0) {
                $qty = 1;
            }
            $qtyDisp = ($qty != floor($qty)) ? $qty : (int) $qty;
            $description = $qtyDisp > 1 ? "{$name} (×{$qtyDisp})" : $name;

            $splits = BalanceHistory::insurancePayerSplitsForInvoiceItem($invoice, $name, $itemData);
            if (count($splits) > 1) {
                foreach ($splits as $split) {
                    $amt = (float) ($split['amount'] ?? 0);
                    $insurer = trim((string) ($split['insurer'] ?? ''));
                    if ($insurer === '' || $amt <= 0.001) {
                        continue;
                    }
                    $rows[] = [
                        'insurer' => $insurer,
                        'amount' => round($amt, 2),
                        'description' => $description,
                    ];
                }

                continue;
            }

            $snapshot = self::authorizationSnapshot($invoice);
            $cascadeLines = is_array($snapshot) ? ($snapshot['cascade_line_items'] ?? null) : null;
            if (is_array($cascadeLines) && $cascadeLines !== []) {
                $cascade = self::matchCascadeRow($itemData, $cascadeLines);
                if ($cascade !== null) {
                    $primary = trim((string) ($cascade['primary_insurer'] ?? ''));
                    $primaryAmt = (float) ($cascade['covered_amount'] ?? 0);
                    if ($primaryAmt <= 0.001) {
                        $lineTotal = (float) ($itemData['total_amount'] ?? 0);
                        $secondaryAmt = (float) ($cascade['secondary_covered_amount'] ?? 0);
                        $primaryAmt = max(0, round($lineTotal - $secondaryAmt, 2));
                    }
                    if ($primary !== '' && $primaryAmt > 0.001) {
                        $rows[] = [
                            'insurer' => $primary,
                            'amount' => round($primaryAmt, 2),
                            'description' => $description,
                        ];
                    }

                    continue;
                }
            }

            $lineTotal = (float) ($itemData['total_amount'] ?? 0);
            if ($lineTotal <= 0.001) {
                continue;
            }

            $primary = '';
            if (is_array($cascadeLines) && $cascadeLines !== []) {
                $cascade = self::matchCascadeRow($itemData, $cascadeLines);
                $primary = trim((string) ($cascade['primary_insurer'] ?? ''));
            }

            if ($primary === '') {
                continue;
            }

            $rows[] = [
                'insurer' => $primary,
                'amount' => round($lineTotal, 2),
                'description' => $description,
            ];
        }

        $snapshot = self::authorizationSnapshot($invoice);
        if (is_array($snapshot)) {
            $scVendor = trim((string) ($snapshot['service_charge_vendor_name'] ?? ''));
            $serviceCharge = (float) ($invoice->service_charge ?? 0);
            if ($scVendor !== '' && $serviceCharge > 0.001) {
                $rows[] = [
                    'insurer' => $scVendor,
                    'amount' => round($serviceCharge, 2),
                    'description' => 'Service Fee',
                ];
            }
        }

        return $rows;
    }

    public static function invoiceUsesCascadeLineItems(Invoice $invoice): bool
    {
        $snapshot = self::authorizationSnapshot($invoice);

        return is_array($snapshot)
            && ! empty($snapshot['cascade_line_items'])
            && is_array($snapshot['cascade_line_items']);
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, mixed>  $cascadeLines
     * @return array<string, mixed>|null
     */
    public static function matchCascadeRow(array $line, array $cascadeLines): ?array
    {
        $targetName = mb_strtolower(trim((string) ($line['name'] ?? $line['displayName'] ?? '')));
        $itemCode = trim((string) ($line['code'] ?? ''));

        foreach ($cascadeLines as $cascade) {
            if (! is_array($cascade)) {
                continue;
            }
            $lineName = mb_strtolower(trim((string) ($cascade['name'] ?? '')));
            $lineCode = trim((string) ($cascade['code'] ?? ''));

            $matches = ($targetName !== '' && $lineName !== '' && $targetName === $lineName)
                || ($itemCode !== '' && $lineCode !== '' && strcasecmp($itemCode, $lineCode) === 0);

            if ($matches) {
                return $cascade;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cascade
     * @param  array<string, mixed>  $line
     */
    private static function payableAmountForPayerOnCascadeRow(string $payerKey, array $cascade, array $line): float
    {
        $primaryKey = self::normalizeName((string) ($cascade['primary_insurer'] ?? ''));
        $secondaryKey = self::normalizeName((string) ($cascade['secondary_insurer'] ?? ''));
        $secondaryAmt = (float) ($cascade['secondary_covered_amount'] ?? 0);

        if ($payerKey === $primaryKey && $primaryKey !== '') {
            $primaryAmt = (float) ($cascade['covered_amount'] ?? 0);
            if ($primaryAmt <= 0.001) {
                $lineTotal = (float) ($line['total_amount'] ?? 0);
                $primaryAmt = max(0, round($lineTotal - $secondaryAmt, 2));
            }

            return $primaryAmt;
        }

        if ($secondaryAmt > 0.001 && $payerKey === $secondaryKey) {
            return $secondaryAmt;
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function authorizationSnapshot(Invoice $invoice): ?array
    {
        $snapshot = $invoice->insurance_authorization_snapshot;
        if (is_array($snapshot)) {
            return $snapshot;
        }

        if (is_string($snapshot) && $snapshot !== '') {
            $decoded = json_decode($snapshot, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private static function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

}
