<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\ScratchpadGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\SuggestedObservation;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * SRD's "Bedside Rough Notes Scratchpad."
 *
 * Manual entry (Save as Note) always works with zero dependency on the AI
 * Gateway — extraction is strictly additive, and every proposed observation
 * requires an explicit clinician click to commit rather than being
 * auto-charted (API Integration Guide §10.7).
 *
 * Real-time STT streaming is not built. It needs a gateway-issued session
 * token, and §14 is explicit that the module API key must not be put in a
 * browser to get one.
 *
 * Suggestions are held in component state between extraction and commit. The
 * `id` on each is opaque — a Clinical suggestion-item id under the API driver,
 * an array index under the local one — and is only ever passed back.
 */
class BedsideScratchpad extends Component
{
    public string $clientId;

    public ?string $visitId = null;

    public string $scratchpadText = '';

    /** @var array<int, array<string, mixed>> serialised SuggestedObservations awaiting a decision */
    public array $proposedObservations = [];

    public ?string $aiError = null;

    public ?string $resultMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $gateway = $this->gateway();

        return view('livewire.clinical.bedside-scratchpad', [
            'aiAvailable' => $gateway->isAiAvailable(),
            'recentNotes' => collect($gateway->recentNotes($this->actor(), $this->clientId)),
        ]);
    }

    /**
     * The manual-entry fallback — always available, no gateway involved.
     */
    public function saveAsNote(): void
    {
        $this->validate(['scratchpadText' => ['required', 'string']]);

        $this->gateway()->saveNote($this->actor(), $this->clientId, $this->visitId, $this->scratchpadText);

        $this->scratchpadText = '';
        $this->resultMessage = 'Note saved.';
    }

    public function extractWithAi(): void
    {
        $this->validate(['scratchpadText' => ['required', 'string']]);

        try {
            $suggestions = $this->gateway()->extractObservations(
                $this->actor(),
                $this->clientId,
                $this->visitId,
                $this->scratchpadText,
            );
        } catch (ClinicalApiException $e) {
            $this->aiError = $e->getMessage();
            $this->proposedObservations = [];

            return;
        }

        if ($suggestions === []) {
            $this->aiError = 'AI extraction returned nothing — use Save as Note instead.';
            $this->proposedObservations = [];

            return;
        }

        $this->aiError = null;
        $this->proposedObservations = array_map(
            fn (SuggestedObservation $item) => (array) $item,
            $suggestions,
        );
    }

    /**
     * Explicit per-item commit. Never auto-chart an extraction — and note the
     * committed value still runs the physiological guard, so this can fail.
     */
    public function commitObservation(int $index): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $item = $this->suggestionAt($index);

        if (! $item) {
            return;
        }

        try {
            $this->gateway()->acceptSuggestion($this->actor(), $this->clientId, $this->visitId, $item);

            $this->forget($index);
            $this->resultMessage = "Committed {$item->cde_code}.";
        } catch (ClinicalApiException|Exception $e) {
            $this->aiError = "Could not commit {$item->cde_code}: {$e->getMessage()}";
        }
    }

    public function rejectObservation(int $index): void
    {
        $item = $this->suggestionAt($index);

        if (! $item) {
            return;
        }

        try {
            $this->gateway()->rejectSuggestion($this->actor(), $item);
        } catch (ClinicalApiException $e) {
            // A failed reject is not worth blocking the clinician over — the
            // suggestion was never charted, so dropping it from the UI is the
            // outcome they asked for either way.
            $this->aiError = null;
        }

        $this->forget($index);
    }

    private function suggestionAt(int $index): ?SuggestedObservation
    {
        $raw = $this->proposedObservations[$index] ?? null;

        if (! is_array($raw)) {
            return null;
        }

        return new SuggestedObservation(
            id: $raw['id'] ?? $index,
            cde_code: (string) ($raw['cde_code'] ?? ''),
            value: $raw['value'] ?? null,
            unit: $raw['unit'] ?? null,
            display_label: $raw['display_label'] ?? null,
            cde_resolved: (bool) ($raw['cde_resolved'] ?? true),
            unit_resolved: (bool) ($raw['unit_resolved'] ?? true),
        );
    }

    private function forget(int $index): void
    {
        unset($this->proposedObservations[$index]);
        $this->proposedObservations = array_values($this->proposedObservations);
    }

    private function gateway(): ScratchpadGateway
    {
        return app(ScratchpadGateway::class);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
