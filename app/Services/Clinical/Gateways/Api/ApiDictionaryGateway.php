<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\ClinicalDictionaryGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\CodedOption;
use App\Support\Clinical\UnitOption;
use Illuminate\Support\Facades\Cache;

/**
 * CLINICAL_DRIVER=api: settings dictionaries — API Integration Guide §10.9.
 *
 * These populate dropdowns, so they are read on nearly every clinical render
 * and would otherwise be several network round trips per page. They are also
 * the most cacheable thing in the whole surface: identical for every user in
 * a tenant, and changed only by an administrator editing settings.
 *
 * Ten minutes is the compromise — long enough that a busy ward is not
 * re-fetching the reason-code list on every keystroke, short enough that an
 * administrator who adds a wastage reason sees it take effect over a coffee
 * rather than after a deploy.
 */
class ApiDictionaryGateway implements ClinicalDictionaryGateway
{
    private const TTL_MINUTES = 10;

    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function unitsOfMeasure(ClinicalActor $actor): array
    {
        return $this->cached(
            $actor,
            'units',
            'settings/dictionaries/units-of-measure',
            [],
            fn (array $row) => UnitOption::fromApi($row),
        );
    }

    public function reasonCodes(ClinicalActor $actor, string $category): array
    {
        return $this->cached(
            $actor,
            "reasons:{$category}",
            'settings/dictionaries/reason-codes',
            ['category' => $category],
            fn (array $row) => CodedOption::fromApi($row),
        );
    }

    public function routes(ClinicalActor $actor): array
    {
        return $this->routesAndFrequencies($actor, 'ROUTE');
    }

    public function frequencies(ClinicalActor $actor): array
    {
        return $this->routesAndFrequencies($actor, 'FREQUENCY');
    }

    /**
     * @return array<int, CodedOption>
     */
    private function routesAndFrequencies(ClinicalActor $actor, string $type): array
    {
        // One endpoint serves both (§10.9), so both share a cache entry and
        // the split happens here rather than in two round trips.
        $payload = Cache::remember(
            "clinical:dict:{$actor->businessId}:routes-and-frequencies",
            now()->addMinutes(self::TTL_MINUTES),
            fn () => $this->client->get(
                'settings/dictionaries/routes-and-frequencies',
                [],
                ['business_id' => $actor->businessId],
            ),
        );

        $key = $type === 'ROUTE' ? 'routes' : 'frequencies';
        $rows = $payload[$key] ?? array_filter(
            array_is_list($payload) ? $payload : [],
            fn ($row) => is_array($row) && ($row['type'] ?? null) === $type,
        );

        $options = array_map(
            fn (array $row) => CodedOption::fromApi($row),
            array_values(array_filter($rows, 'is_array')),
        );

        // Frequencies sort by interval so STAT and the short intervals lead;
        // routes sort alphabetically. Matches the local ordering exactly so
        // the dropdowns do not visibly reshuffle when the driver flips.
        usort($options, fn (CodedOption $a, CodedOption $b) => $type === 'FREQUENCY'
            ? ($a->minute_interval ?? PHP_INT_MAX) <=> ($b->minute_interval ?? PHP_INT_MAX)
            : strcmp($a->display_label, $b->display_label));

        return $options;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  callable(array<string, mixed>): mixed  $map
     * @return array<int, mixed>
     */
    private function cached(ClinicalActor $actor, string $key, string $path, array $query, callable $map): array
    {
        $payload = Cache::remember(
            "clinical:dict:{$actor->businessId}:{$key}",
            now()->addMinutes(self::TTL_MINUTES),
            fn () => $this->client->get($path, $query, ['business_id' => $actor->businessId]),
        );

        $rows = array_is_list($payload) ? $payload : ($payload['items'] ?? []);

        return array_map($map, array_values(array_filter($rows, 'is_array')));
    }
}
