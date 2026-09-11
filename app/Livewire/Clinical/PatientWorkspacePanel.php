<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\PatientWorkspaceGateway;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 2 — the general-purpose "open a patient's chart"
 * projection: banner + longitudinal timeline. Distinct from
 * GET /clinical/handover and ward-census (a shift/ward view) — this is
 * one patient's own workspace. "confidentiality.restricted" is
 * deliberately rendered as a bare flag, never the label/reason
 * (CLN-P2-BNR-004) — mirroring what Clinical itself withholds.
 */
#[Lazy]
class PatientWorkspacePanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $banner = null;
        $timeline = [];

        try {
            $banner = app(PatientWorkspaceGateway::class)->banner($this->actor(), $this->clientId, $this->visitId, Auth::id());
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load the patient banner — Clinical may be unreachable.';
        }

        try {
            $timeline = app(PatientWorkspaceGateway::class)->timeline($this->actor(), $this->clientId, $this->visitId, 50);
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load the patient timeline — Clinical may be unreachable.';
        }

        return view('livewire.clinical.patient-workspace-panel', [
            'banner' => $banner,
            'timeline' => $timeline,
        ]);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
