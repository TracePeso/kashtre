<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\EncounterSectionGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * MDT co-signing of the shared encounter workspace — API Integration Guide
 * §10.1. Each specialist attests to their own section of the same
 * encounter concurrently, rather than one clinician charting for everyone.
 */
#[Lazy]
class EncounterSectionsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $sectionCode = '';

    public string $sectionName = '';

    public string $attestationNote = '';

    public bool $showWithdrawn = false;

    public string $withdrawReason = '';

    public ?string $errorMessage = null;

    public ?string $statusMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        return view('livewire.clinical.encounter-sections-panel', [
            'sections' => app(EncounterSectionGateway::class)->forPatient(
                $this->actor(),
                $this->clientId,
                $this->visitId,
                $this->showWithdrawn,
            ),
        ]);
    }

    public function sign(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'sectionCode' => ['required', 'string'],
            'sectionName' => ['required', 'string'],
        ]);

        if (! $this->visitId) {
            $this->errorMessage = 'No visit is open for this patient.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(EncounterSectionGateway::class)->sign(
                $this->actor(),
                $this->clientId,
                $this->visitId,
                $this->sectionCode,
                $this->sectionName,
                $this->attestationNote ?: null,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = 'Section signed.';
        $this->reset(['sectionCode', 'sectionName', 'attestationNote']);
    }

    public function withdraw(int|string $signatureId): void
    {
        $this->validate(['withdrawReason' => ['required', 'string']]);

        $this->errorMessage = null;

        try {
            app(EncounterSectionGateway::class)->withdraw($this->actor(), $this->clientId, $signatureId, $this->withdrawReason);
        } catch (ClinicalApiException $e) {
            // Only the signatory may withdraw their own signature — this is
            // the refusal a colleague trying to retract someone else's
            // attestation should read plainly, not a generic failure.
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = 'Signature withdrawn.';
        $this->withdrawReason = '';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
