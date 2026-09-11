<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\InteroperabilityGateway;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * v6.1 Volume 12 — read-only. Creating an exchange job or running a quality
 * measure is not exposed; see InteroperabilityController's docblock (an
 * unbound policy-decision contract, and clinical quality-measure logic
 * neither this host nor Main should fabricate under time pressure).
 */
class InteroperabilityPanel extends Component
{
    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(in_array('View Clinical Module', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $actor = $this->actor();
        $exchangeJobs = [];
        $measureVersions = [];
        $measureRuns = [];

        try {
            $gateway = app(InteroperabilityGateway::class);
            $exchangeJobs = $gateway->exchangeJobs($actor);
            $measureVersions = $gateway->measureVersions($actor);
            $measureRuns = $gateway->measureRuns($actor);
        } catch (Exception $e) {
            $this->errorMessage = 'Could not load interoperability data — Clinical may be unreachable.';
        }

        return view('livewire.clinical.interoperability-panel', compact('exchangeJobs', 'measureVersions', 'measureRuns'));
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
