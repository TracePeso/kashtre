<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\DiagnosticReportStatusGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 8 — diagnostic report status corrections. Operates on a
 * report id already known from elsewhere on the chart (the live LIMS/RIS
 * result view) — there is no new "list reports" endpoint in this pass, same
 * shape as Care Transitions' discharge-document flow.
 */
#[Lazy]
class DiagnosticReportCorrectionsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $reportId = '';

    public string $action = 'correct';

    public string $reason = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Diagnoses', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        return view('livewire.clinical.diagnostic-report-corrections-panel');
    }

    public function submit(): void
    {
        abort_unless(in_array('Add Clinical Diagnoses', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'reportId' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:3'],
            'action' => ['required', 'in:correct,entered-in-error,cancel'],
        ], [], ['reportId' => 'report id']);

        $gateway = app(DiagnosticReportStatusGateway::class);
        $actor = $this->actor();
        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            match ($this->action) {
                'correct' => $gateway->correct($actor, $this->reportId, $this->reason),
                'entered-in-error' => $gateway->markEnteredInError($actor, $this->reportId, $this->reason),
                'cancel' => $gateway->cancel($actor, $this->reportId, $this->reason),
            };
        } catch (ClinicalApiException $e) {
            // e.g. 422 REPORT_STATUS_TERMINAL if this report already has a
            // terminal status from a prior correction/EIE/cancel.
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = match ($this->action) {
            'correct' => 'Correction recorded — the original report is preserved and marked amended.',
            'entered-in-error' => 'Report marked entered-in-error.',
            'cancel' => 'Report cancelled.',
            default => 'Done.',
        };
        $this->reset(['reportId', 'reason']);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
