<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\CriticalAlertsGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: `GET clinical/critical-alerts` (confirmed live
 * 2026-08-19). Fails soft to an empty feed — a dashboard badge that cannot
 * reach Clinical should say nothing rather than crash the page it sits on.
 */
class ApiCriticalAlertsGateway implements CriticalAlertsGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function feed(
        ClinicalActor $actor,
        string $scope = self::SCOPE_MY_PATIENTS,
        ?string $wardCode = null,
        ?string $severityTier = null,
        bool $includeAcknowledged = false,
        int $limit = 100,
    ): array {
        try {
            $response = $this->client->getEnvelope('clinical/critical-alerts', array_filter([
                'scope' => $scope,
                'ward_code' => $wardCode,
                'severity_tier' => $severityTier,
                'include_acknowledged' => $includeAcknowledged ? 1 : null,
                'limit' => $limit,
            ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load the critical alerts feed.', $e->context());

            return ['alerts' => [], 'count' => 0, 'by_severity' => [], 'oldest_unacknowledged_at' => null];
        }

        $meta = $response['meta'] ?? [];

        return [
            'alerts' => $response['data'] ?? [],
            'count' => $meta['count'] ?? 0,
            // Clinical returns {} for an empty map, which json-decodes to []
            // here, not a keyed array — guard so a template can iterate it
            // as key => value without a is_array/count(0) special case.
            'by_severity' => is_array($meta['by_severity'] ?? null) && ! array_is_list($meta['by_severity']) ? $meta['by_severity'] : [],
            'oldest_unacknowledged_at' => $meta['oldest_unacknowledged_at'] ?? null,
        ];
    }

    public function acknowledge(ClinicalActor $actor, int|string $alertId): void
    {
        $this->client->post(
            "clinical/critical-alerts/{$alertId}/acknowledge",
            [],
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => 'critical-alert-ack-'.$alertId,
            ],
        );
    }

    public function review(ClinicalActor $actor, int|string $alertId, string $reviewNotes): array
    {
        return $this->client->post(
            "clinical/critical-alerts/{$alertId}/review",
            ['review_notes' => $reviewNotes],
            ['business_id' => $actor->businessId],
        );
    }

    public function action(ClinicalActor $actor, int|string $alertId, string $actionTaken): array
    {
        return $this->client->post(
            "clinical/critical-alerts/{$alertId}/action",
            ['action_taken' => $actionTaken],
            ['business_id' => $actor->businessId],
        );
    }

    public function close(ClinicalActor $actor, int|string $alertId, string $closureReason): array
    {
        return $this->client->post(
            "clinical/critical-alerts/{$alertId}/close",
            ['closure_reason' => $closureReason],
            ['business_id' => $actor->businessId],
        );
    }
}
