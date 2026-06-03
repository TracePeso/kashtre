<?php

namespace Tests\Feature;

use App\Livewire\ClientSpaceDirectory;
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

    public function test_client_space_directory_add_staff_links_last_node_staff_after_leaf_setup(): void
    {
        $user = User::factory()->create([
            'is_hr_admin' => true,
            'permissions' => ['Add HR Setup'],
        ]);

        $organization = Organization::create([
            'name' => 'Leaf Directory Org',
            'external_business_uuid' => 'leaf-directory-org',
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

        $clientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'name' => 'Ward A',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $staffAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $leafNode->id,
            'staff_uuid' => 'leaf-directory-staff',
            'staff_name' => 'Leaf Nurse',
            'staff_title' => 'Nurse',
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        Livewire::test(OrganizationalStructure::class)
            ->call('openLeafClientSpacesModal', $leafNode->id)
            ->set('selectedLeafClientSpaceIds', [$clientSpace->id])
            ->call('saveLeafClientSpaces')
            ->assertSet('showLeafStaffModal', true)
            ->assertSet('selectedLeafTargetClientSpaceId', null);

        $this->assertTrue($leafNode->fresh()->isMarkedAsLastRoutingNode());
        $this->assertTrue($clientSpace->fresh()->isLinkedToRoutingNode($leafNode->fresh()));

        Livewire::test(ClientSpaceDirectory::class)
            ->call('openAddStaffModal', $clientSpace->id)
            ->assertSet('showAddStaffModal', true)
            ->call('selectAllVisibleStaff')
            ->assertSet('selectedStaffAssignmentIds', [$staffAssignment->id])
            ->call('addStaffToClientSpace')
            ->assertHasNoErrors()
            ->assertSet('showAddStaffModal', false)
            ->assertSet('selectedStaffAssignmentIds', []);

        $this->assertDatabaseHas('hr_client_space_staff_assignments', [
            'organization_id' => $organization->id,
            'client_space_unit_id' => $clientSpace->id,
            'staff_assignment_id' => $staffAssignment->id,
            'assignment_type' => HrClientSpaceStaffAssignment::TYPE_SECONDARY,
            'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
        ]);

        $this->assertSame($leafNode->id, $staffAssignment->fresh()->organizational_unit_id);
    }

    public function test_client_space_directory_rejects_staff_outside_attached_last_routes(): void
    {
        $user = User::factory()->create([
            'is_hr_admin' => true,
            'permissions' => ['Add HR Setup'],
        ]);

        $organization = Organization::create([
            'name' => 'Leaf Directory Guard Org',
            'external_business_uuid' => 'leaf-directory-guard-org',
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

        $attachedLeafNode = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $rootNode->id,
            'tier_level_id' => $leafLevel->id,
            'name' => 'Unit A',
            'type' => 'Unit',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $otherLeafNode = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $rootNode->id,
            'tier_level_id' => $leafLevel->id,
            'name' => 'Unit B',
            'type' => 'Unit',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $clientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => null,
            'name' => 'Ward A',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $eligibleAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $attachedLeafNode->id,
            'staff_uuid' => 'eligible-last-node-staff',
            'staff_name' => 'Eligible Nurse',
            'staff_title' => 'Nurse',
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $ineligibleAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $otherLeafNode->id,
            'staff_uuid' => 'other-last-node-staff',
            'staff_name' => 'Other Nurse',
            'staff_title' => 'Nurse',
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        Livewire::test(OrganizationalStructure::class)
            ->call('openLeafClientSpacesModal', $attachedLeafNode->id)
            ->set('selectedLeafClientSpaceIds', [$clientSpace->id])
            ->call('saveLeafClientSpaces')
            ->assertSet('showLeafStaffModal', true);

        Livewire::test(ClientSpaceDirectory::class)
            ->call('openAddStaffModal', $clientSpace->id)
            ->call('selectAllVisibleStaff')
            ->assertSet('selectedStaffAssignmentIds', [$eligibleAssignment->id])
            ->set('selectedStaffAssignmentIds', [$eligibleAssignment->id, $ineligibleAssignment->id])
            ->call('addStaffToClientSpace')
            ->assertHasErrors(['selectedStaffAssignmentIds']);

        $this->assertDatabaseMissing('hr_client_space_staff_assignments', [
            'organization_id' => $organization->id,
            'client_space_unit_id' => $clientSpace->id,
            'staff_assignment_id' => $eligibleAssignment->id,
            'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseMissing('hr_client_space_staff_assignments', [
            'organization_id' => $organization->id,
            'client_space_unit_id' => $clientSpace->id,
            'staff_assignment_id' => $ineligibleAssignment->id,
            'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
        ]);
    }

    public function test_last_node_staff_prompt_stays_populated_and_can_link_staff_if_client_space_modal_unit_state_is_cleared(): void
    {
        $user = User::factory()->create([
            'is_hr_admin' => true,
            'permissions' => ['Add HR Setup'],
        ]);

        $organization = Organization::create([
            'name' => 'Leaf Prompt Persistence Org',
            'external_business_uuid' => 'leaf-prompt-persistence-org',
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
            'name' => 'Unit A',
            'type' => 'Unit',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $clientSpace = HrOrganizationalUnit::create([
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

        $staffAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $leafNode->id,
            'staff_uuid' => 'prompt-persistence-staff',
            'staff_name' => 'Prompt Nurse',
            'staff_title' => 'Nurse',
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        Livewire::test(OrganizationalStructure::class)
            ->call('openLeafClientSpacesModal', $leafNode->id)
            ->set('selectedLeafClientSpaceIds', [$clientSpace->id, $clientSpaceB->id])
            ->call('saveLeafClientSpaces')
            ->assertSet('showLeafStaffModal', true)
            ->assertSet('selectedLeafStaffUnitId', $leafNode->id)
            ->assertSet('selectedLeafTargetClientSpaceId', null)
            ->set('selectedLeafUnitId', null)
            ->assertSee($leafNode->name)
            ->assertSee($clientSpace->name)
            ->assertSee($clientSpaceB->name)
            ->assertDontSee('primary staff')
            ->call('selectLeafTargetClientSpace', $clientSpace->id)
            ->assertSee($staffAssignment->staff_name)
            ->set('selectedLeafStaffAssignmentIds', [$staffAssignment->id])
            ->call('assignLeafStaffToClientSpaces')
            ->assertHasNoErrors()
            ->assertSet('showLeafStaffModal', true)
            ->assertSet('selectedLeafStaffAssignmentIds', [])
            ->call('backToLeafClientSpacePicker')
            ->assertSet('selectedLeafTargetClientSpaceId', null)
            ->call('selectLeafTargetClientSpace', $clientSpaceB->id)
            ->set('selectedLeafStaffAssignmentIds', [$staffAssignment->id])
            ->call('assignLeafStaffToClientSpaces')
            ->assertHasNoErrors()
            ->assertSet('showLeafStaffModal', true)
            ->assertSet('selectedLeafStaffAssignmentIds', []);

        $this->assertDatabaseHas('hr_client_space_staff_assignments', [
            'organization_id' => $organization->id,
            'client_space_unit_id' => $clientSpace->id,
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
    }

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
            ->assertSet('selectedLeafTargetClientSpaceId', null)
            ->call('selectLeafTargetClientSpace', $clientSpaceA->id)
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

        Livewire::test(OrganizationalStructure::class)
            ->call('openLeafStaffModal', $leafNode->id)
            ->set('selectedLeafTargetClientSpaceId', $conflictingClientSpace->id)
            ->set('selectedLeafStaffAssignmentIds', [$staffAssignment->id])
            ->call('assignLeafStaffToClientSpaces')
            ->assertHasErrors(['selectedLeafTargetClientSpaceId']);

        $this->assertDatabaseMissing('hr_client_space_staff_assignments', [
            'organization_id' => $organization->id,
            'client_space_unit_id' => $conflictingClientSpace->id,
            'staff_assignment_id' => $staffAssignment->id,
            'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
        ]);
    }
}
