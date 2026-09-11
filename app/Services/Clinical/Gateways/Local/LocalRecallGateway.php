<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\RecallGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: no recall-rule engine exists against this schema
 * — recall generation is entirely Clinical's compliance engine, driven by
 * dictionary rules this driver does not carry. An empty worklist is the
 * honest answer.
 */
class LocalRecallGateway implements RecallGateway
{
    public function worklist(ClinicalActor $actor, ?string $status = 'DUE'): array
    {
        return ['recalls' => [], 'count' => 0, 'overdue' => 0];
    }

    public function complete(ClinicalActor $actor, int|string $recallId, ?string $notes = null): array
    {
        throw new RuntimeException('Recalls are not available under the local clinical driver.');
    }

    public function cancel(ClinicalActor $actor, int|string $recallId, ?string $reason = null): array
    {
        throw new RuntimeException('Recalls are not available under the local clinical driver.');
    }
}
