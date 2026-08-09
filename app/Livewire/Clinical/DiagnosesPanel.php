<?php

namespace App\Livewire\Clinical;

use App\Models\ClinicalCondition;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DiagnosesPanel extends Component
{
    public string $clientId;

    public ?string $visitId = null;

    public string $icd11Code = '';

    public string $description = '';

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Diagnoses', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        return view('livewire.clinical.diagnoses-panel', [
            'conditions' => ClinicalCondition::where('business_id', Auth::user()->business_id)
                ->where('client_id', $this->clientId)
                ->orderByDesc('recorded_at')
                ->get(),
        ]);
    }

    public function addCondition(): void
    {
        abort_unless(in_array('Add Clinical Diagnoses', Auth::user()->permissions ?? []), 403);

        $this->validate(['description' => ['required', 'string']]);

        $user = Auth::user();

        ClinicalCondition::create([
            'business_id' => $user->business_id,
            'branch_id' => $user->branch_id,
            'client_id' => $this->clientId,
            'visit_id' => $this->visitId,
            'icd11_code' => $this->icd11Code ?: null,
            'description' => $this->description,
            'recorded_by_user_id' => $user->id,
            'recorded_at' => now(),
        ]);

        $this->reset(['icd11Code', 'description']);
    }
}
