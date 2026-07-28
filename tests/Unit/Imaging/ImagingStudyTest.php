<?php

namespace Tests\Unit\Imaging;

use App\Models\ImagingStudy;
use Tests\TestCase;

class ImagingStudyTest extends TestCase
{
    public function test_accession_candidate_format(): void
    {
        $this->assertSame('ACC-2026-000001', ImagingStudy::buildAccessionCandidate('2026', 1));
        $this->assertSame('ACC-2026-123456', ImagingStudy::buildAccessionCandidate('2026', 123456));
    }

    public function test_statuses_cover_the_full_nine_state_lifecycle_in_order(): void
    {
        $this->assertSame([
            ImagingStudy::STATUS_ORDER_RECEIVED,
            ImagingStudy::STATUS_PREPARATION_REQUIRED,
            ImagingStudy::STATUS_PREPARATION_COMPLETE,
            ImagingStudy::STATUS_READY_FOR_STUDY,
            ImagingStudy::STATUS_IN_PROGRESS,
            ImagingStudy::STATUS_IMAGE_ACQUIRED,
            ImagingStudy::STATUS_REPORT_PENDING,
            ImagingStudy::STATUS_REPORTED,
            ImagingStudy::STATUS_VERIFIED,
        ], ImagingStudy::STATUSES);
    }

    public function test_is_status_reflects_the_current_status_attribute(): void
    {
        $study = new ImagingStudy(['status' => ImagingStudy::STATUS_IN_PROGRESS]);

        $this->assertTrue($study->isStatus(ImagingStudy::STATUS_IN_PROGRESS));
        $this->assertFalse($study->isStatus(ImagingStudy::STATUS_VERIFIED));
    }
}
