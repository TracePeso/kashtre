<?php

namespace Tests\Feature;

use App\Models\HrAttendanceLedger;
use App\Models\HrCalendarEvent;
use App\Models\HrHolidayLeaveCredit;
use App\Models\HrPolicyVersion;
use App\Models\HrRegionalPolicy;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Services\HolidayLeaveCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrHolidayCompensatoryCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_crossing_public_holiday_shifts_receive_dynamic_quarter_half_three_quarter_and_full_credit(): void
    {
        $cases = [
            ['date' => '2026-12-25', 'in' => '2026-12-24 21:00:00', 'out' => '2026-12-25 01:00:00', 'expected' => '0.25'],
            ['date' => '2026-12-26', 'in' => '2026-12-25 20:00:00', 'out' => '2026-12-26 04:00:00', 'expected' => '0.50'],
            ['date' => '2026-12-27', 'in' => '2026-12-26 20:00:00', 'out' => '2026-12-27 06:00:00', 'expected' => '0.75'],
            ['date' => '2026-12-28', 'in' => '2026-12-27 23:00:00', 'out' => '2026-12-28 08:00:00', 'expected' => '1.00'],
        ];

        foreach ($cases as $index => $case) {
            $organization = $this->createOrganizationWithHolidayPolicy();
            $holiday = $this->createHoliday($organization, $case['date'], $index);
            $assignment = $this->createAssignment($organization, $index);
            $outPunch = $this->createPunchPair(
                $organization,
                $assignment,
                $index,
                $case['in'],
                $case['out']
            );

            app(HolidayLeaveCreditService::class)->createForPairedPunch($outPunch->fresh());

            $credit = HrHolidayLeaveCredit::query()
                ->where('organization_id', $organization->id)
                ->where('staff_assignment_id', $assignment->id)
                ->where('hr_calendar_event_id', $holiday->id)
                ->first();

            $this->assertNotNull($credit, 'No credit created for holiday date '.$case['date'].' and assignment '.$assignment->id);
            $this->assertSame($case['expected'], number_format((float) $credit->credit_days, 2, '.', ''));
        }
    }

    public function test_shifts_fully_within_public_holidays_keep_the_configured_flat_credit(): void
    {
        $organization = $this->createOrganizationWithHolidayPolicy(
            crossingRule: HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT,
            crossingCreditDays: 1.0,
            withinRule: HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT,
            withinCreditDays: 1.25
        );

        $holiday = $this->createHoliday($organization, '2026-12-29', 1);
        $assignment = $this->createAssignment($organization, 1);
        $outPunch = $this->createPunchPair(
            $organization,
            $assignment,
            1,
            '2026-12-29 08:00:00',
            '2026-12-29 16:00:00'
        );

        app(HolidayLeaveCreditService::class)->createForPairedPunch($outPunch->fresh());

        $credit = HrHolidayLeaveCredit::query()
            ->where('organization_id', $organization->id)
            ->where('staff_assignment_id', $assignment->id)
            ->where('hr_calendar_event_id', $holiday->id)
            ->first();

        $this->assertNotNull($credit);
        $this->assertSame('1.25', number_format((float) $credit->credit_days, 2, '.', ''));
    }

    public function test_crossing_public_holiday_date_rule_awards_dynamic_credit_per_matched_date(): void
    {
        $organization = $this->createOrganizationWithHolidayPolicy(
            crossingRule: HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_PUBLIC_HOLIDAY_DATE,
            crossingCreditDays: 1.0
        );

        $firstHoliday = $this->createHoliday($organization, '2026-12-30', 1);
        $secondHoliday = $this->createHoliday($organization, '2026-12-31', 2);
        $assignment = $this->createAssignment($organization, 2);
        $outPunch = $this->createPunchPair(
            $organization,
            $assignment,
            2,
            '2026-12-29 20:00:00',
            '2026-12-31 04:00:00'
        );

        app(HolidayLeaveCreditService::class)->createForPairedPunch($outPunch->fresh());

        $firstCredit = HrHolidayLeaveCredit::query()
            ->where('organization_id', $organization->id)
            ->where('staff_assignment_id', $assignment->id)
            ->where('hr_calendar_event_id', $firstHoliday->id)
            ->first();

        $secondCredit = HrHolidayLeaveCredit::query()
            ->where('organization_id', $organization->id)
            ->where('staff_assignment_id', $assignment->id)
            ->where('hr_calendar_event_id', $secondHoliday->id)
            ->first();

        $this->assertNotNull($firstCredit);
        $this->assertNotNull($secondCredit);
        $this->assertSame('0.75', number_format((float) $firstCredit->credit_days, 2, '.', ''));
        $this->assertSame('0.25', number_format((float) $secondCredit->credit_days, 2, '.', ''));
    }

    private function createOrganizationWithHolidayPolicy(
        string $crossingRule = HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT,
        float $crossingCreditDays = 1.0,
        string $withinRule = HrPolicyVersion::HOLIDAY_COMPENSATORY_CREDIT_PER_SHIFT,
        float $withinCreditDays = 1.0
    ): Organization {
        $organization = Organization::create([
            'name' => 'Holiday Credit Org',
            'external_business_uuid' => 'holiday-credit-org-'.str()->lower((string) str()->uuid()),
            'weekend_days' => [0, 6],
        ]);

        $policy = HrRegionalPolicy::create([
            'organization_id' => $organization->id,
            'policy_code' => 'UG-HOL-'.$organization->id,
            'name' => 'Holiday Policy',
            'is_active' => true,
        ]);

        HrPolicyVersion::create([
            'organization_id' => $organization->id,
            'regional_policy_id' => $policy->id,
            'version_label' => 'v1',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'is_active' => true,
            'weekly_standard_minutes' => 2400,
            'weekly_absolute_ceiling_minutes' => 3360,
            'daily_net_cap_minutes' => 600,
            'minimum_rest_gap_minutes' => 720,
            'consecutive_work_days_limit' => 5,
            'rest_after_consecutive_days_minutes' => 1440,
            'anchor_window_minutes' => 0,
            'overtime_trigger_minutes' => null,
            'metadata' => [
                'holiday_compensatory_credit_settings' => [
                    HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_CROSSING_PUBLIC_HOLIDAY => [
                        'rule' => $crossingRule,
                        'credit_days' => $crossingCreditDays,
                    ],
                    HrPolicyVersion::HOLIDAY_COMPENSATORY_SCOPE_WITHIN_PUBLIC_HOLIDAY => [
                        'rule' => $withinRule,
                        'credit_days' => $withinCreditDays,
                    ],
                ],
            ],
        ]);

        return $organization;
    }

    private function createHoliday(Organization $organization, string $date, int $index): HrCalendarEvent
    {
        return HrCalendarEvent::create([
            'organization_id' => $organization->id,
            'title' => 'Public Holiday '.$index,
            'event_type' => HrCalendarEvent::TYPE_PUBLIC_HOLIDAY,
            'starts_on' => $date,
            'ends_on' => $date,
            'repeats_yearly' => false,
            'affects_rosters' => true,
            'reward_type' => HrCalendarEvent::REWARD_LEAVE_DAY,
            'blocks_rosters' => false,
            'is_active' => true,
            'approval_status' => HrCalendarEvent::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);
    }

    private function createAssignment(Organization $organization, int $index): StaffAssignment
    {
        return StaffAssignment::create([
            'organization_id' => $organization->id,
            'staff_uuid' => 'staff-'.$index,
            'staff_name' => 'Staff '.$index,
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);
    }

    private function createPunchPair(
        Organization $organization,
        StaffAssignment $assignment,
        int $index,
        string $inOccurredAt,
        string $outOccurredAt
    ): HrAttendanceLedger {
        $inPunch = HrAttendanceLedger::create([
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => $assignment->staff_uuid,
            'punch_type' => HrAttendanceLedger::PUNCH_IN,
            'punch_source' => 'test',
            'provider' => 'local',
            'device_id' => 'device-'.$index,
            'source_event_id' => 'in-'.$index.'-'.str()->lower((string) str()->uuid()),
            'occurred_at' => $inOccurredAt,
            'status' => HrAttendanceLedger::STATUS_PAIRED,
        ]);

        return HrAttendanceLedger::create([
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => $assignment->staff_uuid,
            'punch_type' => HrAttendanceLedger::PUNCH_OUT,
            'punch_source' => 'test',
            'provider' => 'local',
            'device_id' => 'device-'.$index,
            'source_event_id' => 'out-'.$index.'-'.str()->lower((string) str()->uuid()),
            'occurred_at' => $outOccurredAt,
            'paired_with_id' => $inPunch->id,
            'status' => HrAttendanceLedger::STATUS_PAIRED,
        ]);
    }
}
