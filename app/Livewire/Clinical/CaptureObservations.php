<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\ObservationsGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\CdeDefinition;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * SRD §1: dynamic bedside charting form driven entirely by the CDE registry —
 * no hardcoded vitals fields.
 *
 * Talks only to ObservationsGateway / CareAccessGateway, so it works
 * unchanged whether the clinical data lives in this database
 * (CLINICAL_DRIVER=local) or in the Clinical Module (CLINICAL_DRIVER=api).
 *
 * The permission checks below stay as UI gating. Under the API driver the
 * real authority is Clinical's ReBAC gate, which re-runs on every call — a
 * check performed here is a courtesy to the user, not a security control.
 */
class CaptureObservations extends Component
{
    public string $clientId;

    public ?string $visitId = null;

    /** @var array<string, string> cde_code => entered raw value */
    public array $values = [];

    /** @var array<string, int> cde_code => selected input unit id */
    public array $inputUnits = [];

    /** @var array<int, string> error messages from the last save attempt */
    public array $captureErrors = [];

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;

        foreach ($this->activeCdes() as $cde) {
            if ($cde->base_uom_id !== null) {
                $this->inputUnits[$cde->cde_code] = $cde->base_uom_id;
            }
        }
    }

    public function render()
    {
        $cdes = $this->activeCdes();
        $observations = $this->gateway();
        $actor = $this->actor();

        $unitOptions = [];

        foreach ($cdes as $cde) {
            $unitOptions[$cde->cde_code] = $observations->unitsForCde($actor, $cde->cde_code);
        }

        return view('livewire.clinical.capture-observations', [
            'cdes' => collect($cdes),
            'unitOptionsByCde' => collect($unitOptions),
            'recentObservations' => collect($observations->recentForPatient($actor, $this->clientId)),
            'hasActiveRelationship' => app(CareAccessGateway::class)
                ->hasActiveRelationship($actor, $this->clientId),
        ]);
    }

    public function save(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $gateway = $this->gateway();
        $actor = $this->actor();

        $this->captureErrors = [];
        $captured = 0;

        foreach ($this->values as $cdeCode => $rawValue) {
            if ($rawValue === '' || $rawValue === null) {
                continue;
            }

            try {
                $gateway->capture($actor, $this->clientId, $this->visitId, [
                    'cde_code' => $cdeCode,
                    'value_numeric' => (float) $rawValue,
                    'input_uom_id' => $this->inputUnits[$cdeCode] ?? null,
                ]);

                $captured++;
            } catch (ClinicalApiException $e) {
                // Clinical writes these messages for a clinician to read —
                // an implausible value names the bound it breached, which is
                // usually enough to spot the unit confusion that caused it.
                $this->captureErrors[] = "{$cdeCode}: {$e->getMessage()}";
            } catch (Exception $e) {
                $this->captureErrors[] = "{$cdeCode}: {$e->getMessage()}";
            }
        }

        // Only clear the fields that were actually accepted, so a refused
        // value stays on screen for the clinician to correct rather than
        // vanishing along with the successful ones.
        if ($captured > 0 && $this->captureErrors === []) {
            $this->values = [];
        }
    }

    public function claim(string $role): void
    {
        abort_unless(in_array('Manage Care Assignments', Auth::user()->permissions ?? []), 403);
        abort_unless(in_array($role, ['doctor', 'nurse'], true), 422);

        // Claiming a patient in a clinical capacity this user does not
        // actually hold should not be self-service.
        $rolePermission = $role === 'doctor' ? 'Act As Consultant (Clinical)' : 'Act As Ward Nurse (Clinical)';
        abort_unless(in_array($rolePermission, Auth::user()->permissions ?? []), 403);

        app(CareAccessGateway::class)->claim($this->actor(), $role, $this->clientId, $this->visitId);
    }

    /**
     * @return array<int, CdeDefinition>
     */
    private function activeCdes(): array
    {
        // Chunk 2 MVP scope: NUMERIC CDEs only, the only data_type seeded so
        // far. TEXT/BOOLEAN/CODE/MULTI_COMPONENT rendering waits until such
        // CDEs are actually registered.
        return $this->gateway()->activeCdes($this->actor(), 'NUMERIC');
    }

    private function gateway(): ObservationsGateway
    {
        return app(ObservationsGateway::class);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
