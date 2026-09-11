<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\WardCensusGateway;
use App\Models\ClinicalBed;
use App\Models\ClinicalWard;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\WardCensus;
use Illuminate\Validation\ValidationException;

/**
 * CLINICAL_DRIVER=local: the census against this database's clinical_wards /
 * clinical_beds.
 *
 * The bed transitions mirror the rules Clinical enforces server-side, so the
 * board behaves the same on either driver: a bed occupied by someone else
 * cannot be taken, and retiring a bed that still holds a patient is refused
 * rather than silently emptying it.
 *
 * Note both tables ship in database/migrations/clinical, which is not on the
 * default migration path; a database that has never run
 * `php artisan migrate --path=database/migrations/clinical` will fail here.
 */
class LocalWardCensusGateway implements WardCensusGateway
{
    public function wards(ClinicalActor $actor): array
    {
        return ClinicalWard::query()
            ->where('business_id', $actor->businessId)
            ->where('is_active', true)
            ->with('beds')
            ->orderBy('ward_name')
            ->get()
            ->map(fn (ClinicalWard $ward) => WardCensus::fromModel($ward))
            ->all();
    }

    public function census(ClinicalActor $actor, string $wardCode): ?WardCensus
    {
        $ward = ClinicalWard::query()
            ->where('business_id', $actor->businessId)
            ->where('ward_code', $wardCode)
            ->with('beds')
            ->first();

        return $ward ? WardCensus::fromModel($ward) : null;
    }

    public function reserveBed(ClinicalActor $actor, int $bedId, string $patientId, ?string $visitId = null): void
    {
        $bed = $this->bed($actor, $bedId);

        if ($bed->operational_state !== ClinicalBed::STATE_AVAILABLE) {
            $this->refuse("Bed {$bed->bed_code} is not available to reserve.", $bed);
        }

        $bed->update([
            'operational_state' => ClinicalBed::STATE_RESERVED,
            'current_client_id' => $patientId,
            'current_visit_id' => $visitId,
        ]);
    }

    public function assignBed(ClinicalActor $actor, int $bedId, string $patientId, ?string $visitId = null): ?int
    {
        $bed = $this->bed($actor, $bedId);

        // Valid from AVAILABLE, or from RESERVED when it is the same patient
        // walking into the bed that was being held for them.
        $heldForSomeoneElse = $bed->operational_state === ClinicalBed::STATE_RESERVED
            && $bed->current_client_id !== null
            && (string) $bed->current_client_id !== $patientId;

        if ($bed->operational_state === ClinicalBed::STATE_OCCUPIED || $heldForSomeoneElse) {
            $this->refuse("Bed {$bed->bed_code} is already occupied by another patient.", $bed);
        }

        $bed->update([
            'operational_state' => ClinicalBed::STATE_OCCUPIED,
            'current_client_id' => $patientId,
            'current_visit_id' => $visitId,
        ]);

        // No BedMovement-equivalent row exists under this driver — Volume 8's
        // "Complete an internal transfer" is API-driver only (see
        // LocalCareTransitionsGateway), so there is nothing meaningful to
        // return here.
        return null;
    }

    public function releaseBed(ClinicalActor $actor, int $bedId): void
    {
        $this->bed($actor, $bedId)->update([
            'operational_state' => ClinicalBed::STATE_AVAILABLE,
            'current_client_id' => null,
            'current_visit_id' => null,
        ]);
    }

    public function retireBed(ClinicalActor $actor, int $bedId): void
    {
        $bed = $this->bed($actor, $bedId);

        if ($bed->operational_state !== ClinicalBed::STATE_AVAILABLE) {
            $this->refuse("Bed {$bed->bed_code} is still in use — release the patient before retiring it.", $bed);
        }

        $bed->delete();
    }

    public function addOverflowBed(ClinicalActor $actor, int $clientSpaceId, ?string $bedCode = null): void
    {
        $ward = ClinicalWard::query()
            ->where('business_id', $actor->businessId)
            ->findOrFail($clientSpaceId);

        $bedCode ??= "BED-{$ward->beds()->count()}-OVERFLOW";

        ClinicalBed::create([
            'ward_id' => $ward->id,
            'bed_code' => $bedCode,
            'operational_state' => ClinicalBed::STATE_AVAILABLE,
            'is_overflow' => true,
        ]);
    }

    public function retireVacantOverflowBeds(ClinicalActor $actor, string $wardCode): array
    {
        $ward = ClinicalWard::query()
            ->where('business_id', $actor->businessId)
            ->where('ward_code', $wardCode)
            ->with('beds')
            ->first();

        if (! $ward) {
            return ['retired_count' => 0, 'skipped_count' => 0, 'skipped' => [], 'message' => null];
        }

        $retired = 0;
        $skipped = [];

        foreach ($ward->beds as $bed) {
            if (! $bed->is_overflow) {
                // Baseline capacity is never touched, however empty the ward is.
                continue;
            }

            if ($bed->operational_state !== ClinicalBed::STATE_AVAILABLE) {
                $skipped[] = [
                    'bed_id' => $bed->id,
                    'bed_code' => $bed->bed_code,
                    'operational_state' => $bed->operational_state,
                    'current_patient_id' => $bed->current_client_id,
                    'reason' => 'Still in use — release the patient before retiring this bed.',
                ];

                continue;
            }

            $bed->delete();
            $retired++;
        }

        return [
            'retired_count' => $retired,
            'skipped_count' => count($skipped),
            'skipped' => $skipped,
            'message' => $skipped === []
                ? "Retired {$retired} overflow bed(s)."
                : "Retired {$retired} overflow bed(s); ".count($skipped).' still in use and left in place.',
        ];
    }

    private function bed(ClinicalActor $actor, int $bedId): ClinicalBed
    {
        return ClinicalBed::query()
            ->whereHas('ward', fn ($q) => $q->where('business_id', $actor->businessId))
            ->findOrFail($bedId);
    }

    /**
     * Mirrors Clinical's 422: the message is written for a clinician and the
     * board shows it verbatim.
     */
    private function refuse(string $message, ClinicalBed $bed): never
    {
        throw ValidationException::withMessages([
            'operational_state' => $message,
        ]);
    }
}
