<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use App\Support\InsurerCascadeLineAllocations;
use Illuminate\Support\Facades\Log;

/**
 * Posts insurer guarantee debits to third-party payer ledgers (AP / vendor statements).
 */
class ThirdPartyInsuranceVendorLedgerService
{
    /**
     * Itemized cascade debits when cascade_line_items exist; otherwise no-op (caller uses aggregate posting).
     */
    public function postInsuranceVendorDebits(Invoice $invoice, int $businessId, ?int $clientId = null): bool
    {
        if (! InsurerCascadeLineAllocations::invoiceUsesCascadeLineItems($invoice)) {
            return false;
        }

        $clientId = $clientId ?? $invoice->client_id;

        $lumpDescription = 'Insurance guarantee for invoice '.$invoice->invoice_number;
        ThirdPartyPayerBalanceHistory::query()
            ->where('invoice_id', $invoice->id)
            ->where('transaction_type', 'debit')
            ->where('description', $lumpDescription)
            ->delete();

        $rows = InsurerCascadeLineAllocations::vendorDebitRowsForInvoice($invoice);

        foreach ($rows as $row) {
            $insurer = trim((string) ($row['insurer'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            $description = trim((string) ($row['description'] ?? ''));

            if ($insurer === '' || $amount <= 0.001 || $description === '') {
                continue;
            }

            $payer = ThirdPartyPayer::where('name', $insurer)
                ->where('business_id', $businessId)
                ->where('type', 'insurance_company')
                ->whereNull('client_id')
                ->first();

            if (! $payer) {
                Log::warning('[Kashtre] Third-party payer not found for cascade vendor debit', [
                    'invoice_id' => $invoice->id,
                    'insurer' => $insurer,
                    'amount' => $amount,
                ]);

                continue;
            }

            ThirdPartyPayerBalanceHistory::recordDebit(
                $payer,
                $amount,
                $description,
                $invoice->invoice_number,
                'Insurance guarantee for invoice '.$invoice->invoice_number,
                'insurance',
                $invoice->id,
                $clientId
            );
        }

        return true;
    }
}
