<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\CaptureObservations;
use App\Models\CdeObservation;
use App\Models\ClinicalCareAssignment;
use App\Models\ClinicalUomMaster;
use App\Models\User;
use Database\Seeders\ClinicalMasterDictionariesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CaptureObservationsTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    protected function setUp(): void
    {
        parent::setUp();

        (new ClinicalMasterDictionariesSeeder())->run();
    }

    /**
     * Not User::factory()->create() — see WardCensusBoardTest for why
     * (current_team_id column mismatch in this app's real users table).
     */
    private function userWithPermissions(array $permissions): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'clinical-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'branch_id' => 1,
            'permissions' => $permissions,
        ]);
    }

    public function test_a_user_without_view_permission_is_blocked(): void
    {
        $user = $this->userWithPermissions([]);

        Livewire::actingAs($user)
            ->test(CaptureObservations::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_it_shows_a_claim_banner_when_the_user_has_no_active_relationship(): void
    {
        $user = $this->userWithPermissions(['View Clinical Observations']);

        Livewire::actingAs($user)
            ->test(CaptureObservations::class, ['clientId' => 'CLIENT-001'])
            ->assertSee('No active care assignment for you on this patient.');
    }

    public function test_claiming_the_patient_creates_an_individual_assignment(): void
    {
        $user = $this->userWithPermissions(['View Clinical Observations', 'Manage Care Assignments', 'Act As Ward Nurse (Clinical)']);

        Livewire::actingAs($user)
            ->test(CaptureObservations::class, ['clientId' => 'CLIENT-001'])
            ->call('claim', 'nurse')
            ->assertDontSee('No active care assignment for you on this patient.');

        $this->assertSame($user->id, ClinicalCareAssignment::where('client_id', 'CLIENT-001')->value('primary_nurse_user_id'));
    }

    public function test_claiming_without_the_matching_role_capacity_is_blocked(): void
    {
        $user = $this->userWithPermissions(['View Clinical Observations', 'Manage Care Assignments']);

        Livewire::actingAs($user)
            ->test(CaptureObservations::class, ['clientId' => 'CLIENT-002'])
            ->call('claim', 'nurse')
            ->assertForbidden();

        $this->assertSame(0, ClinicalCareAssignment::where('client_id', 'CLIENT-002')->count());
    }

    public function test_claiming_as_doctor_requires_the_consultant_capacity(): void
    {
        $user = $this->userWithPermissions(['View Clinical Observations', 'Manage Care Assignments', 'Act As Consultant (Clinical)']);

        Livewire::actingAs($user)
            ->test(CaptureObservations::class, ['clientId' => 'CLIENT-003'])
            ->call('claim', 'doctor')
            ->assertDontSee('No active care assignment for you on this patient.');

        $this->assertSame($user->id, ClinicalCareAssignment::where('client_id', 'CLIENT-003')->value('primary_doctor_user_id'));
    }

    public function test_it_captures_a_numeric_observation_and_updates_the_flowsheet(): void
    {
        $user = $this->userWithPermissions(['View Clinical Observations', 'Add Clinical Observations']);
        $mgdlId = ClinicalUomMaster::whereNull('business_id')->where('unit_label', 'mg/dL')->value('id');

        Livewire::actingAs($user)
            ->test(CaptureObservations::class, ['clientId' => 'CLIENT-001'])
            ->set('values.GLUCOSE_RANDOM', 126.1)
            ->set('inputUnits.GLUCOSE_RANDOM', $mgdlId)
            ->call('save')
            ->assertSee('126.1')
            ->assertSet('captureErrors', []);

        $observation = CdeObservation::where('client_id', 'CLIENT-001')->where('cde_code', 'GLUCOSE_RANDOM')->first();
        $this->assertNotNull($observation);
        $this->assertEqualsWithDelta(7.0, (float) $observation->base_value_numeric, 0.1);
    }

    public function test_a_physiologically_implausible_value_is_rejected_with_a_visible_error(): void
    {
        $user = $this->userWithPermissions(['View Clinical Observations', 'Add Clinical Observations']);
        $mmolId = ClinicalUomMaster::whereNull('business_id')->where('unit_label', 'mmol/L')->value('id');

        Livewire::actingAs($user)
            ->test(CaptureObservations::class, ['clientId' => 'CLIENT-001'])
            ->set('values.GLUCOSE_RANDOM', 180)
            ->set('inputUnits.GLUCOSE_RANDOM', $mmolId)
            ->call('save')
            ->assertSee('HEURISTIC_SAFETY_BLOCK');

        $this->assertSame(0, CdeObservation::where('client_id', 'CLIENT-001')->count());
    }
}
