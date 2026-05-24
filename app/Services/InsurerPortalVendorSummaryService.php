<?php

namespace App\Services;

use App\Models\Business;
use App\Models\InsuranceCompany;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use App\Support\InsurerStatementInvoiceItems;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InsurerPortalVendorSummaryService
{
    public function resolvePayer(int $businessId, int $thirdPartyVendorId): ?ThirdPartyPayer
    {
        $localInsuranceCompanyIds = InsuranceCompany::where('third_party_business_id', $thirdPartyVendorId)
            ->where('business_id', $businessId)
            ->pluck('id');

        if ($localInsuranceCompanyIds->isNotEmpty()) {
            $payer = ThirdPartyPayer::whereIn('insurance_company_id', $localInsuranceCompanyIds)
                ->where('business_id', $businessId)
                ->where('type', 'insurance_company')
                ->whereNull('client_id')
                ->first();
            if ($payer) {
                return $payer;
            }
        }

        return ThirdPartyPayer::where('insurance_company_id', $thirdPartyVendorId)
            ->where('business_id', $businessId)
            ->where('type', 'insurance_company')
            ->whereNull('client_id')
            ->first();
    }

    /**
     * @return array{payer: ?array, financial: ?array, recent_transactions: array, invoices: array, excluded_items: array}
     */
    public function buildSummaryPayload(int $businessId, int $thirdPartyVendorId): array
    {
        $business = Business::find($businessId);
        if (!$business) {
            return $this->errorSummary('Business not found.');
        }

        $thirdPartyPayer = $this->resolvePayer($businessId, $thirdPartyVendorId);

        $localInsuranceCompanyIds = InsuranceCompany::where('third_party_business_id', $thirdPartyVendorId)
            ->where('business_id', $businessId)
            ->pluck('id');

        $vendorStub = $this->vendorStub($thirdPartyVendorId, $businessId, $thirdPartyPayer);

        if (!$thirdPartyPayer) {
            return [
                'payer' => null,
                'financial' => null,
                'recent_transactions' => [],
                'invoices' => [],
                'excluded_items' => [],
                'business' => $this->serializeBusinessCreditLimit($business),
                'vendor_stub' => $vendorStub,
                'message' => 'No third-party payer account found for this vendor. Balance history will appear once invoices are created with this vendor.',
            ];
        }

        $balanceSummary = app(AccountBalanceSummaryService::class)->forThirdPartyPayer($thirdPartyPayer);
        $chronological = app(ThirdPartyPayerChronologicalPaymentService::class);
        $outstandingEntries = $chronological->previewAllocation($thirdPartyPayer, 0)['entries'];

        $recent = ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
            ->with(['invoice', 'client', 'business', 'branch', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $invoices = $this->buildInvoicesForVendor(
            $businessId,
            $thirdPartyVendorId,
            $thirdPartyPayer,
            $localInsuranceCompanyIds,
            $vendorStub
        );

        $excludedItemIds = (array) ($thirdPartyPayer->excluded_items ?? []);
        $excludedItems = collect();
        if (!empty($excludedItemIds)) {
            $excludedItems = Item::where('business_id', $businessId)
                ->whereIn('id', $excludedItemIds)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'type']);
        }

        $effectiveCreditLimit = (($thirdPartyPayer->credit_limit ?? 0) > 0)
            ? (float) $thirdPartyPayer->credit_limit
            : (float) ($business->max_third_party_credit_limit ?? 0);

        return [
            'payer' => $this->serializePayer($thirdPartyPayer),
            'financial' => [
                'total_credits' => $balanceSummary['total_credits'],
                'total_debits' => $balanceSummary['total_debits'],
                'available_balance' => $balanceSummary['available_balance'],
                'total_balance' => $balanceSummary['total_balance'],
                'suspense_balance' => $balanceSummary['suspense_balance'],
                'current_balance' => $balanceSummary['available_balance'],
                'ledger_balance' => $balanceSummary['ledger_balance'],
                'total_outstanding' => $chronological->totalOutstanding($thirdPartyPayer),
                'outstanding_entries' => $outstandingEntries,
            ],
            'recent_transactions' => $recent->map(fn ($h) => $this->serializeBalanceHistory($h))->values()->all(),
            'invoices' => $invoices->values()->all(),
            'excluded_items' => $excludedItems->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'code' => $i->code,
                'type' => $i->type,
            ])->values()->all(),
            'business' => $this->serializeBusinessCreditLimit($business),
            'vendor_stub' => $vendorStub,
            'message' => null,
        ];
    }

    public function paginatedBalanceHistory(int $businessId, int $thirdPartyVendorId, int $perPage = 50): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $payer = $this->resolvePayer($businessId, $thirdPartyVendorId);
        if (!$payer) {
            return new LengthAwarePaginator([], 0, $perPage, 1);
        }

        return ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $payer->id)
            ->with(['invoice', 'client', 'business', 'branch', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * @return array{rows: array, pagination: array<string, mixed>}
     */
    public function formatBalanceHistoryPage(LengthAwarePaginator $paginator): array
    {
        return [
            'rows' => $paginator->getCollection()
                ->map(fn (ThirdPartyPayerBalanceHistory $h) => $this->serializeBalanceHistory($h))
                ->values()
                ->all(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array{success: false, message: string}
     */
    protected function errorSummary(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
        ];
    }

    protected function vendorStub(int $thirdPartyVendorId, int $businessId, ?ThirdPartyPayer $payer): array
    {
        $ic = InsuranceCompany::where('third_party_business_id', $thirdPartyVendorId)
            ->where('business_id', $businessId)
            ->first();

        return [
            'id' => $thirdPartyVendorId,
            'name' => $ic->name ?? $payer?->name ?? '',
            'code' => $ic->code ?? '',
        ];
    }

    protected function serializeBusinessCreditLimit(Business $business): array
    {
        return [
            'id' => $business->id,
            'max_third_party_credit_limit' => (float) ($business->max_third_party_credit_limit ?? 0),
        ];
    }

    protected function serializePayer(ThirdPartyPayer $payer): array
    {
        return [
            'id' => $payer->id,
            'status' => $payer->status,
            'credit_limit' => $payer->credit_limit !== null ? (float) $payer->credit_limit : null,
            'name' => $payer->name,
        ];
    }

    protected function serializeBalanceHistory(ThirdPartyPayerBalanceHistory $history): array
    {
        $invoice = $history->invoice;
        $payer = $history->relationLoaded('thirdPartyPayer')
            ? $history->thirdPartyPayer
            : ThirdPartyPayer::find($history->third_party_payer_id);

        $statementItems = ($invoice && $payer)
            ? InsurerStatementInvoiceItems::linesPayableByPayer($invoice, $payer)
            : (is_array($invoice?->items) ? $invoice->items : []);

        return [
            'id' => $history->id,
            'created_at' => $history->created_at?->toIso8601String(),
            'description' => $history->description,
            'client' => $history->client ? [
                'name' => $history->client->name,
                'client_id' => $history->client->client_id,
            ] : null,
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'items' => $statementItems,
            ] : null,
            'transaction_type' => $history->transaction_type,
            'change_amount' => (float) $history->change_amount,
            'new_balance' => (float) $history->new_balance,
            'payment_method' => $history->payment_method,
            'payment_status' => $history->payment_status,
        ];
    }

    protected function buildInvoicesForVendor(
        int $businessId,
        int $thirdPartyVendorId,
        ThirdPartyPayer $thirdPartyPayer,
        Collection $localInsuranceCompanyIds,
        array $vendorStub
    ): Collection {
        $invoices = collect();

        $vendorNameNormalized = strtolower(trim((string) ($thirdPartyPayer->name ?? $vendorStub['name'] ?? '')));
        $localInsuranceIdStrings = $localInsuranceCompanyIds->map(fn ($id) => (string) $id)->all();
        $vendorIdString = (string) $thirdPartyVendorId;

        $candidateInvoices = Invoice::where('business_id', $businessId)
            ->whereNull('parent_invoice_id')
            ->whereNotNull('insurance_authorization_snapshot')
            ->with(['client', 'business', 'branch'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($candidateInvoices as $invoiceModel) {
            $snapshot = is_array($invoiceModel->insurance_authorization_snapshot)
                ? $invoiceModel->insurance_authorization_snapshot
                : json_decode($invoiceModel->insurance_authorization_snapshot ?? '[]', true);

            $vendorsSnap = is_array($snapshot['vendors'] ?? null) ? $snapshot['vendors'] : [];
            if (empty($vendorsSnap)) {
                continue;
            }

            $matchedVendorSnap = null;
            foreach ($vendorsSnap as $vSnap) {
                $snapVendorName = strtolower(trim((string) ($vSnap['vendor_name'] ?? $vSnap['insurance_company_name'] ?? '')));
                $snapVendorId = (string) ($vSnap['vendor_id'] ?? '');
                $snapInsuranceCompanyId = (string) ($vSnap['insurance_company_id'] ?? '');

                $matchesByName = $vendorNameNormalized !== '' && $snapVendorName === $vendorNameNormalized;
                $matchesByThirdPartyId = $snapVendorId !== '' && $snapVendorId === $vendorIdString;
                $matchesByLocalInsuranceId = $snapInsuranceCompanyId !== '' && in_array($snapInsuranceCompanyId, $localInsuranceIdStrings, true);

                if ($matchesByName || $matchesByThirdPartyId || $matchesByLocalInsuranceId) {
                    $matchedVendorSnap = $vSnap;
                    break;
                }
            }

            if (!$matchedVendorSnap) {
                continue;
            }

            $invoiceLedgerEntries = ThirdPartyPayerBalanceHistory::where('invoice_id', $invoiceModel->id)
                ->where('third_party_payer_id', $thirdPartyPayer->id)
                ->get();

            $debits = abs($invoiceLedgerEntries->where('transaction_type', 'debit')->sum('change_amount'));
            $credits = $invoiceLedgerEntries->where('transaction_type', 'credit')->sum('change_amount');

            $snapClient = (float) ($matchedVendorSnap['client_portion_allocated']
                ?? $matchedVendorSnap['client_total']
                ?? $matchedVendorSnap['client_portion']
                ?? 0);
            $snapInsurance = (float) ($matchedVendorSnap['insurance_total']
                ?? $matchedVendorSnap['insurance_portion']
                ?? 0);
            $snapshotTotal = round($snapClient + $snapInsurance, 2);

            $totalAmount = $debits > 0 ? $debits : $snapshotTotal;
            $amountPaid = $credits;
            $balanceDue = max(0, $totalAmount - $amountPaid);

            if ($balanceDue <= 0 && $amountPaid > 0) {
                $paymentStatus = 'paid';
            } elseif ($amountPaid > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'pending_payment';
            }

            $invoices->push([
                'id' => $invoiceModel->id,
                'invoice_number' => $invoiceModel->invoice_number ?? 'N/A',
                'client_name' => $invoiceModel->client?->name ?? $invoiceModel->client_name ?? 'N/A',
                'client_id' => $invoiceModel->client?->client_id,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
                'status' => $invoiceModel->status ?? 'confirmed',
                'created_at' => $invoiceModel->created_at?->toIso8601String(),
                'business_name' => $invoiceModel->business?->name,
                'branch_name' => $invoiceModel->branch?->name,
            ]);
        }

        return $invoices;
    }
}
