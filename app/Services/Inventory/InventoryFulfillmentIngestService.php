<?php

namespace App\Services\Inventory;

use App\Models\ClientSpaceStoreAssignment;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryModuleConfig;
use App\Models\Invoice;
use App\Models\Item;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InventoryFulfillmentIngestService
{
    public function __construct(
        private readonly InventoryDemandLedgerService $demandLedger,
    ) {}

    /**
     * Stamp paid goods onto the mapped End Store fulfillment queue (SRD §4.1).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{created: int, skipped: int, lines: list<InventoryFulfillmentLine>}
     */
    public function ingestFromInvoice(Invoice $invoice, array $items = [], ?int $explicitClientSpaceId = null): array
    {
        $created = 0;
        $skipped = 0;
        $lines = [];

        $payloadItems = $items !== [] ? $items : (is_array($invoice->items) ? $invoice->items : []);

        // Dual-stream demand: always capture intent before stock / routing decisions (SRD A-02).
        $this->demandLedger->recordFromInvoice($invoice, $payloadItems);

        if (! InventoryModuleConfig::query()
            ->where('business_id', $invoice->business_id)
            ->where('is_active', true)
            ->exists()) {
            return compact('created', 'skipped', 'lines');
        }

        $assignment = $this->resolveAssignment($invoice, $explicitClientSpaceId);

        if (! $assignment) {
            Log::warning('Inventory fulfillment ingest skipped — no Client Space → End Store routing', [
                'invoice_id' => $invoice->id,
                'business_id' => $invoice->business_id,
                'branch_id' => $invoice->branch_id,
                'client_space_id' => $explicitClientSpaceId ?? $invoice->client_space_id,
            ]);

            return compact('created', 'skipped', 'lines');
        }

        foreach ($payloadItems as $item) {
            $name = Str::lower(trim((string) ($item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? '')));
            if ($name === 'deposit') {
                $skipped++;
                continue;
            }

            $itemId = $item['id'] ?? $item['item_id'] ?? null;
            if (! $itemId) {
                $skipped++;
                continue;
            }

            $itemModel = Item::query()->find($itemId);
            if (! $itemModel || $itemModel->type !== 'good') {
                $skipped++;
                continue;
            }

            $quantity = (float) ($item['quantity'] ?? 1);
            if ($quantity <= 0) {
                $skipped++;
                continue;
            }

            $priority = $this->detectPriority($item, $itemModel, (int) $invoice->business_id);
            $line = $this->upsertLine($invoice, $itemModel, $assignment, $quantity, $priority, $item);

            if ($line->wasRecentlyCreated) {
                $created++;
            } else {
                // Quantity/priority refresh on an open line still counts as handled, not skipped.
            }

            $lines[] = $line;
        }

        Log::info('Inventory fulfillment ingest finished', [
            'invoice_id' => $invoice->id,
            'store_id' => $assignment->store_id,
            'client_space_id' => $assignment->client_space_id,
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return compact('created', 'skipped', 'lines');
    }

    protected function resolveAssignment(Invoice $invoice, ?int $explicitClientSpaceId = null): ?ClientSpaceStoreAssignment
    {
        $spaceId = $explicitClientSpaceId
            ?? $invoice->client_space_id
            ?? null;

        if ($spaceId) {
            return ClientSpaceStoreAssignment::resolveForClientSpace((int) $spaceId);
        }

        $branchScoped = ClientSpaceStoreAssignment::query()
            ->with(['store', 'clientSpace'])
            ->where('business_id', $invoice->business_id)
            ->where('is_active', true)
            ->whereHas('clientSpace', function ($q) use ($invoice) {
                $q->where('branch_id', $invoice->branch_id);
            })
            ->get();

        if ($branchScoped->count() === 1) {
            return $branchScoped->first();
        }

        $businessScoped = ClientSpaceStoreAssignment::query()
            ->with(['store', 'clientSpace'])
            ->where('business_id', $invoice->business_id)
            ->where('is_active', true)
            ->get();

        if ($businessScoped->count() === 1) {
            return $businessScoped->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $itemPayload
     */
    protected function detectPriority(array $itemPayload, Item $item, int $businessId): string
    {
        $haystack = Str::upper(implode(' ', array_filter([
            (string) ($itemPayload['name'] ?? ''),
            (string) ($itemPayload['displayName'] ?? ''),
            (string) ($itemPayload['notes'] ?? ''),
            (string) ($itemPayload['priority'] ?? ''),
            (string) $item->name,
        ])));

        $keywords = InventoryModuleConfig::query()
            ->where('business_id', $businessId)
            ->first()
            ?->statPriorityKeywords() ?? ['STAT', 'URGENT'];

        foreach ($keywords as $word) {
            if ($word !== '' && str_contains($haystack, $word)) {
                return ($word === 'STAT' || str_starts_with($word, 'STAT'))
                    ? InventoryFulfillmentLine::PRIORITY_STAT
                    : InventoryFulfillmentLine::PRIORITY_URGENT;
            }
        }

        $explicit = Str::lower((string) ($itemPayload['priority'] ?? 'normal'));

        return match ($explicit) {
            'stat' => InventoryFulfillmentLine::PRIORITY_STAT,
            'urgent' => InventoryFulfillmentLine::PRIORITY_URGENT,
            default => InventoryFulfillmentLine::PRIORITY_NORMAL,
        };
    }

    /**
     * @param  array<string, mixed>  $itemPayload
     */
    protected function upsertLine(
        Invoice $invoice,
        Item $item,
        ClientSpaceStoreAssignment $assignment,
        float $quantity,
        string $priority,
        array $itemPayload,
    ): InventoryFulfillmentLine {
        $existing = InventoryFulfillmentLine::query()
            ->where('invoice_id', $invoice->id)
            ->where('item_id', $item->id)
            ->where('store_id', $assignment->store_id)
            ->whereNotIn('status', [
                InventoryFulfillmentLine::STATUS_CANCELLED,
                InventoryFulfillmentLine::STATUS_COMPLETED,
            ])
            ->first();

        if ($existing) {
            $existing->fill([
                'quantity' => $quantity,
                'priority' => $priority,
                'client_space_id' => $assignment->client_space_id,
                'fulfillment_strategy' => $assignment->fulfillment_strategy,
                'visit_id' => $invoice->visit_id,
                'client_id' => $invoice->client_id,
                'item_name' => $item->name,
            ]);
            $existing->save();

            return $existing;
        }

        return InventoryFulfillmentLine::create([
            'business_id' => $invoice->business_id,
            'store_id' => $assignment->store_id,
            'client_space_id' => $assignment->client_space_id,
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'visit_id' => $invoice->visit_id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'quantity_fulfilled' => 0,
            'fulfillment_strategy' => $assignment->fulfillment_strategy,
            'priority' => $priority,
            'status' => InventoryFulfillmentLine::STATUS_PENDING,
            'basket_key' => $invoice->client_id
                ? 'client-'.$invoice->client_id.'-visit-'.($invoice->visit_id ?? 'none')
                : null,
            'queued_at' => now(),
            'notes' => $itemPayload['notes'] ?? null,
            'metadata' => [
                'invoice_number' => $invoice->invoice_number,
                'source' => 'payment_confirmation',
            ],
        ]);
    }
}
