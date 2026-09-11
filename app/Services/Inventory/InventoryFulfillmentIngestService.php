<?php

namespace App\Services\Inventory;

use App\Models\ClientSpaceStoreAssignment;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryModuleConfig;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use App\Support\InventoryFulfillmentStrategy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InventoryFulfillmentIngestService
{
    public function __construct(
        private readonly InventoryDemandLedgerService $demandLedger,
    ) {}

    /**
     * Stamp paid goods onto the mapped End Store fulfillment queue.
     *
     * Routing:
     * 1. Active Client Space → End Store assignment (strategy always from this mapping)
     * 2. Else invoice.end_store_id + fulfillment_strategy / store defaults
     * 3. Else branch-preferred End Store
     *
     * When a space assignment exists, invoice/POS fulfillment_strategy does not override it.
     * @param  array<int, array<string, mixed>>  $items
     * @return array{created: int, skipped: int, lines: list<InventoryFulfillmentLine>}
     */
    public function ingestFromInvoice(Invoice $invoice, array $items = [], ?int $explicitClientSpaceId = null): array
    {
        $created = 0;
        $skipped = 0;
        $lines = [];

        $payloadItems = $items !== [] ? $items : (is_array($invoice->items) ? $invoice->items : []);

        // Dual-stream demand: always capture intent before stock / routing decisions.
        $this->demandLedger->recordFromInvoice($invoice, $payloadItems);

        if (! InventoryModuleConfig::query()
            ->where('business_id', $invoice->business_id)
            ->where('is_active', true)
            ->exists()) {
            return compact('created', 'skipped', 'lines');
        }

        $assignment = $this->resolveAssignment($invoice, $explicitClientSpaceId);
        $store = $assignment?->store;
        $clientSpaceId = $assignment?->client_space_id;
        $strategy = null;

        if ($assignment) {
            // Space routing is authoritative for strategy (and default End Store).
            $strategy = (string) $assignment->fulfillment_strategy;
        } else {
            $store = $this->resolveEndStore($invoice);
            if (! $store) {
                Log::warning('Inventory fulfillment ingest skipped — no Client Space routing or End Store', [
                    'invoice_id' => $invoice->id,
                    'business_id' => $invoice->business_id,
                    'branch_id' => $invoice->branch_id,
                    'client_space_id' => $explicitClientSpaceId ?? $invoice->client_space_id,
                    'end_store_id' => $invoice->end_store_id ?? null,
                ]);

                return compact('created', 'skipped', 'lines');
            }

            $strategy = $this->resolveStrategy($invoice, $store);
            $clientSpaceId = $explicitClientSpaceId ?? $invoice->client_space_id;

            // Without space routing, invoice strategy may still override store default.
            $fromInvoiceStrategy = (string) ($invoice->fulfillment_strategy ?? '');
            if (in_array($fromInvoiceStrategy, [
                InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE,
                InventoryFulfillmentStrategy::BATCH_AND_STAGE,
            ], true)) {
                $strategy = $fromInvoiceStrategy;
            }
        }

        // Invoice End Store may still redirect which pharmacy fulfills, but never overrides
        // an active Client Space → strategy mapping.
        if (! empty($invoice->end_store_id)) {
            $override = Store::query()
                ->where('id', $invoice->end_store_id)
                ->where('business_id', $invoice->business_id)
                ->first();
            if ($override && $override->isEndStore()) {
                $store = $override;
            }
        }

        if (! $store || ! $strategy) {
            return compact('created', 'skipped', 'lines');
        }

        // Approved Pool only for Batch & Stage; Outpatient dispenses immediately with no pool.
        $supportsApprovedPool = InventoryFulfillmentStrategy::supportsApprovedPool($strategy);

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
            $line = $this->upsertLine(
                $invoice,
                $itemModel,
                $store,
                $quantity,
                $priority,
                $item,
                $strategy,
                $supportsApprovedPool,
                $clientSpaceId ? (int) $clientSpaceId : null,
            );

            if ($line->wasRecentlyCreated) {
                $created++;
            }

            $lines[] = $line;
        }

        Log::info('Inventory fulfillment ingest finished', [
            'invoice_id' => $invoice->id,
            'store_id' => $store->id,
            'client_space_id' => $clientSpaceId,
            'fulfillment_strategy' => $strategy,
            'supports_approved_pool' => $supportsApprovedPool,
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return compact('created', 'skipped', 'lines');
    }

    protected function resolveAssignment(Invoice $invoice, ?int $explicitClientSpaceId = null): ?ClientSpaceStoreAssignment
    {
        if (! class_exists(ClientSpaceStoreAssignment::class)
            || ! \Illuminate\Support\Facades\Schema::hasTable('client_space_store_assignments')) {
            return null;
        }

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

    protected function resolveEndStore(Invoice $invoice): ?Store
    {
        if (! empty($invoice->end_store_id)) {
            $explicit = Store::query()
                ->where('id', $invoice->end_store_id)
                ->where('business_id', $invoice->business_id)
                ->first();

            if ($explicit && $explicit->isEndStore()) {
                return $explicit;
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
            ->first();

        if ($branchScoped) {
            return $branchScoped;
        }

        return Store::query()
            ->where('business_id', $invoice->business_id)
            ->where(function ($query) {
                $query->where('distribution_type', Store::DISTRIBUTION_END)
                    ->orWhereNull('distribution_type');
            })
            ->orderBy('id')
            ->first();
    }

    protected function resolveStrategy(Invoice $invoice, Store $store): string
    {
        $fromInvoice = (string) ($invoice->fulfillment_strategy ?? '');
        if (in_array($fromInvoice, [
            InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE,
            InventoryFulfillmentStrategy::BATCH_AND_STAGE,
        ], true)) {
            return $fromInvoice;
        }

        $fromItem = null;
        $items = is_array($invoice->items) ? $invoice->items : [];
        foreach ($items as $item) {
            $candidate = Str::upper((string) ($item['fulfillment_strategy'] ?? ''));
            if (in_array($candidate, [
                InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE,
                InventoryFulfillmentStrategy::BATCH_AND_STAGE,
            ], true)) {
                $fromItem = $candidate;
                break;
            }
        }

        if ($fromItem) {
            return $fromItem;
        }

        return $store->defaultFulfillmentStrategy();
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
        Store $store,
        float $quantity,
        string $priority,
        array $itemPayload,
        string $strategy = InventoryFulfillmentStrategy::DISCRETE_IMMEDIATE,
        bool $supportsApprovedPool = true,
        ?int $clientSpaceId = null,
    ): InventoryFulfillmentLine {
        $existing = InventoryFulfillmentLine::query()
            ->where('invoice_id', $invoice->id)
            ->where('item_id', $item->id)
            ->where('store_id', $store->id)
            ->whereNotIn('status', [
                InventoryFulfillmentLine::STATUS_CANCELLED,
                InventoryFulfillmentLine::STATUS_COMPLETED,
            ])
            ->first();

        if ($existing) {
            $existing->fill([
                'quantity' => $quantity,
                'priority' => $priority,
                'client_space_id' => $clientSpaceId,
                'fulfillment_strategy' => $strategy,
                'supports_approved_pool' => $supportsApprovedPool,
                'visit_id' => $invoice->visit_id,
                'client_id' => $invoice->client_id,
                'item_name' => $item->name,
            ]);
            $existing->save();

            return $existing;
        }

        return InventoryFulfillmentLine::create([
            'business_id' => $invoice->business_id,
            'store_id' => $store->id,
            'client_space_id' => $clientSpaceId,
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'visit_id' => $invoice->visit_id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'quantity_fulfilled' => 0,
            'fulfillment_strategy' => $strategy,
            'supports_approved_pool' => $supportsApprovedPool,
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
