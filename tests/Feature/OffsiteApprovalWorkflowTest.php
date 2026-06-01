<?php

namespace Tests\Feature;

use App\Livewire\ApprovalRequestQueue;
use App\Livewire\ApprovalWorkflowManager;
use App\Models\ApprovalWorkflow;
use App\Models\HrApprovalRequest;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OffsiteApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_offsite_workflows_can_be_scoped_to_a_client_space(): void
    {
        $organization = Organization::create([
            'name' => 'Offsite Workflow Org',
            'external_business_uuid' => 'offsite-workflow-org',
            'weekend_days' => [0, 6],
        ]);

        $user = User::factory()->create([
            'permissions' => ['Add HR Setup', 'Edit HR Setup'],
        ]);

        $clientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'name' => 'Surgical Ward',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $this->seedApproverAssignments($organization);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        Livewire::test(ApprovalWorkflowManager::class)
            ->set('category', 'offsite_duty')
            ->set('clientSpaceId', $clientSpace->id)
            ->set('approverUuids', $this->approverSelections())
            ->call('saveWorkflow')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hr_approval_workflows', [
            'organization_id' => $organization->id,
            'approval_category' => 'offsite_duty',
            'organizational_unit_id' => $clientSpace->id,
            'is_active' => true,
        ]);
    }

    public function test_offsite_submission_uses_selected_client_space_leader_as_primary_approver(): void
    {
        $organization = Organization::create([
            'name' => 'Offsite Queue Org',
            'external_business_uuid' => 'offsite-queue-org',
            'weekend_days' => [0, 6],
        ]);

        $user = User::factory()->create();

        $leaderUnit = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'name' => 'Regional Lead Node',
            'type' => 'Department',
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
            'head_staff_uuid' => 'leader-uuid',
            'head_name' => 'Client Space Leader',
        ]);

        $clientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'parent_id' => $leaderUnit->id,
            'name' => 'Maternity Ward',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $requesterAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $clientSpace->id,
            'staff_uuid' => $user->staff_uuid,
            'staff_name' => $user->name,
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $workflow = ApprovalWorkflow::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $clientSpace->id,
            'approval_category' => 'offsite_duty',
            'is_active' => true,
        ]);

        $this->seedApproverAssignments($organization);

        foreach ($this->approverSelections() as $level => $approvers) {
            $workflow->syncApprovers($level, collect($approvers)
                ->map(fn (string $uuid): array => [
                    'uuid' => $uuid,
                    'name' => str($uuid)->replace('-', ' ')->title()->toString(),
                ])
                ->all());
        }

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        Livewire::test(ApprovalRequestQueue::class)
            ->call('openCreateModal')
            ->set('category', 'offsite_duty')
            ->set('staffAssignmentId', $requesterAssignment->id)
            ->set('leaveClientSpaceId', $clientSpace->id)
            ->set('subject', 'Workshop in Kampala')
            ->set('details', 'External workshop attendance.')
            ->set('leaveStartDate', '2026-06-10')
            ->set('leaveEndDate', '2026-06-12')
            ->call('submitRequest')
            ->assertHasNoErrors();

        $request = HrApprovalRequest::query()
            ->where('organization_id', $organization->id)
            ->where('approval_category', 'offsite_duty')
            ->latest('id')
            ->first();

        $this->assertNotNull($request);
        $this->assertSame($workflow->id, $request->approval_workflow_id);

        $primarySteps = $request->steps()
            ->where('approver_level', 'primary')
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(1, $primarySteps);
        $this->assertSame('leader-uuid', $primarySteps->first()->approver_staff_uuid);
        $this->assertSame('Client Space Leader', $primarySteps->first()->approver_name);

        $secondarySteps = $request->steps()
            ->where('approver_level', 'secondary')
            ->orderBy('sort_order')
            ->pluck('approver_staff_uuid')
            ->all();

        $this->assertSame(['approver-4', 'approver-5', 'approver-6'], $secondarySteps);
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
}
