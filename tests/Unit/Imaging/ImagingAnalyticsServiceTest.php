<?php

namespace Tests\Unit\Imaging;

use App\Services\Imaging\ImagingAnalyticsService;
use Carbon\Carbon;
use Tests\TestCase;

class ImagingAnalyticsServiceTest extends TestCase
{
    public function test_hours_between_returns_null_when_either_timestamp_is_missing(): void
    {
        $this->assertNull(ImagingAnalyticsService::hoursBetween(null, Carbon::now()));
        $this->assertNull(ImagingAnalyticsService::hoursBetween(Carbon::now(), null));
    }

    public function test_hours_between_computes_the_correct_duration(): void
    {
        $start = Carbon::parse('2026-01-01 08:00:00');
        $end = Carbon::parse('2026-01-01 11:30:00');

        $this->assertSame(3.5, ImagingAnalyticsService::hoursBetween($start, $end));
    }

    public function test_hours_between_handles_same_timestamp_as_zero(): void
    {
        $now = Carbon::parse('2026-01-01 08:00:00');

        $this->assertSame(0.0, ImagingAnalyticsService::hoursBetween($now, $now));
    }
}
