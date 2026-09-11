<?php

namespace Tests\Unit\Imaging;

use App\Models\PeerReviewCase;
use Tests\TestCase;

class PeerReviewCaseTest extends TestCase
{
    public function test_should_trigger_is_a_simple_roll_under_or_equal_check(): void
    {
        $this->assertTrue(PeerReviewCase::shouldTrigger(4, 1));
        $this->assertTrue(PeerReviewCase::shouldTrigger(4, 4));
        $this->assertFalse(PeerReviewCase::shouldTrigger(4, 5));
        $this->assertFalse(PeerReviewCase::shouldTrigger(0, 1));
        $this->assertTrue(PeerReviewCase::shouldTrigger(100, 100));
    }

    public function test_mark_completed_rejects_a_case_that_is_not_awaiting_review(): void
    {
        $case = new PeerReviewCase([
            'qa_status' => PeerReviewCase::QA_STATUS_COMPLETED,
            'original_author_user_id' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $case->markCompleted(2, PeerReviewCase::VARIATION_CONCORDANT, null, []);
    }

    public function test_mark_completed_rejects_the_original_author_reviewing_their_own_case(): void
    {
        $case = new PeerReviewCase([
            'qa_status' => PeerReviewCase::QA_STATUS_AWAITING_BLIND_READ,
            'original_author_user_id' => 7,
        ]);

        $this->expectException(\RuntimeException::class);
        $case->markCompleted(7, PeerReviewCase::VARIATION_CONCORDANT, null, []);
    }

    public function test_mark_completed_rejects_an_invalid_variation_score(): void
    {
        $case = new PeerReviewCase([
            'qa_status' => PeerReviewCase::QA_STATUS_AWAITING_BLIND_READ,
            'original_author_user_id' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $case->markCompleted(2, 'NOT_A_REAL_SCORE', null, []);
    }
}
