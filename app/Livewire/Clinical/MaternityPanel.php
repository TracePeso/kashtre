<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\MaternityGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * The birth event, on the mother's chart — API Integration Guide §10.8.
 * One infant per submission here; a multiple birth is recorded as
 * repeated infants in the same array on Clinical's side, but this form
 * keeps to the common single-infant case for now.
 */
#[Lazy]
class MaternityPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $deliveryAt = '';

    public string $deliveryModeCode = '';

    public string $presentationCode = '';

    public string $maternalOutcomeCode = '';

    public string $gestationWeeks = '';

    public string $deliveryNotes = '';

    public string $infantSex = 'FEMALE';

    public string $infantOutcome = 'LIVE_BIRTH';

    public string $infantWeight = '';

    public ?string $errorMessage = null;

    public ?string $statusMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
        $this->deliveryAt = now()->format('Y-m-d\TH:i');
    }

    public function render()
    {
        $gateway = app(MaternityGateway::class);

        return view('livewire.clinical.maternity-panel', [
            'options' => $gateway->options($this->actor()),
            'birthEvents' => $gateway->forPatient($this->actor(), $this->clientId),
        ]);
    }

    public function recordBirth(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'deliveryAt' => ['required', 'string'],
            'deliveryModeCode' => ['required', 'string'],
            'infantSex' => ['required', 'string'],
            'infantOutcome' => ['required', 'string'],
        ]);

        if (! $this->visitId) {
            $this->errorMessage = 'No visit is open for this patient.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(MaternityGateway::class)->recordBirth(
                $this->actor(),
                $this->clientId,
                $this->visitId,
                \Illuminate\Support\Carbon::parse($this->deliveryAt)->toIso8601String(),
                $this->deliveryModeCode,
                [array_filter([
                    'sex' => $this->infantSex,
                    'birth_outcome' => $this->infantOutcome,
                    'birth_weight_value' => $this->infantWeight !== '' ? (float) $this->infantWeight : null,
                ], fn ($v) => $v !== null)],
                $this->gestationWeeks !== '' ? (float) $this->gestationWeeks : null,
                $this->presentationCode ?: null,
                $this->maternalOutcomeCode ?: null,
                $this->deliveryNotes ?: null,
            );
        } catch (ClinicalApiException $e) {
            // Clinical's generic top-level message ("The given data was
            // invalid.") never says which field — the per-field detail in
            // errors() is what actually tells a clinician what to fix (this
            // is how the AMBIGUOUS/INDETERMINATE mismatch above went
            // undiagnosed: the panel had been throwing away exactly the
            // message that would have named infants.0.sex).
            $fieldErrors = collect($e->errors())->filter(fn ($v) => is_array($v))->flatten();
            $this->errorMessage = $fieldErrors->isNotEmpty() ? $fieldErrors->first() : $e->getMessage();

            return;
        }

        $this->statusMessage = 'Birth event recorded.';
        $this->reset(['presentationCode', 'maternalOutcomeCode', 'gestationWeeks', 'deliveryNotes', 'infantWeight']);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
