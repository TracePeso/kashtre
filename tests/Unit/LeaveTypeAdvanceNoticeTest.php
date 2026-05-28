<?php

namespace Tests\Unit;

use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LeaveTypeAdvanceNoticeTest extends TestCase
{
    public function test_advance_notice_summary_reports_no_rule_when_days_are_zero(): void
    {
        $leaveType = new LeaveType([
            'advance_notice_timing' => LeaveType::NOTICE_PRE,
            'advance_notice_days' => 0,
        ]);

        $this->assertSame('No notice requirement', $leaveType->advanceNoticeSummary());
        $this->assertNull(
            $leaveType->advanceNoticeValidationMessage(
                CarbonImmutable::parse('2026-05-28'),
                CarbonImmutable::parse('2026-05-28')
            )
        );
    }

    public function test_pre_notice_rule_blocks_start_dates_that_are_too_soon(): void
    {
        $leaveType = new LeaveType([
            'advance_notice_timing' => LeaveType::NOTICE_PRE,
            'advance_notice_days' => 3,
        ]);

        $this->assertSame(
            '3 day(s) before leave starts',
            $leaveType->advanceNoticeSummary()
        );

        $this->assertSame(
            'This leave type requires at least 3 day(s) notice before the leave start date.',
            $leaveType->advanceNoticeValidationMessage(
                CarbonImmutable::parse('2026-05-28'),
                CarbonImmutable::parse('2026-05-30')
            )
        );

        $this->assertNull(
            $leaveType->advanceNoticeValidationMessage(
                CarbonImmutable::parse('2026-05-28'),
                CarbonImmutable::parse('2026-05-31')
            )
        );
    }

    public function test_post_notice_rule_blocks_leave_reported_too_late(): void
    {
        $leaveType = new LeaveType([
            'advance_notice_timing' => LeaveType::NOTICE_POST,
            'advance_notice_days' => 2,
        ]);

        $this->assertSame(
            '2 day(s) after leave starts',
            $leaveType->advanceNoticeSummary()
        );

        $this->assertSame(
            'This leave type must be reported within 2 day(s) after the leave start date.',
            $leaveType->advanceNoticeValidationMessage(
                CarbonImmutable::parse('2026-05-28'),
                CarbonImmutable::parse('2026-05-25')
            )
        );

        $this->assertNull(
            $leaveType->advanceNoticeValidationMessage(
                CarbonImmutable::parse('2026-05-28'),
                CarbonImmutable::parse('2026-05-26')
            )
        );
    }
}
