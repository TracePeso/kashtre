<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\HandoverRecordGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: the accountable Handover Record (SRD v6.1
 * Phase 9) is Clinical-owned, with no local table equivalent — distinct
 * from LocalHandoverGateway, which still answers the older stateless
 * ward-projection question locally.
 */
class LocalHandoverRecordGateway implements HandoverRecordGateway
{
    public function prepare(ClinicalActor $actor, array $payload): array
    {
        $this->refuse();
    }

    public function show(ClinicalActor $actor, string $handoverId): array
    {
        $this->refuse();
    }

    public function send(ClinicalActor $actor, string $handoverId): array
    {
        $this->refuse();
    }

    public function acknowledge(ClinicalActor $actor, string $handoverId, ?string $note = null): array
    {
        $this->refuse();
    }

    public function amend(ClinicalActor $actor, string $handoverId, array $content): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('The Handover Record (v6.1 Phase 9) is only available under CLINICAL_DRIVER=api.');
    }
}
