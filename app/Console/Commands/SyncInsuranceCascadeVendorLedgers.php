<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\ThirdPartyInsuranceVendorLedgerService;
use App\Support\InsurerCascadeLineAllocations;
use Illuminate\Console\Command;

class SyncInsuranceCascadeVendorLedgers extends Command
{
    protected $signature = 'insurance:sync-vendor-cascade-ledgers
                            {invoice? : Invoice number (e.g. P2026050001) or numeric id}
                            {--all : Rebuild cascade vendor debits for all matching invoices}';

    protected $description = 'Replace lump-sum insurer guarantee rows with per-line cascade vendor debits (50/50 splits, etc.)';

    public function handle(ThirdPartyInsuranceVendorLedgerService $ledgerService): int
    {
        $query = Invoice::query()
            ->whereNull('parent_invoice_id')
            ->whereNotNull('insurance_authorization_snapshot');

        if ($this->option('all')) {
            $invoices = $query->orderByDesc('id')->get();
        } else {
            $key = (string) $this->argument('invoice');
            if ($key === '') {
                $this->error('Provide an invoice number/id or use --all.');

                return self::FAILURE;
            }

            $invoices = $query
                ->where(function ($q) use ($key) {
                    $q->where('invoice_number', $key);
                    if (ctype_digit($key)) {
                        $q->orWhere('id', (int) $key);
                    }
                })
                ->get();
        }

        if ($invoices->isEmpty()) {
            $this->warn('No invoices found.');

            return self::FAILURE;
        }

        $synced = 0;
        foreach ($invoices as $invoice) {
            if (! InsurerCascadeLineAllocations::invoiceUsesCascadeLineItems($invoice)) {
                $this->line("Skip {$invoice->invoice_number}: no cascade_line_items");

                continue;
            }

            $ledgerService->postInsuranceVendorDebits(
                $invoice,
                (int) $invoice->business_id,
                (int) $invoice->client_id
            );
            $synced++;
            $this->info("Synced vendor cascade ledgers for {$invoice->invoice_number}");
        }

        $this->info("Done. {$synced} invoice(s) updated.");

        return self::SUCCESS;
    }
}
