<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ShiftType;
use Carbon\CarbonImmutable;

class HrDefaultShiftTypeService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'code' => 'RWH',
                'name' => 'Regular working Hours',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_durations_minutes' => [
                    ['duration_minutes' => 60],
                ],
                'color' => '#0F766E',
                'is_system_default' => true,
            ],
            [
                'code' => 'EARLY',
                'name' => 'Early Shift',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'break_durations_minutes' => [
                    ['duration_minutes' => 30],
                ],
                'color' => '#2563EB',
                'is_system_default' => false,
            ],
            [
                'code' => 'DAY',
                'name' => 'Day Shift',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_durations_minutes' => [
                    ['duration_minutes' => 60],
                ],
                'color' => '#16A34A',
                'is_system_default' => false,
            ],
            [
                'code' => 'EVENING',
                'name' => 'Evening Shift',
                'start_time' => '14:00:00',
                'end_time' => '22:00:00',
                'break_durations_minutes' => [
                    ['duration_minutes' => 30],
                ],
                'color' => '#F97316',
                'is_system_default' => false,
            ],
            [
                'code' => 'NIGHT',
                'name' => 'Night Shift',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'break_durations_minutes' => [
                    ['duration_minutes' => 45],
                ],
                'color' => '#7C3AED',
                'is_system_default' => false,
            ],
            [
                'code' => 'EXT',
                'name' => 'Extended Shift',
                'start_time' => '08:00:00',
                'end_time' => '20:00:00',
                'break_durations_minutes' => [
                    ['duration_minutes' => 60],
                    ['duration_minutes' => 30],
                ],
                'color' => '#DC2626',
                'is_system_default' => false,
            ],
        ];
    }

    public function seedMissingDefaults(Organization $organization): int
    {
        $this->normalizeLegacyRegularWorkingHoursShift($organization);

        $existingByCode = ShiftType::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy(fn (ShiftType $shiftType): string => strtoupper((string) $shiftType->code));

        $created = 0;

        foreach ($this->definitions() as $definition) {
            $code = strtoupper((string) $definition['code']);
            $existing = $existingByCode->get($code);

            if (! $existing) {
                ShiftType::create($this->payload($organization, $definition));
                $created++;
                continue;
            }

            if ($code === 'RWH' && strcasecmp((string) $existing->name, 'Regular working Hours') !== 0) {
                $existing->fill($this->payload($organization, $definition, true))->save();
                continue;
            }

            if ($code === 'DAY' && strcasecmp((string) $existing->name, 'Day Shift') !== 0) {
                $existing->fill($this->payload($organization, $definition, $existing->is_system_default))->save();
            }
        }

        $hasSystemDefault = ShiftType::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->where('is_system_default', true)
            ->exists();

        if (! $hasSystemDefault) {
            $regularShift = ShiftType::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at')
                ->where('code', 'RWH')
                ->first();

            if ($regularShift) {
                $regularShift->forceFill(['is_system_default' => true])->save();
            }
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function payload(Organization $organization, array $definition, ?bool $isSystemDefault = null): array
    {
        $breakDurations = $definition['break_durations_minutes'] ?? [];
        $grossMinutes = $this->grossDurationMinutes(
            (string) $definition['start_time'],
            (string) $definition['end_time'],
        );
        $breakMinutes = collect($breakDurations)
            ->sum(fn (array $entry): int => max(0, (int) ($entry['duration_minutes'] ?? 0)));

        return [
            'organization_id' => $organization->id,
            'name' => $definition['name'],
            'code' => $definition['code'],
            'start_time' => $definition['start_time'],
            'end_time' => $definition['end_time'],
            'break_durations_minutes' => $breakDurations,
            'gross_duration_minutes' => $grossMinutes,
            'break_duration_minutes' => $breakMinutes,
            'net_duration_minutes' => max(0, $grossMinutes - $breakMinutes),
            'color' => $definition['color'] ?? null,
            'is_active' => true,
            'is_rosterable' => true,
            'is_system_default' => $isSystemDefault ?? (bool) ($definition['is_system_default'] ?? false),
        ];
    }

    private function grossDurationMinutes(string $startTime, string $endTime): int
    {
        $start = CarbonImmutable::parse('2000-01-01 '.$startTime);
        $end = CarbonImmutable::parse('2000-01-01 '.$endTime);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    private function normalizeLegacyRegularWorkingHoursShift(Organization $organization): void
    {
        $legacyRegularShift = ShiftType::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->where('code', 'DAY')
            ->where('name', 'Regular working Hours')
            ->first();

        if (! $legacyRegularShift) {
            return;
        }

        $legacyRegularShift->forceFill([
            'code' => 'RWH',
            'is_system_default' => true,
        ])->save();
    }
}
