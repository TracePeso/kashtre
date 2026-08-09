<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\ClinicalProcessPanel;
use App\Models\ClinicalBed;
use App\Models\ClinicalWard;
use App\Models\User;
use Database\Seeders\ClinicalProcessRegistrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicalProcessPanelTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    protected function setUp(): void
    {
        parent::setUp();

        (new ClinicalProcessRegistrySeeder())->run();
    }

    private function userWithPermissions(array $permissions): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'process-panel-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'permissions' => $permissions,
        ]);
    }

    public function test_a_user_without_view_permission_is_blocked(): void
    {
        $user = $this->userWithPermissions([]);

        Livewire::actingAs($user)
            ->test(ClinicalProcessPanel::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_decision_to_admit_starts_the_admission_process_and_walks_to_bed_allocation(): void
    {
        // ADMISSION's nursing-assessment steps are role-gated — this user
        // needs the ward-nurse capacity to walk the full happy path.
        $user = $this->userWithPermissions(['View Clinical Process Registry', 'Progress Clinical Process Registry', 'Act As Ward Nurse (Clinical)']);
        $ward = ClinicalWard::create(['business_id' => 1, 'ward_code' => 'PANEL-WARD', 'ward_name' => 'Panel Test Ward']);
        $bed = ClinicalBed::create(['ward_id' => $ward->id, 'bed_code' => 'BED-01']);

        $component = Livewire::actingAs($user)
            ->test(ClinicalProcessPanel::class, ['clientId' => 'CLIENT-001'])
            ->set('selectedProcessCode', 'ADMISSION')
            ->set('initiationNote', 'Admit for observation')
            ->call('startProcess')
            ->assertSee('Admission Workflow');

        $executionId = \App\Models\ClinicalProcessExecution::where('client_id', 'CLIENT-001')->value('id');

        // Walk through the first 4 steps (no side effects).
        for ($i = 0; $i < 4; $i++) {
            $component->call('completeStep', $executionId);
        }

        $component->assertSee('Bed Allocation');

        $component->set('selectedBedId', $bed->id)
            ->call('completeStep', $executionId);

        $this->assertSame(ClinicalBed::STATE_OCCUPIED, $bed->fresh()->operational_state);
    }

    public function test_a_role_gated_step_is_blocked_without_the_matching_capacity_permission(): void
    {
        // Has the generic progress permission but not the ward-nurse
        // capacity ADMISSION's second step requires.
        $user = $this->userWithPermissions(['View Clinical Process Registry', 'Progress Clinical Process Registry']);

        $component = Livewire::actingAs($user)
            ->test(ClinicalProcessPanel::class, ['clientId' => 'CLIENT-ROLE-1'])
            ->set('selectedProcessCode', 'ADMISSION')
            ->call('startProcess');

        $executionId = \App\Models\ClinicalProcessExecution::where('client_id', 'CLIENT-ROLE-1')->value('id');

        // Step 1 (ADMISSION_ASSESSMENT) has no required_role — passes.
        $component->call('completeStep', $executionId);

        // Step 2 (INITIAL_NURSING_ASSESSMENT) requires WARD_NURSE — blocked.
        $component->call('completeStep', $executionId)->assertForbidden();
    }

    public function test_a_role_gated_step_succeeds_with_the_matching_capacity_permission(): void
    {
        $user = $this->userWithPermissions(['View Clinical Process Registry', 'Progress Clinical Process Registry', 'Act As Ward Nurse (Clinical)']);

        $component = Livewire::actingAs($user)
            ->test(ClinicalProcessPanel::class, ['clientId' => 'CLIENT-ROLE-2'])
            ->set('selectedProcessCode', 'ADMISSION')
            ->call('startProcess');

        $executionId = \App\Models\ClinicalProcessExecution::where('client_id', 'CLIENT-ROLE-2')->value('id');

        $component->call('completeStep', $executionId); // step 1, unrestricted
        $component->call('completeStep', $executionId); // step 2, WARD_NURSE — should succeed

        $this->assertSame(
            2,
            \App\Models\ClinicalProcessStepExecution::whereHas('execution', fn ($q) => $q->where('client_id', 'CLIENT-ROLE-2'))->count()
        );
    }
}
