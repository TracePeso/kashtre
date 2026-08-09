<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\CodedOption;
use App\Support\Clinical\UnitOption;

/**
 * Settings dictionaries — API Integration Guide §10.9.
 *
 * "Never hardcode a clinical value in your module." Units, reason codes,
 * routes, frequencies and escalation tiers are all tenant-configurable, so
 * they are fetched, not compiled in.
 *
 * These are read-mostly and identical for every user in a tenant, which makes
 * them the one part of the clinical surface worth caching. Implementations
 * should cache briefly — long enough to keep dropdowns off the network on
 * every render, short enough that an administrator's edit shows up without a
 * deploy.
 */
interface ClinicalDictionaryGateway
{
    /**
     * @return array<int, UnitOption>
     */
    public function unitsOfMeasure(ClinicalActor $actor): array;

    /**
     * @param  string  $category  e.g. MAR_WASTAGE, CDSS_OVERRIDE, BREAK_GLASS
     * @return array<int, CodedOption>
     */
    public function reasonCodes(ClinicalActor $actor, string $category): array;

    /**
     * @return array<int, CodedOption>
     */
    public function routes(ClinicalActor $actor): array;

    /**
     * Frequencies carry `minute_interval`, which is what the MAR scheduler
     * expands into individual doses. STAT means a single immediate dose and
     * PRN means none at all — both have no meaningful interval.
     *
     * @return array<int, CodedOption>
     */
    public function frequencies(ClinicalActor $actor): array;
}
