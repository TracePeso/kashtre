<?php

namespace Tests\Unit\Imaging;

use App\Models\ImagingReport;
use Tests\TestCase;

class ImagingReportTest extends TestCase
{
    public function test_statuses_cover_the_full_reporting_lifecycle(): void
    {
        $this->assertSame([
            ImagingReport::STATUS_DRAFT,
            ImagingReport::STATUS_REPORTED,
            ImagingReport::STATUS_VERIFIED,
            ImagingReport::STATUS_AMENDED,
        ], ImagingReport::STATUSES);
    }

    public function test_is_status_reflects_the_current_status_attribute(): void
    {
        $report = new ImagingReport(['status' => ImagingReport::STATUS_DRAFT]);

        $this->assertTrue($report->isStatus(ImagingReport::STATUS_DRAFT));
        $this->assertFalse($report->isStatus(ImagingReport::STATUS_VERIFIED));
    }
}
