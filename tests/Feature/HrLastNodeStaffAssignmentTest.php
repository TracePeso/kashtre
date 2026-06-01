<?php

namespace Tests\Feature;

use App\Livewire\OrganizationalStructure;
use App\Models\HrClientSpaceStaffAssignment;
use App\Models\HrOrganizationalUnit;
use App\Models\HrOrganizationTierLevel;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HrLastNodeStaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_dl_test_staa_assignment_validates_single_staff_member_can_link_to_multiple_client_spaces_with_conflict_checks(): void
    {
        $user = User::factory()->create([
            'permissions' => ['Add HR Setup'],
        ]);

        $organization = Organization::create([
            'name' => 'Direct Link Org',
            'external_business_uuid' => 'direct-link-org',
            'weekend_days' => [0, 6],
        ]);

        $rootLevel = HrOrganizationTierLevel::create([
            'organization_id' => $organization->id,
            'name' => 'Division',
            'level_order' => 1,
        ]);

        $leafLevel = HrOrganizationTierLevel::create([
            'organization_id' => $organization->id,
            'name' => 'Unit',
            'level_order' => 2,
        ]);

        $rootNode = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'tier_level_id' => $rootLevel->id,
            'name' => 'Division',
            'type' => 'Division',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $leafNode = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $rootNode->id,
            'tier_level_id' => $leafLevel->id,
            'name' => 'Unit',
            'type' => 'Unit',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $clientSpaceA = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'name' => 'Ward A',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $clientSpaceB = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'name' => 'Ward B',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $conflictingClientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'name' => 'Ward C',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $staffAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $leafNode->id,
            'staff_uuid' => 'staa-uuid',
            'staff_name' => 'StaA Member',
            'staff_title' => 'Nurse',
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        Livewire::test(OrganizationalStructure::class)
            ->call('openLeafClientSpacesModal', $leafNode->id)
            ->set('selectedLeafClientSpaceIds', [$clientSpaceA->id, $clientSpaceB->id])
            ->call('saveLeafClientSpaces')
            ->assertSet('showLeafStaffModal', true)
            ->assertSet('selectedLeafTargetClientSpaceIds', [$clientSpaceA->id, $clientSpaceB->id])
            ->set('selectedLeafStaffAssignmentIds', [$staffAssignment->id])
            ->call('assignLeafStaffToClientSpaces')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hr_client_space_staff_assignments', [
            'organization_id' => $organization->id,
            'client_space_unit_id' => $clientSpaceA->id,
            'staff_assignment_id' => $staffAssignment->id,
            'assignment_type' => HrClientSpaceStaffAssignment::TYPE_SECONDARY,
            'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('hr_client_space_staff_assignments', [
            'organization_id' => $organization->id,
            'client_space_unit_id' => $clientSpaceB->id,
            'staff_assignment_id' => $staffAssignment->id,
            'assignment_type' => HrClientSpaceStaffAssignment::TYPE_SECONDARY,
            'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
        ]);

        Livewire::test(OrganizationalStructure::class)
            ->call('openLeafStaffModal', $leafNode->id)
            ->set('selectedLeafTargetClientSpaceIds', [$clientSpaceA->id, $conflictingClientSpace->id])
            ->set('selectedLeafStaffAssignmentIds', [$staffAssignment->id])
            ->call('assignLeafStaffToClientSpaces')
            ->assertHasErrors(['selectedLeafTargetClientSpaceIds']);

        $this->assertDatabaseMissing('hr_client_space_staff_assignments', [
            'organization_id' => $organization->id,
            'client_space_unit_id' => $conflictingClientSpace->id,
            'staff_assignment_id' => $staffAssignment->id,
            'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
        ]);
    }
}
