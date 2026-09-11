<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Contracts\Clock;
use App\Domain\Time\Enums\TimeConfidence;
use App\Domain\Time\Enums\TimePrecision;
use App\Domain\Time\Enums\TimeSource;
use App\Domain\Time\ValueObjects\IanaTimezoneId;
use App\Domain\Time\ValueObjects\InstantProvenance;
use App\Domain\Time\ValueObjects\LocalDateTime;
use App\Domain\Time\ValueObjects\UtcInstant;

/**
 * Phase 2 — temporal provenance envelopes for historical events.
 */
final class ProvenanceService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly CivilTimeService $civil,
        private readonly TimeZoneResolutionService $resolution,
    ) {}

    public function fromServer(
        ?UtcInstant $instant = null,
        TimePrecision $precision = TimePrecision::MICROSECOND,
    ): InstantProvenance {
        $at = $instant ?? $this->clock->now();

        return new InstantProvenance(
            instant: $at,
            source: TimeSource::SERVER,
            confidence: TimeConfidence::EXACT,
            precision: $precision,
        );
    }

    public function fromUserLocal(
        LocalDateTime $local,
        IanaTimezoneId|string $ianaId,
        TimePrecision $precision = TimePrecision::SECOND,
    ): InstantProvenance {
        $id = $ianaId instanceof IanaTimezoneId ? $ianaId : IanaTimezoneId::of($ianaId);
        $converted = $this->civil->localToUtc($local, $id);

        return new InstantProvenance(
            instant: $converted['instant'],
            source: TimeSource::USER,
            confidence: $converted['note'] ? TimeConfidence::MEDIUM : TimeConfidence::HIGH,
            precision: $precision,
            sourceTimezone: $id,
            sourceLocal: $local,
            notes: $converted['note'],
        );
    }

    public function fromDevice(
        string $rawTimestamp,
        ?string $rawTimezone,
        ?string $deviceId = null,
        ?int $clockSkewMs = null,
    ): InstantProvenance {
        try {
            if ($rawTimezone) {
                $id = IanaTimezoneId::of($rawTimezone);
                $local = LocalDateTime::parse($rawTimestamp);
                $converted = $this->civil->localToUtc($local, $id);
                $confidence = abs($clockSkewMs ?? 0) > 5000 ? TimeConfidence::LOW : TimeConfidence::MEDIUM;

                return new InstantProvenance(
                    instant: $converted['instant'],
                    source: TimeSource::DEVICE,
                    confidence: $confidence,
                    precision: TimePrecision::SECOND,
                    sourceTimezone: $id,
                    sourceLocal: $local,
                    deviceId: $deviceId,
                    clockSkewMs: $clockSkewMs,
                    notes: $converted['note'],
                );
            }

            $instant = UtcInstant::fromString($rawTimestamp);

            return new InstantProvenance(
                instant: $instant,
                source: TimeSource::DEVICE,
                confidence: abs($clockSkewMs ?? 0) > 5000 ? TimeConfidence::LOW : TimeConfidence::MEDIUM,
                precision: TimePrecision::SECOND,
                deviceId: $deviceId,
                clockSkewMs: $clockSkewMs,
            );
        } catch (\Throwable $e) {
            return new InstantProvenance(
                instant: $this->clock->now(),
                source: TimeSource::DEVICE,
                confidence: TimeConfidence::UNRESOLVED,
                precision: TimePrecision::SECOND,
                deviceId: $deviceId,
                clockSkewMs: $clockSkewMs,
                notes: 'UNRESOLVED: '.$e->getMessage(),
            );
        }
    }

    public function fromExternal(string $iso8601, TimeConfidence $confidence = TimeConfidence::HIGH): InstantProvenance
    {
        return new InstantProvenance(
            instant: UtcInstant::fromString($iso8601),
            source: TimeSource::EXTERNAL,
            confidence: $confidence,
            precision: TimePrecision::SECOND,
        );
    }

    /**
     * Immutable snapshot payload for consumers to persist beside domain events.
     */
    public function snapshotPayload(InstantProvenance $provenance, ?string $displayIana = null): array
    {
        $payload = $provenance->toArray();
        if ($displayIana) {
            $payload['presentation'] = $this->resolution->present($provenance->instant, $displayIana);
            $payload['timezoneSnapshot'] = $this->resolution->snapshot($displayIana, $provenance->instant)->toArray();
        }

        return $payload;
    }
}
