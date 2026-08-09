<?php

namespace App\Services\Clinical\Api\Resources;

use App\Services\Clinical\Api\ClinicalApiClient;

/**
 * Base for the typed endpoint groups in this namespace.
 *
 * Together the subclasses cover every route in §16 of the Clinical Module API
 * Integration Guide. They are a thin, honest mapping — one method per
 * documented endpoint, named after what it does, with the guide's payload keys
 * passed through unchanged. Deliberately *not* a place for business logic:
 * anything that decides something belongs in a gateway
 * (App\Contracts\Clinical\*), which can then be implemented locally too.
 *
 * Use these when you need an endpoint the gateways do not abstract — which is
 * most of the surface, because most of it has no local equivalent.
 */
abstract class ClinicalResource
{
    public function __construct(protected readonly ClinicalApiClient $client)
    {
    }

    /**
     * Strips nulls so optional fields are omitted rather than sent as null.
     * The distinction is load-bearing in places — §10.2 treats a missing
     * `input_uom_id` as "use the CDE's base unit" but an explicit null as a
     * validation failure.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function filled(array $payload): array
    {
        return array_filter($payload, fn ($value) => $value !== null);
    }

    /**
     * Collection endpoints return `data` as a bare list; some nest it under a
     * named key. Tolerating both honours §2's instruction to treat unexpected
     * response shapes as forward compatibility rather than failing on them.
     *
     * @param  array<mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function rows(array $payload, string ...$keys): array
    {
        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        foreach ([...$keys, 'items', 'data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function idempotent(string $key, array $options = []): array
    {
        return $options + ['idempotency_key' => $key];
    }
}
