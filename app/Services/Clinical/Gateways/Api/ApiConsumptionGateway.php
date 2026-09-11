<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\ConsumptionGateway;
use App\Models\ClinicalConsumptionEvent;
use App\Models\ClinicalFloorStockReview;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ConsumptionRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CLINICAL_DRIVER=api: `clinical/consumption/floor-stock` (confirmed live
 * 2026-08-18 — required fields and response shape are from that probe, the
 * guide does not document this endpoint's body). Crash-cart consumption
 * (`clinical/consumption/crash-cart`) is a separate, larger workflow tied to
 * a resuscitation event and a dedicated cart store — out of scope here,
 * this only covers the general "took this off the ward shelf" case the
 * local driver called NON_APPROVED_FLOOR_STOCK_USAGE.
 *
 * Known gap, not a bug: this endpoint queues an outbound fact on Clinical's
 * side (`GET clinical/consumption/outbox`, target INVENTORY) for Main's
 * Inventory to consume and decrement physical stock. Nothing on Main
 * consumes that queue yet, by design — INTEGRATION_README.md is explicit
 * that dispense/usage/billing stays a human action on Kashtre's own Record
 * Usage screen, never a REST call Clinical triggers directly. Recording
 * here is real and audited on Clinical's side; the read side
 * (forPatient()) reports the honest `delivery_status` from Clinical rather
 * than guessing whether the shelf count actually moved.
 */
class ApiConsumptionGateway implements ConsumptionGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forPatient(ClinicalActor $actor, string $patientId, int $limit = 10): array
    {
        // The real, documented per-patient read (API Integration Guide
        // §10.5c, confirmed live 2026-08-19) — replaces an earlier version
        // of this method that scraped `clinical/consumption/outbox` for
        // lack of knowing this one existed. That endpoint is a general
        // outbound queue (still the right source for the facility-wide
        // "not yet billed" panel, see pendingFloorStockForBusiness()); this
        // one is scoped to the patient and named for exactly this purpose,
        // and it also carries every other fact this patient triggered
        // (ADMISSION_REQUESTED, TRIAGE_PRIORITY_ASSIGNED, …) — filtered out
        // below the same way, by requiring a real SKU.
        try {
            $data = $this->client->get(
                "clinical/patients/{$patientId}/consumption-events",
                array_filter(['limit' => $limit]),
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load this patient\'s consumption history.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return collect($rows)
            ->filter(fn ($row) => is_array($row) && ! empty($row['inventory_sku']))
            ->sortByDesc(fn ($row) => $row['occurred_at'] ?? '')
            ->take($limit)
            ->map(fn (array $row) => ConsumptionRecord::fromConsumptionEvent($row))
            ->values()
            ->all();
    }

    public function record(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $itemCode,
        float $quantity,
        string $factToken,
        string $usageContext,
        ?string $justificationNote = null,
    ): ConsumptionRecord {
        // The endpoint only exposes one scenario (floor-stock) — $factToken/
        // $usageContext are accepted for interface parity with the local
        // driver, which supports several, but are not sent: Clinical decides
        // the fact_token itself from which endpoint was called.
        $data = $this->client->post('clinical/consumption/floor-stock', [
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'inventory_sku' => $itemCode,
            'quantity_used' => $quantity,
            'justification_note' => $justificationNote,
        ], [
            'business_id' => $actor->businessId,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return ConsumptionRecord::fromApi($data, ClinicalConsumptionEvent::TOKEN_NON_APPROVED_FLOOR_STOCK_USAGE);
    }

    public function pendingFloorStockForBusiness(ClinicalActor $actor, int $limit = 50): array
    {
        try {
            $data = $this->client->get('clinical/consumption/outbox', [], ['business_id' => $actor->businessId]);
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load the consumption outbox.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        // Only the scenario nothing bills automatically — MEDICATION_WASTED
        // and the like are not a Record Usage concern.
        $floorStockRows = collect($rows)->filter(fn ($row) => is_array($row)
            && ($row['target'] ?? null) === 'INVENTORY'
            && ($row['fact_token'] ?? null) === ClinicalConsumptionEvent::TOKEN_NON_APPROVED_FLOOR_STOCK_USAGE
            && ! empty($row['event_id']));

        $reviewedEventIds = ClinicalFloorStockReview::where('business_id', $actor->businessId)
            ->whereIn('clinical_event_id', $floorStockRows->pluck('event_id'))
            ->pluck('clinical_event_id')
            ->all();

        return $floorStockRows
            ->sortByDesc(fn ($row) => $row['created_at'] ?? '')
            ->take($limit)
            ->map(function (array $row) use ($reviewedEventIds) {
                $payload = $row['payload'] ?? [];

                return [
                    'event_id' => (string) $row['event_id'],
                    'patient_id' => (string) ($payload['global_client_id'] ?? ''),
                    'visit_id' => isset($payload['visit_id']) ? (string) $payload['visit_id'] : null,
                    'item_code' => (string) ($payload['inventory_sku'] ?? ''),
                    'quantity' => (float) ($payload['quantity_consumed'] ?? 0),
                    'recorded_by_user_id' => isset($payload['executed_by_user_id']) ? (int) $payload['executed_by_user_id'] : null,
                    'recorded_at' => $row['created_at'] ?? null,
                    'reviewed' => in_array($row['event_id'], $reviewedEventIds, true),
                ];
            })
            ->values()
            ->all();
    }
}
