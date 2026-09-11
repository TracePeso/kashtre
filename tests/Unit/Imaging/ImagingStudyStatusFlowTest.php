<?php

namespace Tests\Unit\Imaging;

use App\Models\ImagingProtocol;
use App\Models\ImagingStudy;
use Mockery;
use Tests\TestCase;

class ImagingStudyStatusFlowTest extends TestCase
{
    /**
     * Default behavior (no protocol, or a protocol with requires_preparation
     * true) — Ready For Study is only reachable from PREPARATION_COMPLETE,
     * matching the standard flow every existing protocol already relies on.
     */
    public function test_ready_for_study_requires_preparation_complete_by_default(): void
    {
        $study = new ImagingStudy(['status' => ImagingStudy::STATUS_ORDER_RECEIVED]);
        $this->assertFalse($study->canMarkReadyForStudy());

        $study = new ImagingStudy(['status' => ImagingStudy::STATUS_PREPARATION_COMPLETE]);
        $this->assertTrue($study->canMarkReadyForStudy());
    }

    /**
     * A protocol configured with requires_preparation = false lets a study
     * jump straight from ORDER_RECEIVED to READY_FOR_STUDY — protocol() is
     * mocked so this stays a pure unit test with no real ImagingProtocol
     * row required, matching ImagingStudyDoseAndPriorityTest's pattern.
     */
    public function test_ready_for_study_reachable_directly_from_order_received_when_protocol_skips_preparation(): void
    {
        $study = Mockery::mock(ImagingStudy::class)->makePartial();
        $study->shouldReceive('protocol')->andReturn(new ImagingProtocol(['requires_preparation' => false]));
        $study->status = ImagingStudy::STATUS_ORDER_RECEIVED;

        $this->assertTrue($study->canMarkReadyForStudy());

        // Still blocked once actually in the Preparation phase — skipping
        // the phase means it's bypassed entirely, not merely optional at
        // every point in the sequence.
        $study->status = ImagingStudy::STATUS_PREPARATION_REQUIRED;
        $this->assertFalse($study->canMarkReadyForStudy());
    }

    /**
     * Skipping Preparation doesn't skip the Readiness checklist or consent —
     * both still gate Ready For Study exactly as they do in the standard flow.
     */
    public function test_skipping_preparation_still_requires_readiness_and_consent(): void
    {
        $study = Mockery::mock(ImagingStudy::class)->makePartial();
        $study->shouldReceive('protocol')->andReturn(new ImagingProtocol([
            'requires_preparation' => false,
            'requires_consent' => true,
            'readiness_checks' => ['fasting'],
        ]));
        $study->status = ImagingStudy::STATUS_ORDER_RECEIVED;
        $study->readiness_check_results = null;
        $study->consent_verified = false;

        $this->assertFalse($study->canMarkReadyForStudy());

        $study->readiness_check_results = ['fasting' => true];
        $study->consent_verified = true;
        $this->assertTrue($study->canMarkReadyForStudy());
    }
}
