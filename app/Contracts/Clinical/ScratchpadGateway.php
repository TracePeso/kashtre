<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ObservationRecord;
use App\Support\Clinical\SuggestedObservation;

/**
 * Bedside scratchpad and AI-assisted extraction — API Integration Guide §10.7.
 *
 * The direction of the AI call inverts in the split. Today Main calls the AI
 * Gateway directly through AiGatewayClientService. Under CLINICAL_DRIVER=api,
 * Clinical calls the gateway and stages the results as suggestions, and Main
 * holds no gateway credentials at all — which is the right outcome, because
 * an extracted observation has to run Clinical's physiological guard before it
 * can be charted, and only Clinical can do that.
 *
 * Two invariants hold on both sides:
 *
 *  - saveNote() never depends on the AI gateway. Manual charting must work
 *    when the gateway is down, or a nurse with a degraded network has no way
 *    to record anything.
 *  - accept() is per item and explicit. Never auto-commit an extraction.
 */
interface ScratchpadGateway
{
    /**
     * Whether AI extraction can be offered at all. False is an ordinary
     * state — the gateway is unconfigured (§14) — and the UI should fall back
     * to manual entry rather than showing an error.
     */
    public function isAiAvailable(): bool;

    /**
     * @return array<int, ObservationRecord>
     */
    public function recentNotes(ClinicalActor $actor, string $patientId, int $limit = 5): array;

    /**
     * Records free text as a note. Always available, no AI involved.
     *
     * @return int|string an opaque note handle, for extracting from it later
     */
    public function saveNote(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $content,
    ): int|string;

    /**
     * Proposes structured observations from free text. Nothing is charted.
     *
     * @param  int|string|null  $noteId  extract from a saved note; null extracts from $content directly
     * @return array<int, SuggestedObservation>
     */
    public function extractObservations(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $content,
        int|string|null $noteId = null,
    ): array;

    /**
     * Charts one proposed observation. Runs the same physiological guard and
     * unit normalisation as a hand-typed value, so this can still be refused.
     */
    public function acceptSuggestion(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        SuggestedObservation $item,
    ): void;

    public function rejectSuggestion(
        ClinicalActor $actor,
        SuggestedObservation $item,
        ?string $note = null,
    ): void;
}
