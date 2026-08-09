<?php

namespace App\Services\Inventory;

use App\Models\InventoryDemandLedger;
use App\Models\Invoice;
use App\Models\Item;
use Illuminate\Support\Str;

class InventoryDemandLedgerService
{
    /**
     * Record clinical/user intent before stock validation (SRD A-02).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function recordFromInvoice(Invoice $invoice, array $items = []): int
    {
        $payload = $items !== [] ? $items : (is_array($invoice->items) ? $invoice->items : []);
        $written = 0;

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
                continue;
            }

            InventoryDemandLedger::query()->create([
                'business_id' => $invoice->business_id,
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
}
