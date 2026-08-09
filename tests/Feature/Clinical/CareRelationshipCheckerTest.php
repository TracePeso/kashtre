<?php

namespace Tests\Feature\Clinical;

use App\Models\ClinicalCareAssignment;
use App\Models\ClinicalCareTeam;
use App\Models\ClinicalCareTeamMember;
use App\Services\Clinical\CareRelationshipChecker;
use Tests\TestCase;

class CareRelationshipCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only 'clinical'-connection models are touched in this test (no
        // FK to a real users row), so wrapping just that connection is
        // enough to keep the dev database clean.
        $this->app->make('db')->connection('clinical')->beginTransaction();
        $this->beforeApplicationDestroyed(fn () => $this->app->make('db')->connection('clinical')->rollBack());
    }

    public function test_individual_assignment_grants_the_relationship(): void
    {
        ClinicalCareAssignment::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-001',
            'assignment_model' => ClinicalCareAssignment::MODEL_INDIVIDUAL,
            'primary_nurse_user_id' => 42,
            'is_active' => true,
        ]);

        $checker = app(CareRelationshipChecker::class);

        $this->assertTrue($checker->hasActiveRelationship(42, 'CLIENT-001', 1));
        $this->assertFalse($checker->hasActiveRelationship(99, 'CLIENT-001', 1));
    }

    public function test_team_membership_grants_the_relationship(): void
    {
        $team = ClinicalCareTeam::create([
            'business_id' => 1,
            'team_code' => 'ICU-A',
            'team_name' => 'ICU Team A',
        ]);

        ClinicalCareTeamMember::create([
            'team_id' => $team->id,
            'user_id' => 7,
            'role_code' => 'NURSE',
        ]);

        ClinicalCareAssignment::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-002',
            'assignment_model' => ClinicalCareAssignment::MODEL_TEAM,
            'assigned_team_id' => $team->id,
            'is_active' => true,
        ]);

        $this->assertTrue(app(CareRelationshipChecker::class)->hasActiveRelationship(7, 'CLIENT-002', 1));
    }

    public function test_no_assignment_denies_the_relationship(): void
    {
        $this->assertFalse(app(CareRelationshipChecker::class)->hasActiveRelationship(1, 'CLIENT-NOBODY', 1));
    }

    public function test_claim_individually_creates_and_reuses_an_assignment(): void
    {
        $checker = app(CareRelationshipChecker::class);

        $assignment = $checker->claimIndividually(42, 'nurse', 'CLIENT-003', 'VISIT-003', 1, 1);

        $this->assertSame(ClinicalCareAssignment::MODEL_INDIVIDUAL, $assignment->assignment_model);
        $this->assertSame(42, $assignment->primary_nurse_user_id);

        // Claiming again (e.g. a doctor claiming the same patient) reuses
        // the existing row rather than creating a duplicate.
        $checker->claimIndividually(55, 'doctor', 'CLIENT-003', 'VISIT-003', 1, 1);

        $this->assertSame(1, ClinicalCareAssignment::where('client_id', 'CLIENT-003')->count());
        $this->assertSame(55, $assignment->fresh()->primary_doctor_user_id);
    }
}
