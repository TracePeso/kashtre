<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\WardCensus;

/**
 * Ward occupancy and bed management — SRD §5.1-5.3.
 *
 * Wards and beds belong to the Clinical Module under SRD §5; Main renders the
 * census board. This gateway is what lets that board sit on either side without
 * the Livewire component knowing which is answering.
 *
 * Reserve and assign are separate states on purpose: the Decision to Admit flow
 * holds a bed while the patient is still in A&E, and collapsing the two would
 * lose the distinction between "kept for them" and "they are in it".
 *
 * Callers should re-read the census after any mutation rather than patching a
 * bed in place — the action responses describe only the bed they touched, so
 * the four header cards go stale otherwise.
 */
interface WardCensusGateway
{
    /**
     * Every ward in the facility, with headline occupancy for the picker.
     *
     * @return array<int, WardCensus>
     */
    public function wards(ClinicalActor $actor): array;

    /**
     * @param  string  $wardCode  e.g. ICU, GYNAE — the code, never an id
     */
    public function census(ClinicalActor $actor, string $wardCode): ?WardCensus;

    /** Hold a bed for a patient who has not arrived yet. */
    public function reserveBed(ClinicalActor $actor, int $bedId, string $patientId, ?string $visitId = null): void;

    /**
     * The patient physically arrives. Valid on an AVAILABLE bed, or one already
     * RESERVED for that same patient.
     *
     * @return int|null the BedMovement id this assign created — API_GUIDE_V6.1
     *                   §1's "Complete an internal transfer" needs it as
     *                   evidence the bed move happened. Null under the local
     *                   driver, which has no such row.
     */
    public function assignBed(ClinicalActor $actor, int $bedId, string $patientId, ?string $visitId = null): ?int;

    /** The patient leaves; the bed returns to AVAILABLE. */
    public function releaseBed(ClinicalActor $actor, int $bedId): void;

    /** Retire a single bed outright — used to stand down one surge bed. */
    public function retireBed(ClinicalActor $actor, int $bedId): void;

    /**
     * Add a surge bed. Keyed by numeric client_space id, not ward code — the
     * one inconsistency in this group.
     *
     * @param  string|null  $bedCode  omit to let the ward auto-generate one
     */
    public function addOverflowBed(ClinicalActor $actor, int $clientSpaceId, ?string $bedCode = null): void;

    /**
     * Stand down surge capacity: retire every *vacant* overflow bed in the
     * ward. Occupied and reserved surge beds are skipped, never emptied, and
     * are reported back so the board can say which stayed and why.
     *
     * @return array{retired_count: int, skipped_count: int, skipped: array<int, array<string, mixed>>, message: ?string}
     */
    public function retireVacantOverflowBeds(ClinicalActor $actor, string $wardCode): array;
}
