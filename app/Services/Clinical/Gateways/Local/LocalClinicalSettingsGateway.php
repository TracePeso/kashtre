<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\ClinicalSettingsGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: no dictionary authoring.
 *
 * The local engines read the clinical_* master tables directly and there is no
 * in-process CRUD surface behind them — those tables are seeded by migration.
 * Rather than half-implement authoring against a dozen models, this reports
 * itself unavailable and the settings screen says so.
 *
 * That is honest about a real gap: dictionary authoring is a feature of the
 * Clinical Module, and on the local driver it does not exist.
 */
class LocalClinicalSettingsGateway implements ClinicalSettingsGateway
{
    public function list(ClinicalActor $actor, string $path, array $filters = []): array
    {
        return [];
    }

    public function create(ClinicalActor $actor, string $path, array $attributes): array
    {
        throw new RuntimeException(
            'Clinical dictionaries can only be edited while CLINICAL_DRIVER=api; '
            .'on the local driver they are seeded by migration.'
        );
    }

    public function update(ClinicalActor $actor, string $path, int|string $id, array $attributes): array
    {
        return $this->create($actor, $path, $attributes);
    }

    public function activate(ClinicalActor $actor, string $path, int|string $id): array
    {
        return $this->create($actor, $path, []);
    }

    public function deactivate(ClinicalActor $actor, string $path, int|string $id): array
    {
        return $this->create($actor, $path, []);
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
