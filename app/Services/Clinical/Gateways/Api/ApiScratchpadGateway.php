<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\ScratchpadGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Services\Clinical\Api\Exceptions\ClinicalUnavailableException;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ObservationRecord;
use App\Support\Clinical\SuggestedObservation;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: scratchpad and AI extraction — §10.7.
 *
 * Main holds no AI Gateway credentials under this driver. We post free text to
 * Clinical, Clinical calls the gateway, and the results come back as *staged
 * candidates* that a clinician accepts item by item. Accepted items then run
 * Clinical's physiological guard and CDSS shield exactly as typed input does.
 *
 * All AI endpoints answer 503 until the gateway is configured (§14), which is
 * why isAiAvailable() probes rather than assuming.
 */
class ApiScratchpadGateway implements ScratchpadGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function isAiAvailable(): bool
    {
        if (! $this->client->isConfigured()) {
            return false;
        }

        // A degraded AI dependency shows up in the health check's `checks`
        // map. Treating an unreachable Clinical as "AI unavailable" is right:
        // the UI falls back to manual note entry, which is what a clinician
        // needs either way.
        $health = $this->client->health();

        return $health['ok'] && ($health['checks']['ai_gateway'] ?? true) !== false;
    }

    public function recentNotes(ClinicalActor $actor, string $patientId, int $limit = 5): array
    {
        try {
            $data = $this->client->get("clinical/patients/{$patientId}/scratchpad", [
                'limit' => $limit,
            ], ['business_id' => $actor->businessId]);
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load scratchpad notes.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? []);

        return array_map(function (array $note): ObservationRecord {
            return ObservationRecord::fromApi([
                'id' => $note['id'] ?? '',
                'cde_code' => 'BEDSIDE_NOTE',
                'value_text' => $note['content'] ?? '',
                'captured_at' => $note['created_at'] ?? $note['captured_at'] ?? null,
            ]);
        }, array_values(array_filter($rows, 'is_array')));
    }

    public function saveNote(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $content,
    ): int|string {
        $data = $this->client->post('clinical/scratchpad', array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'content' => $content,
            'source' => 'TYPED',
        ], fn ($value) => $value !== null), ['business_id' => $actor->businessId]);

        return $data['note_id'] ?? $data['id'] ?? '';
    }

    public function extractObservations(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $content,
        int|string|null $noteId = null,
    ): array {
        // Extraction is anchored to a saved note, so text that has not been
        // saved is persisted first. That also means the note survives even if
        // the extraction fails — the clinician does not lose what they typed.
        $noteId ??= $this->saveNote($actor, $patientId, $visitId, $content);

        try {
            $data = $this->client->post(
                "clinical/scratchpad/{$noteId}/extract-observations",
                [],
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalUnavailableException $e) {
            // The gateway is unconfigured or degraded. Not an error worth
            // showing — the note is saved and manual entry still works.
            Log::info('AI extraction unavailable; note saved without extraction.', $e->context());

            return [];
        }

        return array_map(
            fn (array $item) => SuggestedObservation::fromApi($item),
            array_values(array_filter($data['items'] ?? [], 'is_array')),
        );
    }

    public function acceptSuggestion(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        SuggestedObservation $item,
    ): void {
        $this->client->post(
            "clinical/ai-suggestions/items/{$item->id}/accept",
            array_filter(['value' => $item->value], fn ($value) => $value !== null),
            [
                'business_id' => $actor->businessId,
                // Accepting the same suggestion twice must chart one
                // observation, not two.
                'idempotency_key' => "ai-suggestion-accept-{$item->id}",
            ],
        );
    }

    public function rejectSuggestion(ClinicalActor $actor, SuggestedObservation $item, ?string $note = null): void
    {
        $this->client->post(
            "clinical/ai-suggestions/items/{$item->id}/reject",
            array_filter(['note' => $note], fn ($value) => $value !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => "ai-suggestion-reject-{$item->id}",
            ],
        );
    }
}
