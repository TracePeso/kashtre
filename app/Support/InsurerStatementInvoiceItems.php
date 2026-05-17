<?php

namespace App\Support;

use App\Models\BalanceHistory;
use App\Models\Invoice;
use App\Models\ThirdPartyPayer;
use Illuminate\Support\Collection;

/**
 * Invoice lines attributable to a specific insurer payer for portal / AP itemized statements.
 * Aligns with cascade primary-insurer assignment (see InvoiceController::buildCascadeLineItemRowsForSnapshot).
 */
final class InsurerStatementInvoiceItems
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function linesPayableByPayer(Invoice $invoice, ThirdPartyPayer $payer): array
    {
        $items = is_array($invoice->items) ? $invoice->items : [];
        if ($items === []) {
            return [];
        }

        $snapshot = is_array($invoice->insurance_authorization_snapshot)
            ? $invoice->insurance_authorization_snapshot
            : json_decode((string) ($invoice->insurance_authorization_snapshot ?? ''), true);
        if (! is_array($snapshot)) {
            $snapshot = [];
        }

        $baseLines = collect($items)
            ->filter(fn ($line) => is_array($line) && empty($line['kashtre_excluded']))
            ->values();

        if ($baseLines->isEmpty()) {
            return [];
        }

        if (InsurerCascadeLineAllocations::invoiceUsesCascadeLineItems($invoice)) {
            $cascadeLines = InsurerCascadeLineAllocations::payableLinesForPayer($invoice, $payer);
            if ($cascadeLines !== []) {
                return array_map(fn (array $row) => $row['line'], $cascadeLines);
            }
        }

        if (! empty($snapshot['multi_vendor']) && ! empty($snapshot['vendors']) && is_array($snapshot['vendors'])) {
            return self::linesForMultiVendorSnapshot($baseLines, $snapshot['vendors'], $payer);
        }

        if ((float) ($snapshot['insurance_total'] ?? 0) > 0.001) {
            return self::linesForSingleVendorSnapshot($baseLines, $snapshot, $invoice, $payer);
        }

        return $baseLines->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $baseLines
     * @return array<int, array<string, mixed>>
     */
    private static function linesForSingleVendorSnapshot(Collection $baseLines, array $snapshot, Invoice $invoice, ThirdPartyPayer $payer): array
    {
        if (! self::payerMatchesLegacyClientInsurer($invoice, $payer)) {
            return [];
        }

        $excludedRows = is_array(data_get($snapshot, 'breakdown.excluded_items'))
            ? data_get($snapshot, 'breakdown.excluded_items')
            : [];

        return $baseLines
            ->filter(fn (array $line) => ! BalanceHistory::invoiceRowExcludedByInsuranceBreakdown($line, $excludedRows))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $baseLines
     * @param  array<int, mixed>  $vendors
     * @return array<int, array<string, mixed>>
     */
    private static function linesForMultiVendorSnapshot(Collection $baseLines, array $vendors, ThirdPartyPayer $payer): array
    {
        $eligible = collect($vendors)
            ->filter(fn ($v) => is_array($v) && self::vendorRowEligibleForCascade($v))
            ->sortBy(fn ($v) => (float) ($v['priority'] ?? 0))
            ->values()
            ->all();

        if ($eligible === []) {
            return [];
        }

        $targetIdx = null;
        foreach ($eligible as $idx => $vSnap) {
            if (self::payerMatchesVendorRow($payer, $vSnap)) {
                $targetIdx = $idx;
                break;
            }
        }

        if ($targetIdx === null) {
            return [];
        }

        $out = [];
        foreach ($baseLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $primaryIdx = self::primaryVendorIndexForLine($line, $eligible);
            if ($primaryIdx !== null && $primaryIdx === $targetIdx) {
                $out[] = $line;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $eligible
     */
    private static function primaryVendorIndexForLine(array $line, array $eligible): ?int
    {
        foreach ($eligible as $idx => $v) {
            $breakdown = is_array($v['breakdown'] ?? null) ? $v['breakdown'] : [];
            $excludedItems = is_array($breakdown['excluded_items'] ?? null) ? $breakdown['excluded_items'] : [];
            if (BalanceHistory::invoiceRowExcludedByInsuranceBreakdown($line, $excludedItems)) {
                continue;
            }

            return $idx;
        }

        return null;
    }

    private static function payerMatchesVendorRow(ThirdPartyPayer $payer, array $vSnap): bool
    {
        $snapVendorId = (string) ($vSnap['vendor_id'] ?? '');
        if ($snapVendorId !== '' && (string) $payer->id === $snapVendorId) {
            return true;
        }

        $payerName = mb_strtolower(trim((string) ($payer->name ?? '')));
        $snapName = mb_strtolower(trim((string) ($vSnap['vendor_name'] ?? $vSnap['insurance_company_name'] ?? '')));
        if ($payerName !== '' && $snapName !== '' && $payerName === $snapName) {
            return true;
        }

        $snapIc = (string) ($vSnap['insurance_company_id'] ?? '');
        if ($snapIc !== '' && $payer->insurance_company_id && $snapIc === (string) $payer->insurance_company_id) {
            return true;
        }

        return false;
    }

    private static function payerMatchesLegacyClientInsurer(Invoice $invoice, ThirdPartyPayer $payer): bool
    {
        $client = $invoice->client;
        if (! $client) {
            return true;
        }

        if (! $payer->insurance_company_id || ! $client->insurance_company_id) {
            return true;
        }

        return (int) $payer->insurance_company_id === (int) $client->insurance_company_id;
    }

    private static function vendorRowEligibleForCascade(array $v): bool
    {
        $status = strtolower((string) ($v['authorization_status'] ?? ''));

        return ! in_array($status, ['failed', 'skipped'], true);
    }
}
