<?php

namespace App\Http\Controllers\API\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ClinicalInboundEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives events the Clinical Module emits to Main — the `POST {MAIN_MODULE_URL}/events`
 * target in API Integration Guide §12.
 *
 * Two properties matter more than anything else here:
 *
 * 1. **Idempotency on event_id.** Delivery is at-least-once and retries with
 *    exponential backoff, so redelivery is normal operation, not an error. A
 *    duplicate is acknowledged 200 and does no work.
 *
 * 2. **Acknowledge on receipt, not on completion.** The event is recorded and
 *    accepted before any downstream work is attempted. If our own handling
 *    fails, that is our problem to retry from the ledger — making Clinical
 *    redeliver forever because a Main-side handler is broken helps nobody, and
 *    the event would be lost entirely if we 500'd and they eventually gave up.
 */
class ClinicalEventsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:64'],
            'fact_token' => ['required', 'string', 'max:100'],
            'tenant_id' => ['nullable', 'string', 'max:100'],
            'global_client_id' => ['nullable', 'string', 'max:100'],
            'visit_id' => ['nullable', 'string', 'max:100'],
        ]);

        $existing = ClinicalInboundEvent::where('event_id', $validated['event_id'])->first();

        if ($existing) {
            // A redelivery. Say so explicitly rather than silently 200-ing, so
            // a persistent stream of duplicates is visible to whoever is
            // debugging Clinical's outbox.
            return response()->json([
                'data' => [
                    'event_id' => $existing->event_id,
                    'status' => $existing->status,
                    'duplicate' => true,
                ],
            ]);
        }

        try {
            $event = ClinicalInboundEvent::create([
                'event_id' => $validated['event_id'],
                'fact_token' => $validated['fact_token'],
                'tenant_id' => $validated['tenant_id'] ?? null,
                'business_id' => $this->resolveBusinessId($validated['tenant_id'] ?? null),
                'global_client_id' => $validated['global_client_id'] ?? null,
                'visit_id' => $validated['visit_id'] ?? null,
                'payload' => $request->all(),
                'status' => ClinicalInboundEvent::STATUS_RECEIVED,
            ]);
        } catch (QueryException $e) {
            // Two concurrent deliveries of the same event raced past the check
            // above. The unique index caught it, which is exactly its job.
            return response()->json([
                'data' => [
                    'event_id' => $validated['event_id'],
                    'status' => ClinicalInboundEvent::STATUS_RECEIVED,
                    'duplicate' => true,
                ],
            ]);
        }

        $this->handle($event);

        return response()->json([
            'data' => [
                'event_id' => $event->event_id,
                'status' => $event->status,
                'duplicate' => false,
            ],
        ], 201);
    }

    /**
     * Dispatches a received event to whatever Main does about it.
     *
     * Only INFANT_REGISTRATION_REQUESTED is actionable today, and §14 records
     * that Main does not yet receive it — the newborn has no chart of its own
     * until Main registers the infant and calls
     * POST /clinical/maternity/birth-records/{id}/link-infant back. Recording
     * the event is the first half of closing that gap; the registration itself
     * needs a decision about how an infant record is created, which is Main's
     * to make.
     *
     * Everything else is recorded for audit and acknowledged. Consumption
     * facts go to Inventory directly, not through here.
     */
    private function handle(ClinicalInboundEvent $event): void
    {
        try {
            match ($event->fact_token) {
                ClinicalInboundEvent::TOKEN_INFANT_REGISTRATION_REQUESTED => $this->flagForInfantRegistration($event),
                default => $this->acknowledgeOnly($event),
            };
        } catch (Throwable $e) {
            // Never rethrown: the event is safely on the ledger and can be
            // reprocessed from there. Failing the response would make Clinical
            // redeliver indefinitely for a fault on our side.
            $event->update([
                'status' => ClinicalInboundEvent::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Failed to handle a clinical event.', [
                'event_id' => $event->event_id,
                'fact_token' => $event->fact_token,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function flagForInfantRegistration(ClinicalInboundEvent $event): void
    {
        // Left as RECEIVED deliberately: the work is not done. A registration
        // clerk (or a later automated handler) picks these up, and marking
        // them PROCESSED here would hide newborns that never got a chart.
        Log::info('Clinical requested an infant registration.', [
            'event_id' => $event->event_id,
            'mother_client_id' => $event->global_client_id,
            'birth_record_id' => $event->payload['birth_record_id'] ?? null,
        ]);
    }

    private function acknowledgeOnly(ClinicalInboundEvent $event): void
    {
        $event->update([
            'status' => ClinicalInboundEvent::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Maps Clinical's tenant back onto our business. The reverse of
     * ClinicalRequestContext::tenantId(), including its TENANT-{id} fallback.
     */
    private function resolveBusinessId(?string $tenantId): ?int
    {
        if (! $tenantId) {
            return null;
        }

        if (preg_match('/^TENANT-(\d+)$/', $tenantId, $matches)) {
            return (int) $matches[1];
        }

        return Business::whereRaw('UPPER(entity_code) = ?', [strtoupper($tenantId)])->value('id');
    }
}
