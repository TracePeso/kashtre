<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Models\ScheduleDefinition;
use App\Domain\Time\Models\ScheduleOccurrence;
use App\Domain\Time\Models\TimeAudit;
use App\Domain\Time\ValueObjects\IanaTimezoneId;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Domain\Time\ValueObjects\LocalDateTime;
use App\Domain\Time\ValueObjects\UtcInstant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 4 — future scheduling, recurrence, DST-aware materialization.
 */
final class ScheduleService
{
    public function __construct(
        private readonly CivilTimeService $civil,
        private readonly TimeZoneCatalogueService $catalogue,
    ) {}

    public function define(
        string $tenantKey,
        string $code,
        string $name,
        string $ianaId,
        string $localTime,
        string $recurrence,
        LocalDate $startDate,
        ?LocalDate $endDate = null,
        ?array $recurrenceRules = null,
        string $gapPolicy = CivilTimeService::GAP_SHIFT_FORWARD,
        string $overlapPolicy = CivilTimeService::OVERLAP_EARLIER,
    ): ScheduleDefinition {
        $this->catalogue->ensureKnown($ianaId);

        $def = ScheduleDefinition::query()->create([
            'tenant_key' => $tenantKey,
            'code' => strtoupper($code),
            'name' => $name,
            'iana_id' => IanaTimezoneId::of($ianaId)->value(),
            'local_time' => $this->normalizeTime($localTime),
            'recurrence' => strtoupper($recurrence),
            'recurrence_rules' => $recurrenceRules,
            'dst_gap_policy' => $gapPolicy,
            'dst_overlap_policy' => $overlapPolicy,
            'local_start_date' => $startDate->toString(),
            'local_end_date' => $endDate?->toString(),
            'status' => 'ACTIVE',
        ]);

        TimeAudit::query()->create([
            'tenant_key' => $tenantKey,
            'actor_user_id' => Auth::id(),
            'action' => 'SCHEDULE_DEFINE',
            'object_type' => 'core_schedule_definitions',
            'object_public_id' => $def->public_id,
            'after' => $def->toArray(),
        ]);

        return $def;
    }

    /**
     * Materialize occurrences for [from, to] inclusive local dates.
     *
     * @return list<ScheduleOccurrence>
     */
    public function materialize(ScheduleDefinition $definition, LocalDate $from, LocalDate $to): array
    {
        $created = [];
        $cursor = $from;
        $end = $to;
        $defEnd = $definition->local_end_date
            ? LocalDate::parse($definition->local_end_date->format('Y-m-d'))
            : null;
        $defStart = LocalDate::parse($definition->local_start_date->format('Y-m-d'));

        while ($cursor->toString() <= $end->toString()) {
            if ($cursor->toString() < $defStart->toString()) {
                $cursor = $cursor->addDays(1);
                continue;
            }
            if ($defEnd && $cursor->toString() > $defEnd->toString()) {
                break;
            }

            if ($this->matchesRecurrence($definition, $cursor)) {
                $occ = $this->materializeOne($definition, $cursor);
                if ($occ) {
                    $created[] = $occ;
                }
            }

            $cursor = $cursor->addDays(1);
        }

        return $created;
    }

    /**
     * Preview next N UTC instants without persisting.
     *
     * @return list<array{localDate: string, utc: string, note: ?string}>
     */
    public function preview(ScheduleDefinition $definition, int $count = 10, ?LocalDate $from = null): array
    {
        $from ??= LocalDate::parse($definition->local_start_date->format('Y-m-d'));
        $out = [];
        $cursor = $from;
        $guard = 0;

        while (count($out) < $count && $guard < 3660) {
            $guard++;
            if ($this->matchesRecurrence($definition, $cursor)) {
                [$h, $i, $s] = array_map('intval', explode(':', $definition->local_time));
                $local = LocalDateTime::of($cursor->year, $cursor->month, $cursor->day, $h, $i, $s);
                $converted = $this->civil->localToUtc(
                    $local,
                    $definition->iana_id,
                    $definition->dst_gap_policy,
                    $definition->dst_overlap_policy,
                );
                $out[] = [
                    'localDate' => $cursor->toString(),
                    'localDateTime' => $local->toString(),
                    'utc' => $converted['instant']->toIso8601(),
                    'note' => $converted['note'],
                ];
            }
            $cursor = $cursor->addDays(1);
            if ($definition->local_end_date) {
                $end = $definition->local_end_date->format('Y-m-d');
                if ($cursor->toString() > $end) {
                    break;
                }
            }
        }

        return $out;
    }

    private function materializeOne(ScheduleDefinition $definition, LocalDate $date): ?ScheduleOccurrence
    {
        [$h, $i, $s] = array_map('intval', explode(':', $definition->local_time));
        $local = LocalDateTime::of($date->year, $date->month, $date->day, $h, $i, $s);
        $converted = $this->civil->localToUtc(
            $local,
            $definition->iana_id,
            $definition->dst_gap_policy,
            $definition->dst_overlap_policy,
        );

        $utc = $converted['instant'];
        $existing = ScheduleOccurrence::query()
            ->where('schedule_definition_id', $definition->id)
            ->where('occurred_at_utc', $utc->toCarbon())
            ->first();
        if ($existing) {
            return $existing;
        }

        $localCarbon = $utc->toCarbon()->setTimezone($definition->iana_id);

        return ScheduleOccurrence::query()->create([
            'schedule_definition_id' => $definition->id,
            'occurred_at_utc' => $utc->toCarbon(),
            'occurred_local_datetime' => $local->toString(),
            'iana_id' => $definition->iana_id,
            'utc_offset_minutes' => (int) ($localCarbon->offset / 60),
            'materialization_note' => $converted['note'],
        ]);
    }

    private function matchesRecurrence(ScheduleDefinition $definition, LocalDate $date): bool
    {
        $rec = strtoupper($definition->recurrence);
        $rules = $definition->recurrence_rules ?? [];

        return match ($rec) {
            'ONCE' => $date->toString() === LocalDate::parse($definition->local_start_date->format('Y-m-d'))->toString(),
            'DAILY' => true,
            'WEEKLY' => $this->matchesWeekday($date, $rules['weekdays'] ?? null),
            'MONTHLY' => $this->matchesMonthDay($definition, $date),
            default => false,
        };
    }

    private function matchesWeekday(LocalDate $date, ?array $weekdays): bool
    {
        $dow = (int) CarbonImmutable::create($date->year, $date->month, $date->day)->dayOfWeekIso; // 1=Mon
        if ($weekdays === null || $weekdays === []) {
            return true;
        }

        return in_array($dow, array_map('intval', $weekdays), true);
    }

    private function matchesMonthDay(ScheduleDefinition $definition, LocalDate $date): bool
    {
        $rules = $definition->recurrence_rules ?? [];
        $target = isset($rules['day_of_month'])
            ? (int) $rules['day_of_month']
            : (int) $definition->local_start_date->format('j');

        return $date->day === $target;
    }

    private function normalizeTime(string $time): string
    {
        $parts = explode(':', $time);
        $h = str_pad((string) ((int) ($parts[0] ?? 0)), 2, '0', STR_PAD_LEFT);
        $i = str_pad((string) ((int) ($parts[1] ?? 0)), 2, '0', STR_PAD_LEFT);
        $s = str_pad((string) ((int) ($parts[2] ?? 0)), 2, '0', STR_PAD_LEFT);

        return "{$h}:{$i}:{$s}";
    }
}
