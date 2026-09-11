<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * Chronic-disease and post-discharge recall worklist — §10.8. Recalls are
 * system-generated from `recall_rules` (a settings dictionary, e.g. a
 * confirmed diabetes diagnosis schedules review at 90 and 365 days) —
 * there is no "create a recall" endpoint for a clinician to call; only
 * reading the worklist and closing an entry once it is actioned.
 *
 * A raw array, not a DTO — display-only, list-of-records shape, same
 * reasoning as HandoverGateway.
 */
interface RecallGateway
{
    /**
     * @return array{recalls: array<int, array<string, mixed>>, count: int, overdue: int}
     */
    public function worklist(ClinicalActor $actor, ?string $status = 'DUE'): array;

    /**
     * @return array<string, mixed>
     */
    public function complete(ClinicalActor $actor, int|string $recallId, ?string $notes = null): array;

    /**
     * @return array<string, mixed>
     */
    public function cancel(ClinicalActor $actor, int|string $recallId, ?string $reason = null): array;
}
