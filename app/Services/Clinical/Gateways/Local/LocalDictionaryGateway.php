<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\ClinicalDictionaryGateway;
use App\Models\ClinicalReasonCode;
use App\Models\ClinicalUomMaster;
use App\Models\PharmacyRouteFrequency;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\CodedOption;
use App\Support\Clinical\UnitOption;

/**
 * CLINICAL_DRIVER=local: dictionaries from the local master tables.
 *
 * No caching here — these are ordinary indexed local queries, and a cache
 * would only add a staleness window for an administrator editing a reason
 * code. The API implementation caches because there the same lookup is a
 * network round trip on every render.
 */
class LocalDictionaryGateway implements ClinicalDictionaryGateway
{
    public function unitsOfMeasure(ClinicalActor $actor): array
    {
        return ClinicalUomMaster::query()
            ->orderBy('unit_label')
            ->get()
            ->map(fn (ClinicalUomMaster $unit) => UnitOption::fromModel($unit))
            ->all();
    }

    public function reasonCodes(ClinicalActor $actor, string $category): array
    {
        return ClinicalReasonCode::query()
            ->where('category_code', $category)
            ->where('is_active', true)
            ->get()
            ->map(fn (ClinicalReasonCode $reason) => CodedOption::fromModel($reason))
            ->all();
    }

    public function routes(ClinicalActor $actor): array
    {
        return PharmacyRouteFrequency::query()
            ->where('type', 'ROUTE')
            ->orderBy('display_label')
            ->get()
            ->map(fn (PharmacyRouteFrequency $route) => CodedOption::fromModel($route))
            ->all();
    }

    public function frequencies(ClinicalActor $actor): array
    {
        return PharmacyRouteFrequency::query()
            ->where('type', 'FREQUENCY')
            ->orderBy('minute_interval')
            ->get()
            ->map(fn (PharmacyRouteFrequency $frequency) => CodedOption::fromModel($frequency))
            ->all();
    }
}
