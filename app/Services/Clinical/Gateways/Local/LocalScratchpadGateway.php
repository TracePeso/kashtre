<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\ScratchpadGateway;
use App\Models\CdeObservation;
use App\Models\CdeRegistry;
use App\Models\ClinicalUomMaster;
use App\Services\AiGateway\AiGatewayClientService;
use App\Services\Clinical\CdeExecutionEngine;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ObservationRecord;
use App\Support\Clinical\SuggestedObservation;

/**
 * CLINICAL_DRIVER=local: the scratchpad against local tables, calling the AI
 * Gateway directly.
 *
 * Notes are stored as BEDSIDE_NOTE observations rather than in a table of
 * their own — the local schema has no scratchpad table, and a note is an
 * observation with text instead of a number.
 *
 * Suggestions are *not* persisted here. They live in the Livewire component's
 * state between extraction and commit, so `id` is a synthetic index. The API
 * driver stages them server-side and returns real ids; both satisfy the
 * contract's "opaque handle, pass it back" rule.
 */
class LocalScratchpadGateway implements ScratchpadGateway
{
    public function __construct(
        private readonly AiGatewayClientService $aiGateway,
        private readonly CdeExecutionEngine $engine,
    ) {
    }

    public function isAiAvailable(): bool
    {
        return $this->aiGateway->isAvailable();
    }

    public function recentNotes(ClinicalActor $actor, string $patientId, int $limit = 5): array
    {
        return CdeObservation::query()
            ->where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->where('cde_code', 'BEDSIDE_NOTE')
            ->orderByDesc('captured_at')
            ->limit($limit)
            ->get()
            ->map(fn (CdeObservation $note) => ObservationRecord::fromModel($note))
            ->all();
    }

    public function saveNote(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $content,
    ): int|string {
        // Deliberately does not go through CdeExecutionEngine: that engine
        // normalises and range-checks *numeric* values, and free text has
        // neither a unit nor a physiological bound.
        return CdeObservation::create([
            'business_id' => $actor->businessId,
            'branch_id' => $actor->branchId,
            'client_id' => $patientId,
            'visit_id' => $visitId,
            'cde_code' => 'BEDSIDE_NOTE',
            'captured_value_text' => $content,
            'capture_method' => CdeObservation::METHOD_MANUAL,
            'validation_status' => 'VALIDATED',
            'validated_by_user_id' => $actor->userId,
            'captured_at' => now(),
        ])->id;
    }

    public function extractObservations(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $content,
        int|string|null $noteId = null,
    ): array {
        $result = $this->aiGateway->extractObservations($patientId, $visitId, $content);

        if (! ($result['available'] ?? false)) {
            return [];
        }

        $suggestions = [];

        foreach (array_values($result['observations'] ?? []) as $index => $proposed) {
            $cdeCode = (string) ($proposed['cde_code'] ?? '');

            $suggestions[] = new SuggestedObservation(
                id: $index,
                cde_code: $cdeCode,
                value: $proposed['value'] ?? null,
                unit: $proposed['unit'] ?? null,
                display_label: $proposed['dataElement'] ?? null,
                cde_resolved: $cdeCode !== '' && CdeRegistry::resolve($actor->businessId, $cdeCode) !== null,
                unit_resolved: $this->resolveUnitId($actor, $cdeCode, $proposed['unit'] ?? null) !== null,
            );
        }

        return $suggestions;
    }

    public function acceptSuggestion(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        SuggestedObservation $item,
    ): void {
        // Runs the full engine — normalisation and the physiological guard —
        // so an AI-extracted implausible value is refused exactly as a
        // hand-typed one would be. That equivalence is the safety property.
        $this->engine->captureObservation([
            'client_id' => $patientId,
            'visit_id' => $visitId,
            'cde_code' => $item->cde_code,
            'value_numeric' => (float) $item->value,
            'input_uom_id' => $this->resolveUnitId($actor, $item->cde_code, $item->unit),
            'capture_method' => CdeObservation::METHOD_VOICE_DICTATION,
        ], $actor->userId, $actor->businessId, $actor->branchId);
    }

    public function rejectSuggestion(ClinicalActor $actor, SuggestedObservation $item, ?string $note = null): void
    {
        // Nothing to do: local suggestions were never persisted, so discarding
        // one is the caller dropping it from its own state. The API driver has
        // a record to mark rejected, which is why the method exists at all.
    }

    /**
     * The unit the extractor named, or the CDE's base unit if it named none
     * we recognise.
     */
    private function resolveUnitId(ClinicalActor $actor, string $cdeCode, ?string $unitLabel): ?int
    {
        if ($unitLabel) {
            $unitId = ClinicalUomMaster::where('unit_label', $unitLabel)->value('id');

            if ($unitId) {
                return (int) $unitId;
            }
        }

        $cde = CdeRegistry::resolve($actor->businessId, $cdeCode);

        return $cde?->base_uom_id ? (int) $cde->base_uom_id : null;
    }
}
