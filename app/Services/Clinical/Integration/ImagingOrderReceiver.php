<?php

namespace App\Services\Clinical\Integration;

use App\Models\ImagingOrder;

/**
 * Local receiver for the 'imaging'.'diagnostic-order-placed' fact.
 * ImagingOrder::create() IS the real order-creation logic in this app —
 * its own model events (creating/created) default the status/priority and
 * immediately call acceptIntoStudy(), which creates the ImagingStudy and
 * starts its workflow. There's no separate "OrderService" to call instead
 * (confirmed by reading ImagingOrderController, which is unused/stub).
 */
class ImagingOrderReceiver
{
    /**
     * @param array<string, mixed> $payload DiagnosticOrderPlacedFact::toPayload()
     * @return array{status: string, imaging_order_id: int, imaging_study_id: ?int, accession_number: ?string}
     */
    public function handle(array $payload): array
    {
        $order = ImagingOrder::create([
            'business_id' => $payload['business_id'],
            'branch_id' => $payload['branch_id'],
            'client_id' => $payload['global_client_id'],
            'visit_id' => $payload['visit_id'],
            'ordering_user_id' => $payload['ordering_clinician_id'],
            'protocol_code' => $payload['protocol_code'],
            'clinical_indication' => $payload['clinical_indication'] ?? null,
        ]);

        $order->refresh();

        return [
            'status' => 'ORDER_RECEIVED',
            'imaging_order_id' => $order->id,
            'imaging_study_id' => $order->imaging_study_id,
            'accession_number' => $order->imagingStudy?->accession_number,
        ];
    }
}
