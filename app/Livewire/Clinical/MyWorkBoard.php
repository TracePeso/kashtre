<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\TaskVisibilityGateway;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * §10.5's four localised task views, with scope switching and the
 * location/clinical filters that intersect — none of which
 * MyPatientTasksBoard exposed (it only ever asked "where are my patients").
 * MY_CURRENT_WORK is the one worth naming: "what must I personally do",
 * including on a patient someone else owns.
 */
class MyWorkBoard extends Component
{
    public string $scope = TaskVisibilityGateway::SCOPE_MY_PATIENTS;

    public string $wardCode = '';

    public string $specialty = '';

    public string $pathwayCode = '';

    public string $roomNumber = '';

    public function mount(): void
    {
        abort_unless(in_array('View Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $wardCode = $this->wardCode !== '' ? $this->wardCode : null;

        if ($this->scope === TaskVisibilityGateway::SCOPE_MY_WARD && $wardCode === null) {
            return view('livewire.clinical.my-work-board', ['result' => null, 'needsWard' => true]);
        }

        $result = app(TaskVisibilityGateway::class)->filtered($this->actor(), $this->scope, array_filter([
            'ward_code' => $wardCode,
            'specialty' => $this->specialty ?: null,
            'pathway_code' => $this->pathwayCode ?: null,
            'room_number' => $this->roomNumber ?: null,
        ]));

        return view('livewire.clinical.my-work-board', ['result' => $result, 'needsWard' => false]);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
