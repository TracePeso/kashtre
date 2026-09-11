<?php

namespace App\Support\Clinical;

/**
 * One recorded consumption of a stocked item against a patient.
 *
 * Local writes this straight into `clinical_consumption_events` with a rich
 * reconciliation outcome (approved-pool vs floor-stock, billing triggered,
 * …) because it also owns the Inventory decrement. Under the API driver
 * Clinical owns that decision entirely — `POST clinical/consumption/floor-
 * stock` (confirmed live 2026-08-18) hands back a flat record, no
 * reconciliation vocabulary, and queues an outbound fact
 * (`GET clinical/consumption/outbox`) that is meant for Main's Inventory to
 * consume and has no consumer wired up yet (see RecordConsumption's
 * class-doc). physicalStockReduced is therefore only ever known under the
 * local driver — it stays null under `api`, not false, because "unknown"
 * and "did not happen" are different facts. deliveryStatus (from
 * `GET clinical/patients/{id}/consumption-events`, confirmed live
 * 2026-08-19) is the honest, undisguised version of the same fact — it is
 * PENDING until something on Main's side actually drains the outbox, which
 * per INTEGRATION_README.md is deliberately never automatic (dispense/
 * billing stays a human action on Kashtre's own Record Usage screen).
 */
class ConsumptionRecord
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $patientId,
        public readonly ?string $visitId,
        public readonly string $itemCode,
        public readonly float $quantity,
        public readonly string $factToken,
        public readonly ?string $justificationNote,
        public readonly ?string $recordedAt,
        public readonly ?int $recordedByUserId = null,
        public readonly ?bool $physicalStockReduced = null,
        public readonly ?bool $billingTriggered = null,
        public readonly ?string $deliveryStatus = null,
    ) {
    }

    public static function fromModel(\App\Models\ClinicalConsumptionEvent $event): self
    {
        return new self(
            id: $event->id,
            patientId: (string) $event->client_id,
            visitId: $event->visit_id ? (string) $event->visit_id : null,
            itemCode: (string) $event->item_code,
            quantity: (float) $event->quantity,
            factToken: (string) $event->fact_token,
            justificationNote: null,
            recordedAt: $event->occurred_at?->toIso8601String(),
            recordedByUserId: $event->recorded_by_user_id,
            physicalStockReduced: (bool) $event->physical_stock_reduced,
            billingTriggered: (bool) $event->billing_triggered,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload, string $factToken): self
    {
        return new self(
            id: $payload['id'] ?? 0,
            patientId: (string) ($payload['patient_id'] ?? ''),
            visitId: isset($payload['visit_id']) ? (string) $payload['visit_id'] : null,
            itemCode: (string) ($payload['inventory_sku'] ?? ''),
            quantity: (float) ($payload['quantity_used'] ?? 0),
            factToken: $factToken,
            justificationNote: $payload['justification_note'] ?? null,
            recordedAt: $payload['created_at'] ?? null,
            recordedByUserId: isset($payload['recorded_by_user_id']) ? (int) $payload['recorded_by_user_id'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload  a row from consumption-events
     */
    public static function fromConsumptionEvent(array $payload): self
    {
        return new self(
            id: (string) ($payload['event_id'] ?? ''),
            patientId: '',
            visitId: isset($payload['visit_id']) ? (string) $payload['visit_id'] : null,
            itemCode: (string) ($payload['inventory_sku'] ?? ''),
            quantity: (float) ($payload['quantity'] ?? 0),
            factToken: (string) ($payload['fact_token'] ?? ''),
            justificationNote: $payload['justification_note'] ?? null,
            recordedAt: $payload['occurred_at'] ?? null,
            recordedByUserId: isset($payload['executed_by_user_id']) ? (int) $payload['executed_by_user_id'] : null,
            deliveryStatus: $payload['delivery_status'] ?? null,
        );
    }
}
