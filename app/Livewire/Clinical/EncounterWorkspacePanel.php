<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\EncounterGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 2 — the encounter-workspace record layered on top of
 * Main's own visit_id (unaffected, unchanged — this is additive). Create
 * → status transitions → closure checks → close → reopen.
 */
#[Lazy]
class EncounterWorkspacePanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public ?string $activeEncounterId = null;

    public string $encounterClass = EncounterGateway::CLASS_INPATIENT;

    public string $service = '';

    public string $facilityId = '';

    public string $newStatus = '';

    public string $statusReason = '';

    public string $reopenReason = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public ?array $closureChecks = null;

    private const CLASSES = [
        EncounterGateway::CLASS_OUTPATIENT => 'Outpatient',
        EncounterGateway::CLASS_EMERGENCY => 'Emergency',
        EncounterGateway::CLASS_INPATIENT => 'Inpatient',
        EncounterGateway::CLASS_DAY_CASE => 'Day case',
        EncounterGateway::CLASS_VIRTUAL => 'Virtual',
        EncounterGateway::CLASS_HOME_COMMUNITY => 'Home / community',
        EncounterGateway::CLASS_OBSERVATION => 'Observation',
    ];

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $encounters = [];
        $active = null;

        try {
            $encounters = app(EncounterGateway::class)->forPatient($this->actor(), $this->clientId);
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load encounters — Clinical may be unreachable.';
        }

        if ($this->activeEncounterId) {
            try {
                $active = app(EncounterGateway::class)->show($this->actor(), $this->activeEncounterId);
            } catch (Exception $e) {
                $this->errorMessage ??= 'Could not refresh the selected encounter.';
            }
        }

        return view('livewire.clinical.encounter-workspace-panel', [
            'encounters' => $encounters,
            'active' => $active,
            'classes' => self::CLASSES,
        ]);
    }

    public function create(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate(['encounterClass' => ['required', 'string']]);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            $record = app(EncounterGateway::class)->create($this->actor(), [
                'patient_id' => $this->clientId,
                'visit_id' => $this->visitId,
                'encounter_class' => $this->encounterClass,
                'service' => $this->service ?: null,
                'facility_id' => $this->facilityId ?: null,
                'responsible_clinician_id' => Auth::id(),
            ]);
        } catch (ClinicalApiException $e) {
            // e.g. 422 ENCOUNTER_ALREADY_EXISTS
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->activeEncounterId = (string) ($record['id'] ?? $record['public_id'] ?? '');
        $this->resultMessage = ($record['minimum_data_pending'] ?? false)
            ? 'Encounter created — flagged as minimum-data-pending (service/reason not yet given).'
            : 'Encounter created.';
        $this->reset(['service', 'facilityId']);
    }

    public function selectEncounter(string $encounterId): void
    {
        $this->activeEncounterId = $encounterId;
        $this->closureChecks = null;
        $this->resultMessage = null;
        $this->errorMessage = null;
    }

    public function transition(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate(['newStatus' => ['required', 'string']]);

        if (! $this->activeEncounterId) {
            return;
        }

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(EncounterGateway::class)->transition($this->actor(), $this->activeEncounterId, $this->newStatus, $this->statusReason ?: null);
        } catch (ClinicalApiException $e) {
            // e.g. 422 ILLEGAL_ENCOUNTER_TRANSITION, with the permitted array in the message
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = "Status changed to {$this->newStatus}.";
        $this->reset(['newStatus', 'statusReason']);
    }

    public function checkClosure(): void
    {
        if (! $this->activeEncounterId) {
            return;
        }

        $this->errorMessage = null;

        try {
            $this->closureChecks = app(EncounterGateway::class)->closureChecks($this->actor(), $this->activeEncounterId);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function close(bool $override = false): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        if (! $this->activeEncounterId) {
            return;
        }

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(EncounterGateway::class)->close($this->actor(), $this->activeEncounterId, $override);
        } catch (ClinicalApiException $e) {
            // e.g. 422 ENCOUNTER_CLOSURE_ITEMS_OUTSTANDING or ENCOUNTER_NOT_FINISHED
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Encounter closed.';
        $this->closureChecks = null;
    }

    public function reopen(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate(['reopenReason' => ['required', 'string', 'min:3']], [], ['reopenReason' => 'reason']);

        if (! $this->activeEncounterId) {
            return;
        }

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(EncounterGateway::class)->reopen($this->actor(), $this->activeEncounterId, $this->reopenReason);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        // The original closure event is preserved, never erased — this
        // just moves the encounter back to IN_PROGRESS alongside it.
        $this->resultMessage = 'Encounter reopened — the original closure record is preserved, not erased.';
        $this->reset(['reopenReason']);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
