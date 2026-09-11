<?php

namespace Tests\Unit\Imaging;

use App\Models\ImagingStudy;
use Tests\TestCase;

class ImagingStudyChecklistTest extends TestCase
{
    public function test_checklist_is_satisfied_when_no_items_required(): void
    {
        $study = new ImagingStudy(['readiness_check_results' => null]);

        $method = new \ReflectionMethod($study, 'isChecklistSatisfied');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($study, []));
    }

    public function test_checklist_is_not_satisfied_until_every_item_is_checked(): void
    {
        $study = new ImagingStudy(['readiness_check_results' => ['fasting' => true]]);

        $method = new \ReflectionMethod($study, 'isChecklistSatisfied');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($study, ['fasting', 'contrast_screening']));

        $study->readiness_check_results = ['fasting' => true, 'contrast_screening' => true];
        $this->assertTrue($method->invoke($study, ['fasting', 'contrast_screening']));
    }

    public function test_can_mark_ready_for_study_requires_correct_status(): void
    {
        $study = new ImagingStudy(['status' => ImagingStudy::STATUS_ORDER_RECEIVED]);

        $this->assertFalse($study->canMarkReadyForStudy());
    }

    public function test_is_consent_satisfied_true_without_a_protocol(): void
    {
        // No protocol_code set → protocol() resolves null → nothing to require consent for.
        $study = new ImagingStudy(['consent_verified' => false]);

        $this->assertTrue($study->isConsentSatisfied());
    }
}
