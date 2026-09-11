<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\DiagnosesGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class DiagnosesPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $icd11Code = '';

    public string $description = '';

    public ?string $panelError = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Diagnoses', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        return view('livewire.clinical.diagnoses-panel', [
            'conditions' => $this->gateway()->forPatient($this->actor(), $this->clientId),
            'requiresIcd11' => ! $this->gateway()->allowsUncodedDiagnosis(),
        ]);
    }

    public function addCondition(): void
    {
        abort_unless(in_array('Add Clinical Diagnoses', Auth::user()->permissions ?? []), 403);

        // Clinical requires a coded diagnosis and a visit; validating here turns
        // a server-side 422 into a field error next to the input that caused it.
        $rules = ['description' => ['required', 'string']];

        if (! $this->gateway()->allowsUncodedDiagnosis()) {
            $rules['icd11Code'] = ['required', 'string'];
            $rules['visitId'] = ['required', 'string'];
        }

        $this->validate($rules, [], [
            'icd11Code' => 'ICD-11 code',
            'visitId' => 'visit ID',
            'description' => 'description',
        ]);

        $this->panelError = null;

        try {
            $this->gateway()->record(
                $this->actor(),
                $this->clientId,
                $this->description,
                $this->icd11Code ?: null,
                $this->visitId,
            );
        } catch (ClinicalApiException $e) {
            // Never swallow this: a clinician who typed a diagnosis and saw the
            // form clear would believe it was on the chart.
            $fieldErrors = collect($e->errors())->filter(fn ($v) => is_array($v))->flatten();
            $this->panelError = $fieldErrors->isNotEmpty() ? $fieldErrors->first() : $e->getMessage();

            return;
        }

        $this->reset(['icd11Code', 'description']);
    }

    private function gateway(): DiagnosesGateway
    {
        return app(DiagnosesGateway::class);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
