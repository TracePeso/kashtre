<?php

namespace App\Domain\Time\ValueObjects;

use App\Domain\Time\Enums\TimeConfidence;
use App\Domain\Time\Enums\TimePrecision;
use App\Domain\Time\Enums\TimeSource;

/**
 * Provenance envelope for a captured instant (Phase 2).
 */
final class InstantProvenance
{
    public function __construct(
        public readonly UtcInstant $instant,
        public readonly TimeSource $source,
        public readonly TimeConfidence $confidence,
        public readonly TimePrecision $precision,
        public readonly ?IanaTimezoneId $sourceTimezone = null,
        public readonly ?LocalDateTime $sourceLocal = null,
        public readonly ?string $deviceId = null,
        public readonly ?int $clockSkewMs = null,
        public readonly ?string $notes = null,
    ) {}

    public function toArray(): array
    {
        return [
            'instant' => $this->instant->toIso8601(),
            'source' => $this->source->value,
            'confidence' => $this->confidence->value,
            'precision' => $this->precision->value,
            'sourceTimezone' => $this->sourceTimezone?->value(),
            'sourceLocal' => $this->sourceLocal?->toString(),
            'deviceId' => $this->deviceId,
            'clockSkewMs' => $this->clockSkewMs,
            'notes' => $this->notes,
        ];
    }
}
