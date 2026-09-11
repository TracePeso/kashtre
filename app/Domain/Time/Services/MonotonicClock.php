<?php

namespace App\Domain\Time\Services;

/**
 * Monotonic elapsed-time helper (TIME-CLK-010) — not for civil event time.
 */
final class MonotonicClock
{
    private ?float $startedAt = null;

    public function start(): void
    {
        $this->startedAt = hrtime(true) / 1e9;
    }

    public function elapsedSeconds(): float
    {
        if ($this->startedAt === null) {
            $this->start();
        }

        return (hrtime(true) / 1e9) - $this->startedAt;
    }

    public function nowNanoseconds(): int
    {
        return (int) hrtime(true);
    }
}
