<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * The accountable Handover Record — SRD v6.1 Phase 9, API_GUIDE_V6.1
 * §Phase 9. Distinct from HandoverGateway (the stateless `GET
 * clinical/handover` ward projection, "what's outstanding right now",
 * recomputed on every call, never accepted by anyone) — this is the actual
 * event: prepared, sent, and — the point of building it — *acknowledged*,
 * since a movement or a sent message is not proof responsibility actually
 * transferred. A later update never edits an already-sent handover in
 * place; it creates a new version that supersedes it.
 */
interface HandoverRecordGateway
{
    /**
     * @param  array{patient_id: string, encounter_id?: ?string, intended_receiver_id: int|string, content: array<string, mixed>, transition_id?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function prepare(ClinicalActor $actor, array $payload): array;

    /** @return array<string, mixed> */
    public function show(ClinicalActor $actor, string $handoverId): array;

    /** Sending is not acceptance — status becomes SENT, not acknowledged. */
    public function send(ClinicalActor $actor, string $handoverId): array;

    /** The point where clinical responsibility actually transfers. */
    public function acknowledge(ClinicalActor $actor, string $handoverId, ?string $note = null): array;

    /**
     * Creates a new version superseding this one — never edits an
     * already-sent handover's content in place.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function amend(ClinicalActor $actor, string $handoverId, array $content): array;
}
