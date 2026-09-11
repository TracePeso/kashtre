<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\WardCensusGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\WardCensus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * CLINICAL_DRIVER=api: ward occupancy and bed management from the Clinical
 * Module — SRD §5. Clinical models wards as client_spaces + patient_beds, so
 * this is the driver that puts the board on the side that owns the data.
 *
 * Census reads are cached briefly because Livewire re-renders the component on
 * every interaction and the board would otherwise re-fetch on each keystroke.
 * Every mutation forgets that entry immediately: a nurse who assigns a bed must
 * see the card totals move, and a stale census after a write is worse than no
 * cache at all.
 */
class ApiWardCensusGateway implements WardCensusGateway
{
    private const TTL_SECONDS = 15;

    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function wards(ClinicalActor $actor): array
    {
        $payload = Cache::remember(
            $this->wardsKey($actor),
            now()->addSeconds(self::TTL_SECONDS),
            fn () => $this->client->get('clinical/wards', [], ['business_id' => $actor->businessId]),
        );

        if (! is_array($payload)) {
            return [];
        }

        return array_map(
            fn (array $row) => WardCensus::fromApi($row),
            array_values(array_filter($payload, 'is_array')),
        );
    }

    public function census(ClinicalActor $actor, string $wardCode): ?WardCensus
    {
        $payload = Cache::remember(
            $this->censusKey($actor, $wardCode),
            now()->addSeconds(self::TTL_SECONDS),
            fn () => $this->client->get(
                "clinical/wards/{$wardCode}/census",
                [],
                ['business_id' => $actor->businessId],
            ),
        );

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return WardCensus::fromApi($payload);
    }

    public function reserveBed(ClinicalActor $actor, int $bedId, string $patientId, ?string $visitId = null): void
    {
        $this->bedAction($actor, $bedId, 'reserve', array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
        ]));
    }

    public function assignBed(ClinicalActor $actor, int $bedId, string $patientId, ?string $visitId = null): ?int
    {
        $meta = $this->bedAction($actor, $bedId, 'assign', array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
        ]));

        return isset($meta['movement_id']) ? (int) $meta['movement_id'] : null;
    }

    public function releaseBed(ClinicalActor $actor, int $bedId): void
    {
        $this->bedAction($actor, $bedId, 'release', []);
    }

    public function retireBed(ClinicalActor $actor, int $bedId): void
    {
        $this->client->delete("clinical/beds/{$bedId}", [], ['business_id' => $actor->businessId]);

        $this->forgetCensus($actor);
    }

    public function addOverflowBed(ClinicalActor $actor, int $clientSpaceId, ?string $bedCode = null): void
    {
        // Keyed by numeric space id, unlike every other call here. Omitting
        // bed_code lets the ward derive one from the room.
        $this->client->post(
            "clinical/wards/{$clientSpaceId}/overflow-bed",
            array_filter(['bed_code' => $bedCode]),
            ['business_id' => $actor->businessId],
        );

        $this->forgetCensus($actor);
    }

    public function retireVacantOverflowBeds(ClinicalActor $actor, string $wardCode): array
    {
        $envelope = $this->client->deleteEnvelope(
            "clinical/wards/{$wardCode}/overflow-beds",
            [],
            ['business_id' => $actor->businessId],
        );

        $this->forgetCensus($actor, $wardCode);

        $data = (array) ($envelope['data'] ?? []);

        return [
            'retired_count' => (int) ($data['retired_count'] ?? 0),
            'skipped_count' => (int) ($data['skipped_count'] ?? 0),
            'skipped' => array_values(array_filter((array) ($data['skipped'] ?? []), 'is_array')),
            'message' => isset($envelope['meta']['message']) ? (string) $envelope['meta']['message'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> the response's meta — assign()'s
     *                              movement_id lives there, same convention
     *                              as release()'s retirement_prompt.
     */
    private function bedAction(ClinicalActor $actor, int $bedId, string $action, array $payload): array
    {
        $envelope = $this->client->postEnvelope(
            "clinical/beds/{$bedId}/{$action}",
            $payload,
            [
                'business_id' => $actor->businessId,
                // One key per user-initiated action, generated here rather than
                // derived from the bed and payload. A derived key looks stable
                // and is not: reserving the same bed for the same patient on
                // Tuesday reuses Monday's key, and Clinical correctly serves the
                // stored response — so the bed never moves and nothing errors.
                // The transport retry inside ClinicalApiClient replays this same
                // request with this same key, which is the case idempotency is
                // actually protecting against.
                'idempotency_key' => (string) Str::uuid(),
            ],
        );

        $this->forgetCensus($actor);

        return (array) ($envelope['meta'] ?? []);
    }

    private function censusKey(ClinicalActor $actor, string $wardCode): string
    {
        return "clinical:ward-census:{$actor->businessId}:{$this->generation($actor)}:{$wardCode}";
    }

    private function wardsKey(ClinicalActor $actor): string
    {
        return "clinical:wards:{$actor->businessId}:{$this->generation($actor)}";
    }

    /**
     * A bed action names a bed, not a ward, so the response cannot tell us
     * which ward's census just went stale. Rather than guess, every cached read
     * is namespaced by a generation counter and a mutation bumps it — which
     * invalidates the picker and every ward at once. The old entries are
     * unreachable and expire on their own TTL.
     */
    private function generation(ClinicalActor $actor): int
    {
        return (int) Cache::get($this->generationKey($actor), 1);
    }

    private function generationKey(ClinicalActor $actor): string
    {
        return "clinical:ward-cache-generation:{$actor->businessId}";
    }

    private function forgetCensus(ClinicalActor $actor, ?string $wardCode = null): void
    {
        $key = $this->generationKey($actor);

        // No TTL: the counter must outlive the entries it namespaces, or a
        // rollover would resurrect a stale census that is still inside its TTL.
        Cache::forever($key, $this->generation($actor) + 1);
    }
}
