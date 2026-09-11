<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\MyPatientTasksBoard;
use App\Models\ClinicalBed;
use App\Models\ClinicalCareAssignment;
use App\Models\ClinicalWard;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The ServiceDeliveryQueue projection itself (numeric clients.id vs.
 * string Client::client_id bridging) isn't exercised here — a real
 * ServiceDeliveryQueue row needs branch/service_point/invoice/item
 * fixtures unrelated to this chunk. The query itself is a plain
 * whereIn+groupBy; the ward-grouping half (no such heavy fixtures needed)
 * is covered instead.
 */
class MyPatientTasksBoardTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    private function userWithPermissions(array $permissions): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'my-tasks-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'permissions' => $permissions,
        ]);
    }

    public function test_a_user_without_permission_is_blocked(): void
    {
        $user = $this->userWithPermissions([]);

        Livewire::actingAs($user)
            ->test(MyPatientTasksBoard::class)
            ->assertForbidden();
    }

    public function test_it_shows_my_patient_count_and_groups_admitted_patients_by_ward(): void
    {
        $user = $this->userWithPermissions(['View Ward Census']);

        ClinicalCareAssignment::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-MYTASK-1',
            'assignment_model' => ClinicalCareAssignment::MODEL_INDIVIDUAL,
            'primary_nurse_user_id' => $user->id,
            'is_active' => true,
        ]);

        $ward = ClinicalWard::create(['business_id' => 1, 'ward_code' => 'MYTASK-WARD', 'ward_name' => 'My Task Ward']);
        ClinicalBed::create([
            'ward_id' => $ward->id,
            'bed_code' => 'BED-01',
            'operational_state' => ClinicalBed::STATE_OCCUPIED,
            'current_client_id' => 'CLIENT-MYTASK-1',
        ]);

        Livewire::actingAs($user)
            ->test(MyPatientTasksBoard::class)
            ->assertSee('My Task Ward')
            ->assertSee('BED-01')
            ->assertViewHas('myPatientCount', 1);
    }
}
