<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * Bedside scratchpad and AI assistance — API Integration Guide §10.7.
 *
 * The governing rule, and the reason this is shaped as propose-then-accept
 * rather than a single call: **nothing the AI gateway returns reaches the
 * chart until a named clinician accepts it, item by item.** Accepted items
 * then run the same deterministic checks as anything typed by hand — an
 * AI-extracted implausible value is refused by the physiological guard, an
 * AI-suggested drug runs the CDSS shield.
 *
 * Build per-item accept/reject. Do not auto-commit.
 *
 * Everything here answers 503 until the AI gateway is configured (§14).
 */
class AiResource extends ClinicalResource
{
    // ---------------------------------------------------------------- scratchpad

    /**
     * @param  array{patient_id: string, visit_id?: ?string, content: string, source?: string}  $payload
     */
    public function createNote(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/scratchpad', $this->filled($payload), $options);
    }

    /**
     * Requests a short-lived token for browser-side speech-to-text.
     *
     * §14 lists voice dictation as blocked: it needs a gateway-issued session
     * token, and the module API key must never be put in a browser to get one.
     * The endpoint exists so the flow can be wired ahead of that.
     */
    public function dictationSession(array $payload = [], array $options = []): array
    {
        return $this->client->post('clinical/scratchpad/dictation-session', $this->filled($payload), $options);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function notesForPatient(string $patientId, array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/scratchpad", $this->filled($query), $options),
            'notes'
        );
    }

    public function showNote(int|string $noteId, array $options = []): array
    {
        return $this->client->get("clinical/scratchpad/{$noteId}", [], $options);
    }

    public function updateNote(int|string $noteId, array $payload, array $options = []): array
    {
        return $this->client->patch("clinical/scratchpad/{$noteId}", $this->filled($payload), $options);
    }

    public function discardNote(int|string $noteId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/scratchpad/{$noteId}/discard",
            $this->filled($payload),
            $this->idempotent("scratchpad-{$noteId}-discard", $options),
        );
    }

    /**
     * Proposes structured observations from the note's free text. Charts
     * nothing — every item comes back requiring clinician validation.
     */
    public function extractObservations(int|string $noteId, array $options = []): array
    {
        return $this->client->post("clinical/scratchpad/{$noteId}/extract-observations", [], $options);
    }

    /**
     * Proposes *actions* rather than values — an order the note implies, a
     * referral the clinician described. Same accept/reject discipline applies,
     * and an accepted order still runs the CDSS shield.
     */
    public function extractIntent(int|string $noteId, array $options = []): array
    {
        return $this->client->post("clinical/scratchpad/{$noteId}/extract-intent", [], $options);
    }

    // ---------------------------------------------------------------- assistance

    /**
     * Proposes ICD-11 codes for a clinical description. Suggestions, not
     * assignments — a coded diagnosis still goes through chart()->recordDiagnosis().
     */
    public function suggestIcd11(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/ai/icd11-suggest', $this->filled($payload), $options);
    }

    public function summarizeObservations(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/ai/summarize-observations', $this->filled($payload), $options);
    }

    public function recommendProtocol(array $payload, array $options = []): array
    {
        return $this->client->post('clinical/ai/recommend-protocol', $this->filled($payload), $options);
    }

    // ---------------------------------------------------------------- suggestions

    /**
     * @return array<int, array<string, mixed>>
     */
    public function suggestionsForPatient(string $patientId, array $query = [], array $options = []): array
    {
        return $this->rows(
            $this->client->get("clinical/patients/{$patientId}/ai-suggestions", $this->filled($query), $options),
            'suggestions'
        );
    }

    public function showSuggestion(int|string $suggestionId, array $options = []): array
    {
        return $this->client->get("clinical/ai-suggestions/{$suggestionId}", [], $options);
    }

    /**
     * Marks the whole suggestion set as seen. Distinct from accepting its
     * items — acknowledging charts nothing.
     */
    public function acknowledgeSuggestion(int|string $suggestionId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/ai-suggestions/{$suggestionId}/acknowledge",
            $this->filled($payload),
            $this->idempotent("ai-suggestion-{$suggestionId}-ack", $options),
        );
    }

    /** Charts one item. Still subject to the physiological guard and CDSS. */
    public function acceptItem(int|string $itemId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/ai-suggestions/items/{$itemId}/accept",
            $this->filled($payload),
            $this->idempotent("ai-suggestion-accept-{$itemId}", $options),
        );
    }

    public function rejectItem(int|string $itemId, ?string $note = null, array $options = []): array
    {
        return $this->client->post(
            "clinical/ai-suggestions/items/{$itemId}/reject",
            $this->filled(['note' => $note]),
            $this->idempotent("ai-suggestion-reject-{$itemId}", $options),
        );
    }
}
