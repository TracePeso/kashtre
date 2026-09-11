<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Contracts\Clock;
use App\Domain\Time\Enums\DeviceObservationStatus;
use App\Domain\Time\Enums\TimeConfidence;
use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\Models\DeviceTimeObservation;
use App\Domain\Time\Models\TimeAudit;
use App\Domain\Time\ValueObjects\IanaTimezoneId;
use App\Domain\Time\ValueObjects\InstantProvenance;
use App\Domain\Time\ValueObjects\LocalDateTime;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 6 — device / offline timestamp normalization and quarantine.
 */
final class DeviceTimeService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly ProvenanceService $provenance,
        private readonly TimeZoneResolutionService $resolution,
        private readonly CivilTimeService $civil,
        private readonly LeapSecondService $leapSeconds,
    ) {}

    public function observe(
        string $tenantKey,
        string $devicePublicId,
        string $rawTimestamp,
        ?string $rawTimezone = null,
        ?int $clockSkewMs = null,
        array $metadata = [],
    ): DeviceTimeObservation {
        try {
            $leap = $this->leapSeconds->inspect($rawTimestamp);
            if (! $leap['accepted']) {
                $this->leapSeconds->auditRejected($tenantKey, $rawTimestamp, $leap['action']);

                return DeviceTimeObservation::query()->create([
                    'tenant_key' => $tenantKey,
                    'device_public_id' => $devicePublicId,
                    'raw_timestamp' => $rawTimestamp,
                    'raw_timezone' => $rawTimezone,
                    'status' => DeviceObservationStatus::QUARANTINED->value,
                    'confidence' => TimeConfidence::UNRESOLVED->value,
                    'quarantine_reason' => 'Leap-second second=60 quarantined by platform policy.',
                    'metadata' => array_merge($metadata, ['leapSecond' => $leap]),
                ]);
            }
            $rawTimestamp = $leap['normalized'] ?? $rawTimestamp;
        } catch (TimeEngineException $e) {
            $this->leapSeconds->auditRejected($tenantKey, $rawTimestamp, 'REJECT');
            throw $e;
        }

        $prov = $this->provenance->fromDevice($rawTimestamp, $rawTimezone, $devicePublicId, $clockSkewMs);

        $status = DeviceObservationStatus::ACCEPTED;
        $reason = null;

        if ($prov->confidence === TimeConfidence::UNRESOLVED) {
            $status = DeviceObservationStatus::QUARANTINED;
            $reason = $prov->notes ?? 'Unable to normalize device timestamp.';
        } elseif ($clockSkewMs !== null && abs($clockSkewMs) > 120_000) {
            $status = DeviceObservationStatus::QUARANTINED;
            $reason = 'Device clock skew exceeds 120 seconds.';
        } elseif ($this->isImplausible($prov, $clockSkewMs)) {
            $status = DeviceObservationStatus::QUARANTINED;
            $reason = 'Timestamp is implausible relative to trusted clock.';
        }

        $local = null;
        if ($rawTimezone && $status === DeviceObservationStatus::ACCEPTED) {
            try {
                $local = $this->civil->utcToLocal($prov->instant, $rawTimezone)->toString();
            } catch (\Throwable) {
                $local = null;
            }
        }

        $row = DeviceTimeObservation::query()->create([
            'tenant_key' => $tenantKey,
            'device_public_id' => $devicePublicId,
            'raw_timestamp' => $rawTimestamp,
            'raw_timezone' => $rawTimezone,
            'normalized_at_utc' => $status === DeviceObservationStatus::ACCEPTED ? $prov->instant->toCarbon() : null,
            'normalized_local_datetime' => $local,
            'status' => $status->value,
            'confidence' => $prov->confidence->value,
            'quarantine_reason' => $reason,
            'metadata' => array_merge($metadata, [
                'provenance' => $prov->toArray(),
                'clockSkewMs' => $clockSkewMs,
            ]),
        ]);

        TimeAudit::query()->create([
            'tenant_key' => $tenantKey,
            'actor_user_id' => Auth::id(),
            'action' => $status === DeviceObservationStatus::QUARANTINED ? 'DEVICE_TIME_QUARANTINE' : 'DEVICE_TIME_ACCEPT',
            'object_type' => 'core_device_time_observations',
            'object_public_id' => $row->public_id,
            'after' => $row->toArray(),
            'reason' => $reason,
        ]);

        return $row;
    }

    public function correct(
        DeviceTimeObservation $observation,
        string $correctedIsoUtc,
        ?string $reason = null,
    ): DeviceTimeObservation {
        if ($observation->status === DeviceObservationStatus::ACCEPTED->value
            && $observation->normalized_at_utc) {
            // already accepted; allow correction path
        }

        $before = $observation->toArray();
        $instant = \App\Domain\Time\ValueObjects\UtcInstant::fromString($correctedIsoUtc);
        $local = null;
        if ($observation->raw_timezone) {
            $local = $this->civil->utcToLocal($instant, $observation->raw_timezone)->toString();
        }

        $observation->update([
            'normalized_at_utc' => $instant->toCarbon(),
            'normalized_local_datetime' => $local,
            'status' => DeviceObservationStatus::CORRECTED->value,
            'confidence' => TimeConfidence::HIGH->value,
            'quarantine_reason' => null,
        ]);
        $fresh = $observation->fresh();

        TimeAudit::query()->create([
            'tenant_key' => $observation->tenant_key,
            'actor_user_id' => Auth::id(),
            'action' => 'DEVICE_TIME_CORRECT',
            'object_type' => 'core_device_time_observations',
            'object_public_id' => $fresh->public_id,
            'before' => $before,
            'after' => $fresh->toArray(),
            'reason' => $reason,
        ]);

        return $fresh;
    }

    public function requireAccepted(DeviceTimeObservation $observation): InstantProvenance
    {
        if (! in_array($observation->status, [
            DeviceObservationStatus::ACCEPTED->value,
            DeviceObservationStatus::CORRECTED->value,
        ], true)) {
            throw TimeEngineException::quarantine($observation->quarantine_reason ?? 'Device time quarantined.');
        }

        return new InstantProvenance(
            instant: \App\Domain\Time\ValueObjects\UtcInstant::fromDateTime($observation->normalized_at_utc),
            source: \App\Domain\Time\Enums\TimeSource::DEVICE,
            confidence: TimeConfidence::from($observation->confidence),
            precision: \App\Domain\Time\Enums\TimePrecision::SECOND,
            sourceTimezone: $observation->raw_timezone ? IanaTimezoneId::tryOf($observation->raw_timezone) : null,
            sourceLocal: $observation->normalized_local_datetime
                ? LocalDateTime::parse($observation->normalized_local_datetime)
                : null,
            deviceId: $observation->device_public_id,
        );
    }

    private function isImplausible(InstantProvenance $prov, ?int $clockSkewMs): bool
    {
        if ($prov->confidence === TimeConfidence::UNRESOLVED) {
            return true;
        }

        $now = $this->clock->now()->toCarbon();
        $at = $prov->instant->toCarbon();

        // More than 30 days in the future, or more than 5 years in the past.
        if ($at->gt($now->addDays(30))) {
            return true;
        }
        if ($at->lt($now->subYears(5))) {
            return true;
        }

        return false;
    }
}
