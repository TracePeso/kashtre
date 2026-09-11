<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\HandoverGateway;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * SRD §2.2 Handover Task Routing — the end-of-shift rollup: every patient
 * the clinician (or their team, or a named ward) owns, sickest first, with
 * what is outstanding. Talks only to HandoverGateway, so it works unchanged
 * under either CLINICAL_DRIVER — the local driver's rollup is honestly
 * thinner (see LocalHandoverGateway), not a lie dressed up to look the same.
 */
class ShiftHandoverBoard extends Component
{
    public string $scope = HandoverGateway::SCOPE_MY_PATIENTS;

    public string $wardCode = '';

    public function mount(): void
    {
        abort_unless(in_array('View Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $wardCode = $this->wardCode !== '' ? $this->wardCode : null;

        if ($this->scope === HandoverGateway::SCOPE_MY_WARD && $wardCode === null) {
            return view('livewire.clinical.shift-handover-board', [
                'result' => null,
                'needsWard' => true,
            ]);
        }

        return view('livewire.clinical.shift-handover-board', [
            'result' => app(HandoverGateway::class)->compile($this->actor(), $this->scope, $wardCode),
            'needsWard' => false,
        ]);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
