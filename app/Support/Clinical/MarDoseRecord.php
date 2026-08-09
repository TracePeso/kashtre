<?php

namespace App\Support\Clinical;

use App\Models\ClinicalMarDose;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * One scheduled dose on the Medication Administration Record, from
 * clinical_mar_doses or GET /api/v1/clinical/patients/{patientId}/mar.
 *
 * `medicationOrder` keeps the local relationship's name so the MAR table in
 * medication-orders-panel.blade.php renders unchanged.
 *
 * A dose that is past due is still returned, flagged rather than hidden —
 * §10.4 is deliberate about this: a late dose is recorded and flagged, never
 * refused, because refusing it pushes the nurse to chart nothing at all.
 */
class MarDoseRecord
{
    public function __construct(
        public readonly int|string $id,
        public readonly ?CarbonInterface $scheduled_at,
        public readonly string $status = 'DUE',
        public readonly ?MedicationOrderRecord $medicationOrder = null,
        public readonly ?string $reason_code = null,
    ) {
    }

    public static function fromModel(ClinicalMarDose $dose): self
    {
        return new self(
            id: $dose->id,
            scheduled_at: $dose->scheduled_at,
            status: (string) $dose->status,
            medicationOrder: $dose->medicationOrder
                ? MedicationOrderRecord::fromModel($dose->medicationOrder)
                : null,
            reason_code: $dose->reason_code,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        $order = $payload['order'] ?? $payload['medication_order'] ?? null;

        return new self(
            id: $payload['dose_id'] ?? $payload['id'] ?? '',
            scheduled_at: isset($payload['scheduled_at']) ? Carbon::parse($payload['scheduled_at']) : null,
            status: (string) ($payload['state'] ?? $payload['status'] ?? 'DUE'),
            medicationOrder: is_array($order) ? MedicationOrderRecord::fromApi($order) : null,
            reason_code: $payload['reason_code'] ?? null,
        );
    }

    public function isOverdue(): bool
    {
        return $this->status === 'DUE'
            && $this->scheduled_at !== null
            && $this->scheduled_at->isPast();
    }
}
