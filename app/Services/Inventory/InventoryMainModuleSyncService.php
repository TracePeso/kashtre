<?php

namespace App\Services\Inventory;

use App\Jobs\BillInventoryUsageToMain;
use App\Jobs\ProcessInventoryMainModuleOutbox;
use App\Models\BranchItemPrice;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryMainModuleOutbox;
use App\Models\InventoryUsageEvent;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ServiceDeliveryQueue;
use App\Models\User;
use App\Services\Inventory\InventoryInternalReplenishmentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InventoryMainModuleSyncService
{
    /**
     * Queue Main Module billing for a usage event (SRD §5.3 B / §6).
     */
    public function dispatchUsageBilling(InventoryUsageEvent $event): void
    {
        if (! $event->billed_main_module) {
            return;
        }

        if ($event->main_billing_status === 'completed' && $event->invoice_id) {
            return;
        }

        if (! $event->client_id) {
            $event->forceFill([
                'main_billing_status' => 'skipped',
                'main_billing_error' => 'No client_id on usage event; cannot create Main Module invoice.',
            ])->save();

            return;
        }

        $event->forceFill([
            'main_billing_status' => $event->main_billing_status ?: 'pending',
        ])->save();

        BillInventoryUsageToMain::dispatch($event->id)->afterResponse();
    }

    /**
     * Create a postpaid Invoice for floor / crash usage. Does not re-ingest fulfillment (stock already deducted).
     */
    public function billUsageEvent(InventoryUsageEvent $event): Invoice
    {
        $event->loadMissing(['client', 'item', 'store', 'recordedBy']);

        if ($event->invoice_id) {
            return Invoice::query()->findOrFail($event->invoice_id);
        }

        if (! $event->client_id || ! $event->client) {
            throw ValidationException::withMessages([
                'client_id' => 'Usage event requires a client for Main Module billing.',
            ]);
        }

        if (! $event->item) {
            throw ValidationException::withMessages([
                'item_id' => 'Usage event item is missing.',
            ]);
        }

        return DB::transaction(function () use ($event) {
            $locked = InventoryUsageEvent::query()->whereKey($event->id)->lockForUpdate()->first();
            if ($locked?->invoice_id) {
                return Invoice::query()->findOrFail($locked->invoice_id);
            }

            $client = $event->client;
            $item = $event->item;
            $qty = (float) $event->quantity;
            $branchId = (int) ($client->branch_id ?: 0);

            if ($branchId <= 0) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Client has no branch; cannot create billing invoice.',
                ]);
            }

            $price = $this->resolveItemPrice($item, $branchId);
            $lineTotal = round($price * $qty, 2);

            $line = [
                'id' => $item->id,
                'item_id' => $item->id,
                'name' => $item->name,
                'quantity' => $qty,
                'price' => $price,
                'total_amount' => $lineTotal,
                'type' => $item->type ?: 'good',
                'source' => 'inventory_usage',
                'inventory_usage_event_uuid' => $event->uuid,
                'store_id' => $event->store_id,
                'classification' => $event->classification,
            ];

            $notes = sprintf(
                'Inventory postpaid usage (%s). Event %s.%s',
                $event->contextLabel(),
                $event->uuid,
                $event->notes ? ' '.$event->notes : ''
            );

            $invoice = $this->createInvoiceWithRetry([
                'client_id' => $client->id,
                'business_id' => $event->business_id,
                'branch_id' => $branchId,
                'client_space_id' => null,
                'created_by' => $event->recorded_by,
                'currency' => 'UGX',
                'client_name' => $client->name,
                'client_phone' => $client->phone_number,
                'payment_phone' => $client->phone_number,
                'visit_id' => $client->visit_id,
                'items' => [$line],
                'subtotal' => $lineTotal,
                'package_adjustment' => 0,
                'account_balance_adjustment' => 0,
                'service_charge' => 0,
                'total_amount' => $lineTotal,
                'amount_paid' => 0,
                'balance_due' => $lineTotal,
                'payment_methods' => [],
                'payment_status' => 'pending',
                'status' => 'confirmed',
                'notes' => $notes,
                'confirmed_at' => now(),
            ], (int) $event->business_id);

            $event->forceFill([
                'invoice_id' => $invoice->id,
                'main_billing_status' => 'completed',
                'main_billing_error' => null,
                'main_billed_at' => now(),
                'billed_main_module' => true,
            ])->save();

            $this->enqueue(
                eventType: InventoryMainModuleOutbox::TYPE_USAGE_BILLING,
                eventId: 'usage-billed-'.$event->uuid,
                aggregateType: InventoryUsageEvent::class,
                aggregateId: $event->id,
                businessId: (int) $event->business_id,
                payload: [
                    'usage_event_uuid' => $event->uuid,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $client->id,
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'total_amount' => $lineTotal,
                    'context' => $event->context,
                ],
                processNow: false
            );

            return $invoice;
        });
    }

    /**
     * After local SDQ flip, enqueue durable Completed sync (§8.1 / §8.3).
     */
    public function enqueueFulfillmentCompleted(InventoryFulfillmentLine $line, User $user): void
    {
        $this->enqueue(
            eventType: InventoryMainModuleOutbox::TYPE_FULFILLMENT_COMPLETED,
            eventId: 'fulfillment-completed-'.$line->uuid.'-'.($line->status === InventoryFulfillmentLine::STATUS_COMPLETED ? 'full' : 'partial').'-'.(string) ($line->quantity_fulfilled),
            aggregateType: InventoryFulfillmentLine::class,
            aggregateId: $line->id,
            businessId: (int) $line->business_id,
            payload: [
                'fulfillment_line_uuid' => $line->uuid,
                'fulfillment_line_id' => $line->id,
                'invoice_id' => $line->invoice_id,
                'item_id' => $line->item_id,
                'client_id' => $line->client_id,
                'status' => $line->status,
                'quantity_fulfilled' => (float) $line->quantity_fulfilled,
                'service_delivery_queue_id' => $line->service_delivery_queue_id,
                'completed_by' => $user->id,
            ],
            processNow: true
        );
    }

    /**
     * High-priority replenishment signal after crash cart usage (§6 step 4.4).
     */
    public function enqueueCrashCartReplenishment(InventoryUsageEvent $event): void
    {
        if ($event->context !== InventoryUsageEvent::CONTEXT_CRASH_CART) {
            return;
        }

        $this->enqueue(
            eventType: InventoryMainModuleOutbox::TYPE_CRASH_CART_REPLENISHMENT,
            eventId: 'crash-replenish-'.$event->uuid,
            aggregateType: InventoryUsageEvent::class,
            aggregateId: $event->id,
            businessId: (int) $event->business_id,
            payload: [
                'usage_event_uuid' => $event->uuid,
                'store_id' => $event->store_id,
                'item_id' => $event->item_id,
                'quantity' => (float) $event->quantity,
                'priority' => 'high',
            ],
            processNow: false
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function enqueue(
        string $eventType,
        string $eventId,
        ?string $aggregateType,
        ?int $aggregateId,
        ?int $businessId,
        array $payload,
        bool $processNow = true
    ): InventoryMainModuleOutbox {
        $existing = InventoryMainModuleOutbox::query()->where('event_id', $eventId)->first();
        if ($existing) {
            if ($processNow && $existing->status === InventoryMainModuleOutbox::STATUS_PENDING) {
                ProcessInventoryMainModuleOutbox::dispatch($existing->id)->afterResponse();
            }

            return $existing;
        }

        $row = InventoryMainModuleOutbox::query()->create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'business_id' => $businessId,
            'payload' => $payload,
            'status' => InventoryMainModuleOutbox::STATUS_PENDING,
            'available_at' => now(),
        ]);

        if ($processNow) {
            ProcessInventoryMainModuleOutbox::dispatch($row->id)->afterResponse();
        }

        return $row;
    }

    public function processOutboxRow(InventoryMainModuleOutbox $row): void
    {
        if ($row->status === InventoryMainModuleOutbox::STATUS_SENT) {
            return;
        }

        $row->attempts = (int) $row->attempts + 1;
        $row->status = InventoryMainModuleOutbox::STATUS_PROCESSING;
        $row->save();

        try {
            match ($row->event_type) {
                InventoryMainModuleOutbox::TYPE_FULFILLMENT_COMPLETED => $this->processFulfillmentCompleted($row),
                InventoryMainModuleOutbox::TYPE_USAGE_BILLING => null, // invoice already created; outbox is audit/ack
                InventoryMainModuleOutbox::TYPE_CRASH_CART_REPLENISHMENT => $this->processCrashCartReplenishment($row),
                default => Log::info('Inventory Main Module outbox: unhandled event type', [
                    'event_type' => $row->event_type,
                    'event_id' => $row->event_id,
                ]),
            };

            $row->status = InventoryMainModuleOutbox::STATUS_SENT;
            $row->processed_at = now();
            $row->last_error = null;
            $row->save();
        } catch (\Throwable $e) {
            $row->status = InventoryMainModuleOutbox::STATUS_FAILED;
            $row->last_error = $e->getMessage();
            $row->available_at = now()->addMinutes(min(60, (int) $row->attempts * 2));
            $row->save();

            Log::warning('Inventory Main Module outbox processing failed', [
                'event_id' => $row->event_id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function processFulfillmentCompleted(InventoryMainModuleOutbox $row): void
    {
        $payload = $row->payload ?? [];
        $lineId = (int) ($payload['fulfillment_line_id'] ?? $row->aggregate_id ?? 0);
        $line = InventoryFulfillmentLine::query()->find($lineId);

        if (! $line || ! $line->invoice_id || ! $line->item_id) {
            return;
        }

        $userId = (int) ($payload['completed_by'] ?? $line->completed_by ?? 0);

        $queues = ServiceDeliveryQueue::query()
            ->where('invoice_id', $line->invoice_id)
            ->where('item_id', $line->item_id)
            ->when($line->client_id, fn ($q) => $q->where('client_id', $line->client_id))
            ->whereIn('status', ['pending', 'in_progress', 'partially_done'])
            ->get();

        foreach ($queues as $queue) {
            if (! $line->service_delivery_queue_id) {
                $line->forceFill(['service_delivery_queue_id' => $queue->id])->saveQuietly();
            }

            if ($line->status === InventoryFulfillmentLine::STATUS_COMPLETED) {
                $queue->markAsCompleted($userId ?: null);
            } elseif ($line->status === InventoryFulfillmentLine::STATUS_PARTIAL) {
                $queue->markAsPartiallyDone($userId ?: null);
            }
        }
    }

    protected function processCrashCartReplenishment(InventoryMainModuleOutbox $row): void
    {
        // Audit / Main Module signal only. Physical IR draft is created on Seal Ready
        // via InventoryCrashCartService::markReady (avoids duplicate drafts per usage line).
        Log::info('Crash cart replenishment signal acknowledged', $row->payload ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function createInvoiceWithRetry(array $payload, int $businessId): Invoice
    {
        $invoice = null;

        for ($attempt = 0; $attempt < 12; $attempt++) {
            try {
                $invoice = Invoice::create(array_merge($payload, [
                    'invoice_number' => Invoice::generateInvoiceNumber($businessId, 'invoice'),
                ]));
                break;
            } catch (QueryException $e) {
                $sqlState = $e->errorInfo[0] ?? '';
                $driverCode = (int) ($e->errorInfo[1] ?? 0);
                $isDuplicate = $sqlState === '23000' || $driverCode === 1062;

                if (! $isDuplicate || $attempt >= 11) {
                    throw $e;
                }
            }
        }

        if (! $invoice) {
            throw new \RuntimeException('Failed to create Main Module billing invoice for inventory usage.');
        }

        return $invoice;
    }

    protected function resolveItemPrice(Item $item, int $branchId): float
    {
        $branchPrice = BranchItemPrice::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $item->id)
            ->value('price');

        if ($branchPrice !== null) {
            return round((float) $branchPrice, 2);
        }

        return round((float) ($item->default_price ?? 0), 2);
    }
}
