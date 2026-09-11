<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\MedicationAdverseEventGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 6 — adverse drug reaction / medication error / near-miss
 * reporting. Reports something that already happened, distinct from the
 * reconciliation panel's what-should-the-chart-say decisions.
 */
#[Lazy]
class MedicationAdverseEventsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $eventType = MedicationAdverseEventGateway::TYPE_ADVERSE_DRUG_REACTION;

    public string $description = '';

    public string $severity = 'MODERATE';

    /** @var array<string, string> eventId => the response/close text being drafted */
    public array $stepInput = [];

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $events = [];

        try {
            $events = app(MedicationAdverseEventGateway::class)->forPatient($this->actor(), $this->clientId);
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load adverse events — Clinical may be unreachable.';
        }

        return view('livewire.clinical.medication-adverse-events-panel', [
            'events' => $events,
            'eventTypes' => [
                MedicationAdverseEventGateway::TYPE_ADVERSE_DRUG_REACTION => 'Adverse drug reaction',
                MedicationAdverseEventGateway::TYPE_SIDE_EFFECT => 'Side effect',
                MedicationAdverseEventGateway::TYPE_MEDICATION_ERROR => 'Medication error',
                MedicationAdverseEventGateway::TYPE_NEAR_MISS => 'Near miss',
                MedicationAdverseEventGateway::TYPE_THERAPEUTIC_FAILURE => 'Therapeutic failure',
            ],
        ]);
    }

    public function report(): void
    {
        abort_unless(in_array('Administer MAR Doses', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'description' => ['required', 'string', 'min:5'],
            'severity' => ['required', 'string'],
        ]);

        $this->errorMessage = null;

        try {
            app(MedicationAdverseEventGateway::class)->report($this->actor(), $this->clientId, $this->visitId, [
                'event_type' => $this->eventType,
                'description' => $this->description,
                'severity' => $this->severity,
                'reported_by_user_id' => Auth::id(),
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->reset(['description']);
        $this->resultMessage = 'Adverse event reported.';
    }

    public function recordResponse(string $eventId): void
    {
        abort_unless(in_array('Administer MAR Doses', Auth::user()->permissions ?? []), 403);

        $text = trim($this->stepInput[$eventId] ?? '');

        if ($text === '') {
            $this->errorMessage = 'Enter the clinical response first.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(MedicationAdverseEventGateway::class)->recordResponse($this->actor(), $eventId, $text);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        unset($this->stepInput[$eventId]);
        $this->resultMessage = 'Response recorded.';
    }

    public function escalate(string $eventId): void
    {
        abort_unless(in_array('Administer MAR Doses', Auth::user()->permissions ?? []), 403);

        $this->errorMessage = null;

        try {
            app(MedicationAdverseEventGateway::class)->escalate($this->actor(), $eventId);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Escalated for review.';
    }

    public function close(string $eventId): void
    {
        abort_unless(in_array('Administer MAR Doses', Auth::user()->permissions ?? []), 403);

        $outcome = trim($this->stepInput[$eventId] ?? '');

        if ($outcome === '') {
            $this->errorMessage = 'Enter the outcome first.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(MedicationAdverseEventGateway::class)->close($this->actor(), $eventId, $outcome);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        unset($this->stepInput[$eventId]);
        $this->resultMessage = 'Event closed.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
