<?php

namespace Tests\Feature;

use App\Livewire\RosterApprovalWorkflowManager;
use App\Models\ApprovalWorkflow;
use App\Models\HrDutyRoster;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RosterApproverDesignationPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_roster_approver_designation_permission_cannot_save_or_delete_roster_rules(): void
    {
        $organization = Organization::create([
            'name' => 'Permission Test Org',
            'external_business_uuid' => 'permission-test-org',
            'weekend_days' => [0, 6],
        ]);

        $user = User::factory()->create([
            'permissions' => ['View HR Setup', 'Edit HR Setup'],
        ]);

        $this->actingAs($user);
        $this->seedApproverAssignments($organization);

        Livewire::test(RosterApprovalWorkflowManager::class)
            ->set('approverUuids', $this->approverSelections())
            ->call('saveRule')
            ->assertForbidden();

        $this->assertDatabaseCount('hr_approval_workflows', 0);

        $workflow = ApprovalWorkflow::create([
            'organization_id' => $organization->id,
            'approval_category' => 'roster',
            'discipline_title' => null,
            'is_active' => true,
        ]);

        Livewire::test(RosterApprovalWorkflowManager::class)
            ->call('deleteRule', $workflow->id)
            ->assertForbidden();

        $this->assertDatabaseHas('hr_approval_workflows', [
            'id' => $workflow->id,
            'is_active' => true,
        ]);
    }

    public function test_user_with_roster_approver_designation_permission_can_manage_roster_rules(): void
    {
        $organization = Organization::create([
            'name' => 'Authorized Org',
            'external_business_uuid' => 'authorized-org',
            'weekend_days' => [0, 6],
        ]);

        $user = User::factory()->create([
            'permissions' => ['View HR Setup', 'Edit HR Setup', 'Designate HR Roster Approvers'],
        ]);
        $clientSpace = $this->createClientSpace($organization, 'Authorized Ward');

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);
        $this->seedApproverAssignments($organization);

        Livewire::test(RosterApprovalWorkflowManager::class)
            ->set('clientSpaceId', $clientSpace->id)
            ->set('approverUuids', $this->approverSelections())
            ->call('saveRule')
            ->assertHasNoErrors();

        $workflow = ApprovalWorkflow::query()
            ->where('organization_id', $organization->id)
            ->where('approval_category', 'roster')
            ->first();

        $this->assertNotNull($workflow);
        $this->assertSame($clientSpace->id, $workflow->organizational_unit_id);
        $this->assertNull($workflow->discipline_title);
        $this->assertSame(9, $workflow->approvers()->count());
        $this->assertDatabaseHas('hr_approval_workflows', [
            'organization_id' => $organization->id,
            'approval_category' => 'leave',
            'organizational_unit_id' => $clientSpace->id,
            'is_active' => true,
        ]);

        Livewire::test(RosterApprovalWorkflowManager::class)
            ->call('deleteRule', $workflow->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hr_approval_workflows', [
            'id' => $workflow->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('hr_approval_workflows', [
            'organization_id' => $organization->id,
            'approval_category' => 'leave',
            'organizational_unit_id' => $clientSpace->id,
            'is_active' => false,
        ]);
    }

    public function test_selected_client_space_loads_only_existing_rosters_for_that_client_space(): void
    {
        $organization = Organization::create([
            'name' => 'Roster Listing Org',
            'external_business_uuid' => 'roster-listing-org',
            'weekend_days' => [0, 6],
        ]);

        $user = User::factory()->create([
            'permissions' => ['View HR Setup', 'Edit HR Setup', 'Designate HR Roster Approvers'],
        ]);

        $firstClientSpace = $this->createClientSpace($organization, 'Ward A');
        $secondClientSpace = $this->createClientSpace($organization, 'Ward B');

        HrDutyRoster::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $firstClientSpace->id,
            'cadre_or_discipline' => 'Nurse',
            'discipline_titles' => ['Nurse'],
            'name' => 'Ward A June Roster',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => HrDutyRoster::STATUS_DRAFT,
            'approval_status' => HrDutyRoster::APPROVAL_NOT_SUBMITTED,
        ]);

        HrDutyRoster::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $secondClientSpace->id,
            'cadre_or_discipline' => 'Lab',
            'discipline_titles' => ['Lab'],
            'name' => 'Ward B June Roster',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => HrDutyRoster::STATUS_PUBLISHED,
            'approval_status' => HrDutyRoster::APPROVAL_APPROVED,
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);
        $this->seedApproverAssignments($organization);

        Livewire::test(RosterApprovalWorkflowManager::class)
            ->call('openCreateModal')
            ->set('clientSpaceId', $firstClientSpace->id)
            ->assertSee('Ward A June Roster')
            ->assertDontSee('Ward B June Roster');
    }

    public function test_roster_rule_can_be_saved_with_two_approval_levels_only(): void
    {
        $organization = Organization::create([
            'name' => 'Two Level Org',
            'external_business_uuid' => 'two-level-org',
            'weekend_days' => [0, 6],
        ]);

        $user = User::factory()->create([
            'permissions' => ['View HR Setup', 'Edit HR Setup', 'Designate HR Roster Approvers'],
        ]);
        $clientSpace = $this->createClientSpace($organization, 'Ward C');

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);
        $this->seedApproverAssignments($organization);

        Livewire::test(RosterApprovalWorkflowManager::class)
            ->set('clientSpaceId', $clientSpace->id)
            ->set('approvalLevelCount', 2)
            ->set('approverUuids', [
                'primary' => ['approver-1', 'approver-2', 'approver-3'],
                'secondary' => ['approver-4', 'approver-5', 'approver-6'],
                'tertiary' => ['approver-7', 'approver-8', 'approver-9'],
            ])
            ->call('saveRule')
            ->assertHasNoErrors();

        $workflow = ApprovalWorkflow::query()
            ->where('organization_id', $organization->id)
            ->where('approval_category', 'roster')
            ->firstOrFail();

        $leaveWorkflow = ApprovalWorkflow::query()
            ->where('organization_id', $organization->id)
            ->where('approval_category', 'leave')
            ->where('organizational_unit_id', $clientSpace->id)
            ->firstOrFail();

        $this->assertSame(3, $workflow->approvers()->where('approver_level', 'primary')->count());
        $this->assertSame(3, $workflow->approvers()->where('approver_level', 'secondary')->count());
        $this->assertSame(0, $workflow->approvers()->where('approver_level', 'tertiary')->count());
        $this->assertSame(
            $workflow->approvers()->orderBy('approver_level')->orderBy('sort_order')->pluck('approver_staff_uuid')->all(),
            $leaveWorkflow->approvers()->orderBy('approver_level')->orderBy('sort_order')->pluck('approver_staff_uuid')->all()
        );
    }

    private function seedApproverAssignments(Organization $organization): void
    {
        foreach (range(1, 9) as $index) {
            StaffAssignment::create([
                'organization_id' => $organization->id,
                'staff_uuid' => 'approver-'.$index,
                'staff_name' => 'Approver '.$index,
                'assignment_type' => 'primary',
                'status' => 'active',
                'assigned_at' => now(),
            ]);
        }
    }

    private function approverSelections(): array
    {
        return [
            'primary' => ['approver-1', 'approver-2', 'approver-3'],
            'secondary' => ['approver-4', 'approver-5', 'approver-6'],
            'tertiary' => ['approver-7', 'approver-8', 'approver-9'],
        ];
    }

    private function createClientSpace(Organization $organization, string $name): HrOrganizationalUnit
    {
        return HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);
    }
}
