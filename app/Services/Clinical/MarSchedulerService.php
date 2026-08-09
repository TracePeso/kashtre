<?php

namespace App\Services\Clinical;

use App\Models\ClinicalConsumptionEvent;
use App\Models\ClinicalMarDose;
use App\Models\ClinicalMedicationOrder;
use App\Models\PharmacyRouteFrequency;

/**
 * SRD §1.5 MAR Scheduler, off pharmacy_route_frequency_master's
 * minute_interval (seeded Chunk 1). STAT generates a single immediate
 * dose; PRN generates none (administered on-demand per the doc's "min
 * retry window lock" description — the retry-window enforcement itself
 * is a Chunk 9 refinement, not built here).
 */
class MarSchedulerService
{
    public function generateDosesForOrder(ClinicalMedicationOrder $order, int $windowDays = 3): void
    {
        $frequency = PharmacyRouteFrequency::where('type', 'FREQUENCY')
            ->where('code', $order->frequency_code)
            ->where(function ($query) use ($order) {
                $query->where('business_id', $order->business_id)->orWhereNull('business_id');
            })
            ->orderByRaw('business_id IS NULL')
            ->first();

        if (! $frequency) {
            return;
        }

        if ($frequency->code === 'STAT') {
            ClinicalMarDose::create([
                'medication_order_id' => $order->id,
                'scheduled_at' => $order->start_at,
            ]);

            return;
        }

        if ($frequency->code === 'PRN' || $frequency->minute_interval <= 0) {
            return;
        }

        $end = $order->end_at ?? $order->start_at->copy()->addDays($windowDays);
        $cursor = $order->start_at->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            ClinicalMarDose::create([
                'medication_order_id' => $order->id,
                'scheduled_at' => $cursor->copy(),
            ]);

            $cursor->addMinutes($frequency->minute_interval);
        }
    }

    /**
     * @return array<string, mixed> ConsumptionEventBroker's response, or a
     *                               stand-in status for externally-sourced/unlinked drugs
     */
    public function administerDose(ClinicalMarDose $dose, int $userId, ?int $wardId = null): array
    {
        $order = $dose->medicationOrder;

        $dose->update([
            'status' => ClinicalMarDose::STATUS_ADMINISTERED,
            'administered_by_user_id' => $userId,
            'administered_at' => now(),
        ]);

        if ($order->is_external || ! $order->drug_code) {
            return ['status' => 'ADMINISTERED_NO_INVENTORY_LINK'];
        }

        return app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => $order->business_id,
            'branch_id' => $order->branch_id,
            'client_id' => $order->client_id,
            'visit_id' => $order->visit_id,
            'ward_id' => $wardId,
            'item_code' => $order->drug_code,
            'quantity' => (float) $order->dose_amount,
            'fact_token' => ClinicalConsumptionEvent::TOKEN_MEDICATION_ADMINISTERED,
            'usage_context' => 'PATIENT',
        ], $userId);
    }

    public function holdDose(ClinicalMarDose $dose, int $userId, string $reasonCode, ?string $notes = null): void
    {
        $dose->update([
            'status' => ClinicalMarDose::STATUS_HELD,
            'administered_by_user_id' => $userId,
            'administered_at' => now(),
            'reason_code' => $reasonCode,
            'notes' => $notes,
        ]);
    }
}
