<?php

namespace Tests\Feature;

use App\Livewire\ClientSpaceDirectory;
use App\Models\HrClientSpaceStaffAssignment;
use App\Models\HrOrganizationalUnit;
use App\Models\HrStaffRosteringProfile;
use App\Models\Organization;
use App\Models\ShiftType;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientSpaceShiftPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_preference_can_be_saved_from_client_space_directory_for_linked_staff(): void
    {
        $organization = Organization::create([
            'name' => 'Client Space Shift Org',
            'external_business_uuid' => 'client-space-shift-org',
            'weekend_days' => [0, 6],
        ]);

        $user = User::factory()->create([
            'permissions' => ['View HR Setup'],
        ]);

        $routingNode = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'name' => 'Routing Node A',
            'type' => 'Routing Node',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
            'metadata' => [
                HrOrganizationalUnit::METADATA_LAST_ROUTING_NODE => true,
            ],
        ]);

        $clientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $routingNode->id,
            'name' => 'Ward Delta',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $assignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $routingNode->id,
            'staff_uuid' => 'staff-delta',
            'staff_name' => 'Daisy Delta',
            'staff_title' => 'Nurse',
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        HrClientSpaceStaffAssignment::create([
            'organization_id' => $organization->id,
            'client_space_unit_id' => $clientSpace->id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => $assignment->staff_uuid,
            'assignment_type' => HrClientSpaceStaffAssignment::TYPE_SECONDARY,
            'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);

        $dayShift = ShiftType::create([
            'organization_id' => $organization->id,
            'name' => 'Day Shift',
            'code' => 'DAY',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'color' => '#2563EB',
            'is_active' => true,
            'is_rosterable' => true,
        ]);

        $nightShift = ShiftType::create([
            'organization_id' => $organization->id,
            'name' => 'Night Shift',
            'code' => 'NGT',
            'start_time' => '20:00:00',
            'end_time' => '06:00:00',
            'color' => '#0F172A',
            'is_active' => true,
            'is_rosterable' => true,
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        Livewire::test(ClientSpaceDirectory::class)
            ->call('openShiftPreferenceModal', $clientSpace->id, $assignment->id)
            ->set('shiftPreferenceForm.rostering_mode', HrStaffRosteringProfile::MODE_FIXED)
            ->set('shiftPreferenceForm.fixed_shift_type_id', $dayShift->id)
            ->set('shiftPreferenceForm.fixed_days_of_week', [1, 2, 3])
            ->set('shiftPreferenceForm.preferred_shift_type_ids', [$dayShift->id])
            ->set('shiftPreferenceForm.excluded_shift_type_ids', [$nightShift->id])
            ->set('shiftPreferenceForm.max_night_shifts_per_cycle', 0)
            ->set('shiftPreferenceForm.notes', 'Client-space level shift preference edit.')
            ->call('saveShiftPreference')
            ->assertHasNoErrors()
            ->assertSee('Shift preference updated.');

        $this->assertDatabaseHas('hr_staff_rostering_profiles', [
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'rostering_mode' => HrStaffRosteringProfile::MODE_FIXED,
            'fixed_shift_type_id' => $dayShift->id,
            'max_night_shifts_per_cycle' => 0,
            'is_active' => true,
        ]);
    }
}
