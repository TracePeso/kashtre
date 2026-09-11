<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\ValueObjects\IanaTimezoneId;
use App\Domain\Time\ValueObjects\LocalDateTime;
use App\Domain\Time\ValueObjects\UtcInstant;
use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * Civil-time ↔ UTC conversion with DST gap/overlap handling (Phases 1 & 4).
 */
final class CivilTimeService
{
    public const GAP_SHIFT_FORWARD = 'SHIFT_FORWARD';

    public const GAP_SHIFT_BACK = 'SHIFT_BACK';

    public const GAP_REJECT = 'REJECT';

    public const OVERLAP_EARLIER = 'EARLIER';

    public const OVERLAP_LATER = 'LATER';

    public const OVERLAP_REJECT = 'REJECT';

    /**
     * @return array{instant: UtcInstant, note: ?string, offsetSeconds: int}
     */
    public function localToUtc(
        LocalDateTime $local,
        IanaTimezoneId|string $ianaId,
        string $gapPolicy = self::GAP_SHIFT_FORWARD,
        string $overlapPolicy = self::OVERLAP_EARLIER,
    ): array {
        $tz = ($ianaId instanceof IanaTimezoneId ? $ianaId : IanaTimezoneId::of($ianaId))->toDateTimeZone();
        $wall = sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d',
            $local->year,
            $local->month,
            $local->day,
            $local->hour,
            $local->minute,
            $local->second,
        );

        $transitions = $this->probeTransitions($wall, $tz);

        if ($transitions['nonexistent']) {
            return $this->handleGap($local, $tz, $gapPolicy, $wall);
        }

        if (count($transitions['instants']) > 1) {
            return $this->handleOverlap($transitions['instants'], $overlapPolicy, $wall);
        }

        if (count($transitions['instants']) === 0) {
            throw TimeEngineException::localNonexistent($wall, $tz->getName());
        }

        $carbon = $transitions['instants'][0];

        return [
            'instant' => UtcInstant::fromDateTime($carbon),
            'note' => null,
            'offsetSeconds' => $carbon->setTimezone($tz)->offset,
        ];
    }

    public function utcToLocal(UtcInstant $instant, IanaTimezoneId|string $ianaId): LocalDateTime
    {
        $tz = ($ianaId instanceof IanaTimezoneId ? $ianaId : IanaTimezoneId::of($ianaId))->toDateTimeZone();
        $local = $instant->toCarbon()->setTimezone($tz);

        return LocalDateTime::fromCarbon($local);
    }

    /**
     * @return array{nonexistent: bool, instants: list<CarbonImmutable>}
     */
    private function probeTransitions(string $wall, DateTimeZone $tz): array
    {
        // Try both fold=0 and fold=1 style by creating with UTC guess then verifying.
        $candidates = [];
        try {
            $a = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $wall, $tz);
            if ($a !== false) {
                $candidates[] = $a->utc();
            }
        } catch (\Throwable) {
            // ignore
        }

        // Alternative: construct via timestamp search around the wall time.
        $base = CarbonImmutable::parse($wall, 'UTC');
        for ($offsetHours = -14; $offsetHours <= 14; $offsetHours++) {
            $guess = $base->subHours($offsetHours);
            $formatted = $guess->setTimezone($tz)->format('Y-m-d H:i:s');
            if ($formatted === $wall) {
                $candidates[] = $guess->utc();
            }
        }

        $unique = [];
        foreach ($candidates as $c) {
            $unique[$c->timestamp.'.'.$c->micro] = $c;
        }
        $instants = array_values($unique);

        // Nonexistent: wall time does not appear for any UTC guess in ±14h.
        $nonexistent = count($instants) === 0;

        return ['nonexistent' => $nonexistent, 'instants' => $instants];
    }

    /**
     * @return array{instant: UtcInstant, note: ?string, offsetSeconds: int}
     */
    private function handleGap(LocalDateTime $local, DateTimeZone $tz, string $policy, string $wall): array
    {
        if ($policy === self::GAP_REJECT) {
            throw TimeEngineException::localNonexistent($wall, $tz->getName());
        }

        $shiftMinutes = $policy === self::GAP_SHIFT_BACK ? -60 : 60;

        $carbonLocal = CarbonImmutable::create(
            $local->year,
            $local->month,
            $local->day,
            $local->hour,
            $local->minute,
            $local->second,
            $tz,
        )->addMinutes($shiftMinutes);

        // Re-resolve after shift.
        $newLocal = LocalDateTime::fromCarbon($carbonLocal);
        $result = $this->localToUtc($newLocal, IanaTimezoneId::of($tz->getName()), self::GAP_REJECT, self::OVERLAP_EARLIER);
        $result['note'] = "DST_GAP_{$policy}";

        return $result;
    }

    /**
     * @param  list<CarbonImmutable>  $instants
     * @return array{instant: UtcInstant, note: ?string, offsetSeconds: int}
     */
    private function handleOverlap(array $instants, string $policy, string $wall): array
    {
        if ($policy === self::OVERLAP_REJECT) {
            throw TimeEngineException::localAmbiguous($wall, 'overlap');
        }

        usort($instants, fn (CarbonImmutable $a, CarbonImmutable $b) => $a->timestamp <=> $b->timestamp);
        $chosen = $policy === self::OVERLAP_LATER ? end($instants) : $instants[0];

        return [
            'instant' => UtcInstant::fromDateTime($chosen),
            'note' => "DST_OVERLAP_{$policy}",
            'offsetSeconds' => 0,
        ];
    }
}
