<?php

namespace Tests\Feature\Time;

use App\Domain\Time\Enums\PolicyPurpose;
use App\Domain\Time\Enums\PolicyScopeType;
use App\Domain\Time\Services\FixedClock;
use App\Domain\Time\Services\SharedTimeGateway;
use App\Domain\Time\ValueObjects\LocalDate;
use App\Domain\Time\ValueObjects\LocalDateTime;
use App\Domain\Time\ValueObjects\TimeContext;
use Database\Seeders\Time\CoreTimeEngineSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SharedTimeEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \App\Domain\Time\Models\CoreTimeZone::query()->where('iana_id', 'UTC')->exists()) {
            $this->seed(CoreTimeEngineSeeder::class);
        }

        config(['time.enabled' => true]);
    }

    public function test_clock_returns_utc_instant(): void
    {
        $fixed = new FixedClock('2026-06-01T12:00:00.000000Z');
        $this->app->instance(\App\Domain\Time\Contracts\Clock::class, $fixed);
        $this->app->forgetInstance(SharedTimeGateway::class);

        $time = app(SharedTimeGateway::class);
        $this->assertSame('2026-06-01T12:00:00.000000Z', $time->now()->toIso8601());
    }

    public function test_resolves_tenant_policy_over_platform_fallback(): void
    {
        $time = app(SharedTimeGateway::class);
        $draft = $time->policies()->draft(
            '99',
            PolicyScopeType::TENANT,
            '99',
            'Africa/Kampala',
            PolicyPurpose::OPERATIONAL,
        );
        $time->policies()->activate($draft);

        $resolved = $time->resolution()->resolve(new TimeContext(tenantId: '99'), '99');
        $this->assertSame('Africa/Kampala', $resolved->ianaId->value());
        $this->assertFalse($resolved->usedFallback);
    }

    public function test_business_date_and_day_window(): void
    {
        $time = app(SharedTimeGateway::class);
        $date = LocalDate::of(2026, 9, 6);
        $window = $time->businessDates()->localDayWindow($date, 'Africa/Kampala');

        $this->assertSame('2026-09-06', $window['localDate']);
        $this->assertTrue($window['start']->isBefore($window['end']));
    }

    public function test_schedule_preview_daily(): void
    {
        $time = app(SharedTimeGateway::class);
        $def = $time->schedules()->define(
            '99',
            'TEST_DAILY',
            'Test',
            'Africa/Kampala',
            '09:00:00',
            'DAILY',
            LocalDate::of(2026, 9, 1),
            LocalDate::of(2026, 9, 10),
        );
        $preview = $time->schedules()->preview($def, 3, LocalDate::of(2026, 9, 1));
        $this->assertCount(3, $preview);
        $this->assertSame('2026-09-01', $preview[0]['localDate']);
    }

    public function test_financial_period_close_blocks_assert(): void
    {
        $time = app(SharedTimeGateway::class);
        $period = $time->periods()->open(
            '99',
            '2026-09',
            'Sep 2026',
            LocalDate::of(2026, 9, 1),
            LocalDate::of(2026, 9, 30),
        );
        $time->periods()->close($period);

        $this->expectException(\App\Domain\Time\Exceptions\TimeEngineException::class);
        $time->periods()->assertOpenFor('99', LocalDate::of(2026, 9, 15));
    }

    public function test_device_observation_accepts_iso_utc(): void
    {
        $time = app(SharedTimeGateway::class);
        $row = $time->devices()->observe('99', 'DEV-A', '2026-09-06T10:00:00Z', null, 100);
        $this->assertSame('ACCEPTED', $row->status);
        $this->assertNotNull($row->normalized_at_utc);
    }

    public function test_civil_conversion_kampala(): void
    {
        $time = app(SharedTimeGateway::class);
        $local = LocalDateTime::of(2026, 9, 6, 15, 0, 0);
        $result = $time->civil()->localToUtc($local, 'Africa/Kampala');
        $this->assertSame('2026-09-06T12:00:00.000000Z', $result['instant']->toIso8601());
    }
}
