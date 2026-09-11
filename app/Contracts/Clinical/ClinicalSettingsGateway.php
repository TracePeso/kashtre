<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * The Clinical Module's configuration surface — units, reason codes, CDEs,
 * order sets, ward structure, care teams, recall and work-order rules.
 *
 * These are the dictionaries the zero-hardcoding mandate exists for: a facility
 * changes its own reason codes and frequencies, and every clinical picker is
 * populated from here rather than from a PHP enum. Main hosts the authoring UI
 * because that is where a super admin already administers every other module;
 * the Clinical Module remains the system of record.
 *
 * Deliberately generic. Every dictionary shares an index/store/update shape, so
 * one interface serves all of them and a dictionary added on the Clinical side
 * costs a manifest entry here rather than a new gateway.
 */
interface ClinicalSettingsGateway
{
    /**
     * @param  string  $path  the dictionary's path, e.g. 'settings/dictionaries/reason-codes'
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(ClinicalActor $actor, string $path, array $filters = []): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(ClinicalActor $actor, string $path, array $attributes): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(ClinicalActor $actor, string $path, int|string $id, array $attributes): array;

    /**
     * Dictionaries are never deleted — a row is toggled out of clinician
     * drop-downs instead, which keeps every historical record that
     * references it intact and readable.
     *
     * @return array<string, mixed>
     */
    public function activate(ClinicalActor $actor, string $path, int|string $id): array;

    /**
     * Five dictionaries refuse this when something still depends on the row
     * (an active CDE's base unit, a care team with live assignments, an
     * occupied bed's space, a process with instances in progress, or
     * CRITICAL_PANIC on escalation rules, which can never be silenced) — that
     * refusal arrives as a normal 422 with a message naming the blocker.
     *
     * @return array<string, mixed>
     */
    public function deactivate(ClinicalActor $actor, string $path, int|string $id): array;

    /** Whether this driver can reach the dictionaries at all. */
    public function isAvailable(): bool;
}
