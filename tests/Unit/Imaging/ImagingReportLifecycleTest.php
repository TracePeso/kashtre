<?php

namespace Tests\Unit\Imaging;

use App\Models\ImagingReport;
use Tests\TestCase;

class ImagingReportLifecycleTest extends TestCase
{
    public function test_mark_reported_rejects_a_non_draft_report(): void
    {
        $report = new ImagingReport(['status' => ImagingReport::STATUS_VERIFIED]);

        $this->expectException(\RuntimeException::class);
        $report->markReported(1);
    }

    public function test_mark_verified_accepts_reported_or_amended(): void
    {
        $method = new \ReflectionMethod(ImagingReport::class, 'requireStatus');
        $method->setAccessible(true);

        $reported = new ImagingReport(['status' => ImagingReport::STATUS_REPORTED]);
        $amended = new ImagingReport(['status' => ImagingReport::STATUS_AMENDED]);
        $draft = new ImagingReport(['status' => ImagingReport::STATUS_DRAFT]);

        // Neither of these should throw.
        $method->invoke($reported, ImagingReport::STATUS_REPORTED, ImagingReport::STATUS_AMENDED);
        $method->invoke($amended, ImagingReport::STATUS_REPORTED, ImagingReport::STATUS_AMENDED);

        $this->expectException(\RuntimeException::class);
        $method->invoke($draft, ImagingReport::STATUS_REPORTED, ImagingReport::STATUS_AMENDED);
    }

    public function test_mark_amended_rejects_a_report_that_is_not_verified(): void
    {
        $report = new ImagingReport(['status' => ImagingReport::STATUS_DRAFT]);

        $this->expectException(\RuntimeException::class);
        $report->markAmended(1, 'Correction needed', ['Impression' => 'Updated finding']);
    }
}
