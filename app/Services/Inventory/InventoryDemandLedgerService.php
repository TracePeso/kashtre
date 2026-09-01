<?php

namespace App\Services\Inventory;

use App\Models\InventoryDemandLedger;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Support\Str;

class InventoryDemandLedgerService
{
    /**
     * Record clinical/user intent before stock validation.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function recordFromInvoice(Invoice $invoice, array $items = []): int
    {
        $payload = $items !== [] ? $items : (is_array($invoice->items) ? $invoice->items : []);
        $written = 0;
        $storeId = $this->resolveStoreId($invoice);

        foreach ($payload as $item) {
            $name = Str::lower(trim((string) ($item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? '')));
            if ($name === 'deposit') {
                continue;
            }

            $itemId = $item['id'] ?? $item['item_id'] ?? null;
            if (! $itemId) {
                continue;
            }

            $model = Item::query()->find($itemId);
            if (! $model || $model->type !== 'good') {
                continue;
            }

            $qty = (float) ($item['quantity'] ?? 1);
            if ($qty <= 0) {
                continue;
            }

            // Idempotent per invoice+item so early (pre-payment) capture and payment ingest do not double-count.
            $exists = InventoryDemandLedger::query()
                ->where('invoice_id', $invoice->id)
                ->where('item_id', $model->id)
                ->exists();

            if ($exists) {
                // Back-fill store_id on early captures once End Store is known.
                if ($storeId) {
                    InventoryDemandLedger::query()
                        ->where('invoice_id', $invoice->id)
                        ->where('item_id', $model->id)
                        ->whereNull('store_id')
                        ->update(['store_id' => $storeId]);
                }
                continue;
            }

            InventoryDemandLedger::query()->create([
                'business_id' => $invoice->business_id,
                'store_id' => $storeId,
                'item_id' => $model->id,
                'quantity' => $qty,
                'source' => 'invoice',
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'occurred_at' => $invoice->confirmed_at ?? now(),
                'metadata' => [
                    'invoice_number' => $invoice->invoice_number,
                    'item_name' => $model->name,
                ],
            ]);
            $written++;
        }

        return $written;
    }

    protected function resolveStoreId(Invoice $invoice): ?int
    {
        if (! empty($invoice->end_store_id)) {
            $explicit = Store::query()
                ->where('id', $invoice->end_store_id)
                ->where('business_id', $invoice->business_id)
                ->first();
            if ($explicit && $explicit->isEndStore()) {
                return (int) $explicit->id;
            }
        }

        $branchScoped = Store::query()
            ->where('business_id', $invoice->business_id)
            ->where(function ($query) {
                $query->where('distribution_type', Store::DISTRIBUTION_END)
                    ->orWhereNull('distribution_type');
            })
            ->when($invoice->branch_id, fn ($query) => $query->where('branch_id', $invoice->branch_id))
            ->orderBy('id')
            ->value('id');

        if ($branchScoped) {
            return (int) $branchScoped;
        }

        $any = Store::query()
            ->where('business_id', $invoice->business_id)
            ->where(function ($query) {
                $query->where('distribution_type', Store::DISTRIBUTION_END)
                    ->orWhereNull('distribution_type');
            })
            ->orderBy('id')
            ->value('id');

        return $any ? (int) $any : null;
    }
}
