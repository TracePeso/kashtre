<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\ClinicalSettingsGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: dictionary authoring against the Clinical Module.
 *
 * Reads fail soft to an empty list — an administrator seeing "no reason codes"
 * with the panel intact can retry, where a 500 loses the whole settings page.
 * Writes are never swallowed: an administrator who saves a reason code and is
 * not told it failed will assume the facility is configured when it is not.
 *
 * Settings endpoints need only the service key — no care relationship and no
 * patient context — so these work for any administrator.
 */
class ApiClinicalSettingsGateway implements ClinicalSettingsGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function list(ClinicalActor $actor, string $path, array $filters = []): array
    {
        try {
            $data = $this->client->get($path, $filters, ['business_id' => $actor->businessId]);
        } catch (ClinicalApiException $e) {
            Log::warning("Could not load clinical dictionary [{$path}].", $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function create(ClinicalActor $actor, string $path, array $attributes): array
    {
        return $this->client->post($path, $attributes, ['business_id' => $actor->businessId]);
    }

    public function update(ClinicalActor $actor, string $path, int|string $id, array $attributes): array
    {
        // The dictionaries use PATCH for edits — a dictionary row is amended
        // field by field, not replaced wholesale.
        return $this->client->patch(
            rtrim($path, '/')."/{$id}",
            $attributes,
            ['business_id' => $actor->businessId],
        );
    }

    public function activate(ClinicalActor $actor, string $path, int|string $id): array
    {
        return $this->client->post(
            rtrim($path, '/')."/{$id}/activate",
            [],
            ['business_id' => $actor->businessId],
        );
    }

    public function deactivate(ClinicalActor $actor, string $path, int|string $id): array
    {
        return $this->client->post(
            rtrim($path, '/')."/{$id}/deactivate",
            [],
            ['business_id' => $actor->businessId],
        );
    }

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }
}
