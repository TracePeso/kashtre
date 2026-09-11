<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\TriageGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD §4.1.1 OPD intake and triage acuity. Chart the vitals through
 * CaptureObservations above this panel first — triage scores whatever is
 * already on the chart, it does not capture anything itself.
 */
#[Lazy]
class TriageAssessment extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    /** Score what's charted without pushing the priority colour to the queue. */
    public function preview(): void
    {
        $this->run(announce: false);
    }

    /** Score and push the priority colour to the universal queue. */
    public function assess(): void
    {
        $this->run(announce: true);
    }

    private function run(bool $announce): void
    {
        if (! $this->visitId) {
            $this->errorMessage = 'No visit is open for this patient.';

            return;
        }

        $this->errorMessage = null;

        try {
            $this->result = app(TriageGateway::class)->assess(
                $this->actor(),
                $this->clientId,
                $this->visitId,
                $announce,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();
            $this->result = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->result = null;
        }
    }

    public function render()
    {
        return view('livewire.clinical.triage-assessment');
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
