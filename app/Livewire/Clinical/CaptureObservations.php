<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\ObservationsGateway;
use App\Models\Client;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\CdeDefinition;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
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
#[Lazy]
class CaptureObservations extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    /**
     * CDEs whose value is computed from other CDEs on this same form rather
     * than typed in directly — confirmed against clinical_module's
     * CdeRegistrySeeder/ScoringDictionariesSeeder 2026-08-26. Populated by
     * recalculateDerivedFields(), never editable by hand: a clinician editing
     * BMI directly could silently disagree with the weight/height it was
     * just computed from.
     *
     * NEWS2_TOTAL, GCS_TOTAL, APGAR_TOTAL and TRIAGE_VITAL_SCORE are *not*
     * in this list even though they are calculated indicators too — their
     * scoring models need CODE-type inputs (e.g. SUPPLEMENTAL_O2,
     * CONSCIOUSNESS_CVPU, GCS's eye/verbal/motor components) this form does
     * not render at all (data_type=NUMERIC only, see activeCdes()). Wiring
     * those up would mean rendering option fields this component has never
     * supported, not just adding a formula call — left as manual entry
     * rather than silently claiming to compute something it cannot.
     */
    private const DERIVED_CDE_CODES = ['BMI_CALCULATED', 'EGFR_CALCULATED'];

    public string $clientId;

    public ?string $visitId = null;

    /** @var array<string, string> cde_code => entered raw value */
    public array $values = [];

    /** @var array<string, int> cde_code => selected input unit id */
    public array $inputUnits = [];

    /** @var array<int, string> error messages from the last save attempt */
    public array $captureErrors = [];

    /** SRD v6.1 Phase 7 — the observation currently being corrected/marked/cancelled. */
    public int|string|null $correctingObservationId = null;

    public string $correctionAction = 'correct';

    public string $correctionReason = '';

    public string $correctionValue = '';

    public ?string $correctionError = null;

    public ?string $correctionMessage = null;

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

        // Already on record — asking the clinician to retype it invites a
        // transcription mismatch against the same patient's own chart.
        // Still an ordinary editable field afterward (a documented age can
        // legitimately be an estimate the clinician corrects at the bedside).
        $client = $this->client();

        if ($client && $client->date_of_birth) {
            $this->values['AGE_YEARS'] = (string) $client->age;
        }

        $this->recalculateDerivedFields();
    }

    /**
     * Livewire's own lifecycle hook — fires after any public property update,
     * including a nested one like values.BODY_WEIGHT. Recomputing on every
     * relevant keystroke (rather than only at Save) is what makes a
     * clinician trust the derived number actually reflects what they just
     * typed, instead of a stale value from whatever was on screen at mount.
     */
    public function updated(string $name): void
    {
        if (str_starts_with($name, 'values.')) {
            $this->recalculateDerivedFields();
        }
    }

    public function render()
    {
        $cdes = $this->activeCdes();
        $observations = $this->gateway();
        $actor = $this->actor();

        $unitOptions = [];

        foreach ($cdes as $cde) {
            // unitsForCde() returns a plain array (interface-typed); wrap it
            // here so the view can use ->count()/->first() uniformly, same as
            // $cdes below.
            $unitOptions[$cde->cde_code] = collect($observations->unitsForCde($actor, $cde->cde_code));
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

    /**
     * SRD v6.1 Phase 7. Opens the small correction form for one row of the
     * flowsheet rendered by render() above — correct()/markEnteredInError()/
     * cancel() are each terminal (a second attempt on an already-terminal
     * observation is refused server-side), so this is a one-shot action per
     * row, not an editable state.
     */
    public function beginCorrection(int|string $observationId, string $action): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);
        abort_unless(in_array($action, ['correct', 'entered-in-error', 'cancel'], true), 422);

        $this->correctingObservationId = $observationId;
        $this->correctionAction = $action;
        $this->correctionReason = '';
        $this->correctionValue = '';
        $this->correctionError = null;
    }

    public function cancelCorrection(): void
    {
        $this->correctingObservationId = null;
    }

    public function submitCorrection(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate(['correctionReason' => ['required', 'string', 'min:3']], [], ['correctionReason' => 'reason']);

        if ($this->correctingObservationId === null) {
            return;
        }

        $gateway = $this->gateway();
        $actor = $this->actor();
        $this->correctionError = null;

        try {
            match ($this->correctionAction) {
                'correct' => $gateway->correct(
                    $actor,
                    (string) $this->correctingObservationId,
                    $this->correctionReason,
                    $this->correctionValue !== '' ? ['value_numeric' => (float) $this->correctionValue] : [],
                ),
                'entered-in-error' => $gateway->markEnteredInError($actor, (string) $this->correctingObservationId, $this->correctionReason),
                'cancel' => $gateway->cancel($actor, (string) $this->correctingObservationId, $this->correctionReason),
            };
        } catch (ClinicalApiException $e) {
            $this->correctionError = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->correctionError = $e->getMessage();

            return;
        }

        $this->correctionMessage = match ($this->correctionAction) {
            'correct' => 'Correction recorded — the original is preserved and marked amended.',
            'entered-in-error' => 'Observation marked entered-in-error.',
            'cancel' => 'Observation cancelled.',
            default => 'Done.',
        };
        $this->correctingObservationId = null;
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

    private function recalculateDerivedFields(): void
    {
        $this->recalculateBmi();
        $this->recalculateEgfr();
    }

    /**
     * BMI = weight_kg / height_m² — both of BODY_WEIGHT/BODY_HEIGHT's own
     * base units already match what the formula wants, so no unit
     * conversion is needed before calling it.
     */
    private function recalculateBmi(): void
    {
        $weight = $this->values['BODY_WEIGHT'] ?? null;
        $height = $this->values['BODY_HEIGHT'] ?? null;

        if (! is_numeric($weight) || ! is_numeric($height) || (float) $height <= 0) {
            // A stale BMI from before one of these was cleared is worse than
            // no BMI at all — it would silently disagree with what is now on
            // screen.
            unset($this->values['BMI_CALCULATED']);

            return;
        }

        try {
            $result = $this->gateway()->calculateScore($this->actor(), 'BMI', [
                'weight_kg' => (float) $weight,
                'height_m' => (float) $height,
            ]);

            $this->values['BMI_CALCULATED'] = isset($result['score']) ? (string) $result['score'] : '';
        } catch (Exception $e) {
            // A failed derivation should not block charting the raw vitals
            // that are still valid on their own — leave BMI blank rather
            // than show a stale or guessed number.
            unset($this->values['BMI_CALCULATED']);
        }
    }

    /**
     * eGFR (CKD-EPI 2021) needs serum creatinine in mg/dL, but
     * CREATININE_SERUM's own base unit here is umol/L (see
     * CdeRegistrySeeder) — 1 mg/dL = 88.4 umol/L. Also needs age (already
     * on this form, prefilled from the patient's own record in mount()) and
     * sex, which is not a CDE at all — it comes from the patient's own
     * record in Main, same as the age prefill.
     */
    private function recalculateEgfr(): void
    {
        $creatinineUmolPerL = $this->values['CREATININE_SERUM'] ?? null;
        $age = $this->values['AGE_YEARS'] ?? null;

        if (! is_numeric($creatinineUmolPerL) || ! is_numeric($age)) {
            unset($this->values['EGFR_CALCULATED']);

            return;
        }

        $client = $this->client();

        if (! $client || ! $client->sex) {
            // Nothing on screen says *why* it is blank, but there is nowhere
            // in this form to say so either — this is the same fail-soft
            // posture as a Clinical-side calculation failure just below.
            unset($this->values['EGFR_CALCULATED']);

            return;
        }

        try {
            $result = $this->gateway()->calculateScore($this->actor(), 'EGFR_CKD_EPI', [
                'Scr' => round((float) $creatinineUmolPerL / 88.4, 3),
                'age' => (float) $age,
                'sex' => strtoupper($client->sex),
            ]);

            $this->values['EGFR_CALCULATED'] = isset($result['score']) ? (string) $result['score'] : '';
        } catch (Exception $e) {
            unset($this->values['EGFR_CALCULATED']);
        }
    }

    private function client(): ?Client
    {
        return Client::where('business_id', $this->actor()->businessId)
            ->where('client_id', $this->clientId)
            ->first();
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
