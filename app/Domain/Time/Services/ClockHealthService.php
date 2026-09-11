<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Contracts\Clock;
use App\Domain\Time\Enums\ClockHealthStatus;
use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\Models\ClockHealth;
use App\Domain\Time\ValueObjects\UtcInstant;

final class ClockHealthService
{
    public function __construct(private readonly Clock $clock) {}

    public function recordCheck(
        string $nodeKey,
        int $driftSeconds,
        ?string $source = null,
        array $metadata = [],
    ): ClockHealth {
        $status = $this->classify($driftSeconds);
        $now = $this->clock->now();

        return ClockHealth::query()->updateOrCreate(
            ['node_key' => $nodeKey],
            [
                'status' => $status->value,
                'drift_seconds' => $driftSeconds,
                'source' => $source,
                'checked_at_utc' => $now->toCarbon(),
                'metadata' => $metadata ?: null,
            ],
        );
    }

    public function classify(int $driftSeconds): ClockHealthStatus
    {
        $abs = abs($driftSeconds);
        $cfg = config('time.clock');

        if ($abs >= (int) $cfg['unhealthy_drift_seconds']) {
            return ClockHealthStatus::UNHEALTHY;
        }
        if ($abs >= (int) $cfg['degraded_drift_seconds']) {
            return ClockHealthStatus::DEGRADED;
        }
        if ($abs >= (int) $cfg['warning_drift_seconds']) {
            return ClockHealthStatus::WARNING;
        }

        return ClockHealthStatus::HEALTHY;
    }

    public function forNode(string $nodeKey): ?ClockHealth
    {
        return ClockHealth::query()->where('node_key', $nodeKey)->first();
    }

    /**
     * Refuse safety-critical timestamps when this node is unhealthy (TIME-CLK-007).
     */
    public function assertCanOriginate(string $nodeKey): void
    {
        $row = $this->forNode($nodeKey);
        if ($row && $row->status === ClockHealthStatus::UNHEALTHY->value) {
            throw TimeEngineException::clockUnhealthy($row->status);
        }
    }

    public function trustedNow(string $nodeKey = 'app'): UtcInstant
    {
        $this->assertCanOriginate($nodeKey);

        return $this->clock->now();
    }
}
