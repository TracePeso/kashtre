<?php

namespace App\Services;

use App\Models\LeaveType;
use App\Models\Organization;

class HrDefaultLeaveTypeService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'code' => 'L',
                'name' => 'Annual Leave (Full Day)',
                'session_type' => LeaveType::SESSION_FULL_DAY,
                'days_deducted_per_workday' => 1.0,
                'max_days_per_year' => 19,
                'balance_group_code' => 'L',
                'tracks_balance' => true,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'L1',
                'name' => 'Annual Leave (Morning Absent)',
                'session_type' => LeaveType::SESSION_MORNING_ABSENT,
                'days_deducted_per_workday' => 0.5,
                'max_days_per_year' => 19,
                'balance_group_code' => 'L',
                'tracks_balance' => true,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'L2',
                'name' => 'Annual Leave (Afternoon Absent)',
                'session_type' => LeaveType::SESSION_AFTERNOON_ABSENT,
                'days_deducted_per_workday' => 0.5,
                'max_days_per_year' => 19,
                'balance_group_code' => 'L',
                'tracks_balance' => true,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'S',
                'name' => 'Sickness Leave (Full Day)',
                'session_type' => LeaveType::SESSION_FULL_DAY,
                'days_deducted_per_workday' => 1.0,
                'max_days_per_year' => null,
                'balance_group_code' => 'S',
                'tracks_balance' => false,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'S1',
                'name' => 'Sickness Leave (Morning Absent)',
                'session_type' => LeaveType::SESSION_MORNING_ABSENT,
                'days_deducted_per_workday' => 0.5,
                'max_days_per_year' => null,
                'balance_group_code' => 'S',
                'tracks_balance' => false,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'S2',
                'name' => 'Sickness Leave (Afternoon Absent)',
                'session_type' => LeaveType::SESSION_AFTERNOON_ABSENT,
                'days_deducted_per_workday' => 0.5,
                'max_days_per_year' => null,
                'balance_group_code' => 'S',
                'tracks_balance' => false,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'P',
                'name' => 'Maternity or Paternity',
                'session_type' => LeaveType::SESSION_FULL_DAY,
                'days_deducted_per_workday' => 1.0,
                'max_days_per_year' => null,
                'balance_group_code' => 'P',
                'tracks_balance' => false,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'C',
                'name' => 'Compassionate Leave',
                'session_type' => LeaveType::SESSION_FULL_DAY,
                'days_deducted_per_workday' => 1.0,
                'max_days_per_year' => null,
                'balance_group_code' => 'C',
                'tracks_balance' => false,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'U',
                'name' => 'UPA Leave',
                'session_type' => LeaveType::SESSION_FULL_DAY,
                'days_deducted_per_workday' => 1.0,
                'max_days_per_year' => null,
                'balance_group_code' => 'U',
                'tracks_balance' => false,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'W',
                'name' => 'Work From Home',
                'session_type' => LeaveType::SESSION_FULL_DAY,
                'days_deducted_per_workday' => 1.0,
                'max_days_per_year' => null,
                'balance_group_code' => 'W',
                'tracks_balance' => false,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ],
            [
                'code' => 'R',
                'name' => 'R&R/Days without pay',
                'session_type' => LeaveType::SESSION_FULL_DAY,
                'days_deducted_per_workday' => 1.0,
                'max_days_per_year' => null,
                'balance_group_code' => 'R',
                'tracks_balance' => false,
                'is_paid' => false,
                'requires_approval' => true,
                'is_active' => true,
            ],
        ];
    }

    public function seedMissingDefaults(Organization $organization): int
    {
        $existingByCode = LeaveType::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy(fn (LeaveType $leaveType): string => strtoupper((string) $leaveType->code));

        $created = 0;

        foreach ($this->definitions() as $definition) {
            $code = strtoupper((string) $definition['code']);
            $existing = $existingByCode->get($code);

            if (! $existing) {
                LeaveType::create($this->payload($organization, $definition));
                $created++;
                continue;
            }

            $existing->fill($this->payload($organization, $definition))->save();
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function payload(Organization $organization, array $definition): array
    {
        return [
            'organization_id' => $organization->id,
            'name' => $definition['name'],
            'code' => $definition['code'],
            'session_type' => $definition['session_type'],
            'days_deducted_per_workday' => $definition['days_deducted_per_workday'],
            'max_days_per_year' => $definition['max_days_per_year'],
            'balance_group_code' => $definition['balance_group_code'] ?? strtoupper((string) $definition['code']),
            'tracks_balance' => $definition['tracks_balance'],
            'is_paid' => $definition['is_paid'],
            'requires_approval' => $definition['requires_approval'],
            'is_active' => $definition['is_active'],
        ];
    }
}
