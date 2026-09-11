<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\RecallGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CLINICAL_DRIVER=api: `clinical/recalls/*` (confirmed live 2026-08-19 —
 * the tenant tested against had no recalls yet, so the worklist row shape
 * itself is unverified; rendered generically in the view for that reason).
 */
class ApiRecallGateway implements RecallGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function worklist(ClinicalActor $actor, ?string $status = 'DUE'): array
    {
        try {
            $response = $this->client->getEnvelope(
                'clinical/recalls/worklist',
                array_filter(['status' => $status]),
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load the recall worklist.', $e->context());

            return ['recalls' => [], 'count' => 0, 'overdue' => 0];
        }

        $meta = $response['meta'] ?? [];

        return [
            'recalls' => $response['data'] ?? [],
            'count' => $meta['count'] ?? 0,
            'overdue' => $meta['overdue'] ?? 0,
        ];
    }

    public function complete(ClinicalActor $actor, int|string $recallId, ?string $notes = null): array
    {
        return $this->client->post(
            "clinical/recalls/{$recallId}/complete",
            array_filter(['notes' => $notes], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => 'recall-complete-'.$recallId,
            ],
        );
    }

    public function cancel(ClinicalActor $actor, int|string $recallId, ?string $reason = null): array
    {
        return $this->client->post(
            "clinical/recalls/{$recallId}/cancel",
            array_filter(['reason' => $reason], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => (string) Str::uuid(),
            ],
        );
    }
}
