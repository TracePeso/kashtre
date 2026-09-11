<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Contracts\Clock;
use App\Domain\Time\ValueObjects\UtcInstant;
use Carbon\CarbonImmutable;

/**
 * Deterministic clock for tests and replay (TIME-CLK-003).
 */
final class FixedClock implements Clock
{
    private CarbonImmutable $current;

    public function __construct(UtcInstant|string|null $at = null)
    {
        $this->current = $at instanceof UtcInstant
            ? $at->toCarbon()
            : ($at ? CarbonImmutable::parse($at)->utc() : CarbonImmutable::now('UTC'));
    }

    public function now(): UtcInstant
    {
        return UtcInstant::fromDateTime($this->current);
    }

    public function set(UtcInstant|string $at): void
    {
        $this->current = $at instanceof UtcInstant
            ? $at->toCarbon()
            : CarbonImmutable::parse($at)->utc();
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->current = $this->current->addSeconds($seconds);
    }

    public function rewindSeconds(int $seconds): void
    {
        $this->current = $this->current->subSeconds($seconds);
    }
}
