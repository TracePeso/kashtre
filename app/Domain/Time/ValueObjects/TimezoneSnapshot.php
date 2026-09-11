<?php

namespace App\Domain\Time\ValueObjects;

/**
 * Snapshot of timezone + offset + DST markers at a point in time (Phase 1/2).
 */
final class TimezoneSnapshot
{
    public function __construct(
        public readonly IanaTimezoneId $ianaId,
        public readonly UtcInstant $asOf,
        public readonly int $utcOffsetSeconds,
        public readonly bool $isDst,
        public readonly ?string $abbreviation,
        public readonly string $tzdbVersion,
    ) {}

    public function toArray(): array
    {
        return [
            'ianaId' => $this->ianaId->value(),
            'asOf' => $this->asOf->toIso8601(),
            'utcOffsetSeconds' => $this->utcOffsetSeconds,
            'isDst' => $this->isDst,
            'abbreviation' => $this->abbreviation,
            'tzdbVersion' => $this->tzdbVersion,
        ];
    }
}
