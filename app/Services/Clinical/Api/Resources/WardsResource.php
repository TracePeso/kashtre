<?php

namespace App\Services\Clinical\Api\Resources;

/**
 * Beds, wards and worklists — API Integration Guide §10.5.
 */
class WardsResource extends ClinicalResource
{
    /**
     * The spatial grid: bed layout, occupancy and overflow. This is the ward
     * board a charge nurse works from.
     *
     * @return array<string, mixed>
     */
    public function census(string $wardCode, array $options = []): array
    {
        return $this->client->get("clinical/wards/{$wardCode}/census", [], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function taskSummary(string $wardCode, array $options = []): array
    {
        return $this->client->get("clinical/wards/{$wardCode}/task-summary", [], $options);
    }

    /**
     * Creates a bed beyond the ward's established layout — a corridor or
     * doubled-up bay during a surge. Tracked separately so occupancy reporting
     * does not quietly normalise overcrowding.
     */
    public function createOverflowBed(int|string $clientSpaceId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/wards/{$clientSpaceId}/overflow-bed",
            $this->filled($payload),
            $options,
        );
    }

    /** Holds a bed without admitting to it — the decision-to-admit path. */
    public function reserveBed(int|string $bedId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/beds/{$bedId}/reserve",
            $this->filled($payload),
            $this->idempotent("bed-{$bedId}-reserve", $options),
        );
    }

    public function assignBed(int|string $bedId, array $payload, array $options = []): array
    {
        return $this->client->post(
            "clinical/beds/{$bedId}/assign",
            $this->filled($payload),
            $this->idempotent("bed-{$bedId}-assign", $options),
        );
    }

    public function releaseBed(int|string $bedId, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/beds/{$bedId}/release",
            $this->filled($payload),
            $this->idempotent("bed-{$bedId}-release", $options),
        );
    }

    public function deleteBed(int|string $bedId, array $options = []): array
    {
        return $this->client->delete("clinical/beds/{$bedId}", [], $options);
    }

    // ---------------------------------------------------------------- worklists

    /**
     * Projects the enterprise worklist down to something one person can act
     * on.
     *
     * @param  string  $scope  MY_PATIENTS | MY_WARD | MY_TEAM
     * @return array<int, array<string, mixed>>
     */
    public function taskVisibility(string $scope, ?int $userId = null, array $options = []): array
    {
        return $this->rows(
            $this->client->get('clinical/tasks/visibility', $this->filled([
                'scope' => $scope,
                'user_id' => $userId,
            ]), $options),
            'tasks'
        );
    }

    public function transitionWorkOrder(int|string $workOrderId, string $status, array $payload = [], array $options = []): array
    {
        return $this->client->post(
            "clinical/work-orders/{$workOrderId}/transition",
            $this->filled($payload + ['status' => $status]),
            $this->idempotent("work-order-{$workOrderId}-{$status}", $options),
        );
    }
}
