<?php

namespace Tests\Feature;

use App\Livewire\OrganizationLeaveDashboard;
use App\Models\ApprovalWorkflow;
use App\Models\HrApprovalRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HrOrganizationLeaveDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_leave_dashboard_shows_pending_and_approved_leave_requests_for_the_whole_organization(): void
    {
        $approver = User::factory()->create([
            'permissions' => ['View HR Approvals'],
        ]);
        $requester = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Test HR Org',
            'external_business_uuid' => 'test-hr-org',
            'weekend_days' => [0, 6],
        ]);

        $requesterAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'staff_uuid' => $requester->staff_uuid,
            'staff_name' => $requester->name,
            'staff_title' => 'Nurse',
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $annualLeave = LeaveType::create([
            'organization_id' => $organization->id,
            'name' => 'Annual Leave',
            'code' => 'AL',
            'session_type' => LeaveType::SESSION_FULL_DAY,
            'days_deducted_per_workday' => 1,
            'max_days_per_year' => 30,
            'tracks_balance' => true,
            'is_paid' => true,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $workflow = ApprovalWorkflow::create([
            'organization_id' => $organization->id,
            'approval_category' => 'leave',
            'discipline_title' => 'Leave Workflow',
            'is_active' => true,
        ]);

        $pendingRequest = HrApprovalRequest::create([
            'organization_id' => $organization->id,
            'approval_workflow_id' => $workflow->id,
            'approval_category' => 'leave',
            'leave_type_id' => $annualLeave->id,
            'staff_assignment_id' => $requesterAssignment->id,
            'requester_staff_uuid' => $requester->staff_uuid,
            'requester_name' => $requester->name,
            'subject' => 'Pending Annual Leave',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'requested_days' => 3,
            'status' => 'pending',
            'current_level' => 'primary',
        ]);

        $pendingRequest->steps()->create([
            'approver_level' => 'primary',
            'approver_staff_uuid' => 'another-approver',
            'approver_name' => 'Another Approver',
            'status' => 'pending',
            'is_current' => true,
            'sort_order' => 0,
        ]);

        HrApprovalRequest::create([
            'organization_id' => $organization->id,
            'approval_workflow_id' => $workflow->id,
            'approval_category' => 'leave',
            'leave_type_id' => $annualLeave->id,
            'staff_assignment_id' => $requesterAssignment->id,
            'requester_staff_uuid' => $requester->staff_uuid,
            'requester_name' => $requester->name,
            'subject' => 'Approved Annual Leave',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'requested_days' => 3,
            'status' => 'approved',
            'current_level' => null,
        ]);

        HrApprovalRequest::create([
            'organization_id' => $organization->id,
            'approval_workflow_id' => $workflow->id,
            'approval_category' => 'leave',
            'leave_type_id' => $annualLeave->id,
            'staff_assignment_id' => $requesterAssignment->id,
            'requester_staff_uuid' => $requester->staff_uuid,
            'requester_name' => $requester->name,
            'subject' => 'Rejected Annual Leave',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-02',
            'requested_days' => 2,
            'status' => 'rejected',
            'current_level' => null,
        ]);

        HrApprovalRequest::create([
            'organization_id' => $organization->id,
            'approval_workflow_id' => $workflow->id,
            'approval_category' => 'roster',
            'staff_assignment_id' => $requesterAssignment->id,
            'requester_staff_uuid' => $requester->staff_uuid,
            'requester_name' => $requester->name,
            'subject' => 'Roster Approval',
            'status' => 'pending',
            'current_level' => 'primary',
        ]);

        $this->actingAs($approver);

        Livewire::test(OrganizationLeaveDashboard::class)
            ->assertSee('Organization Leave Dashboard')
            ->assertSee('Pending Annual Leave')
            ->assertSee('Approved Annual Leave')
            ->assertSee('Annual Leave')
            ->assertDontSee('Rejected Annual Leave')
            ->assertDontSee('Roster Approval')
            ->set('statusFilter', 'approved')
            ->assertSee('Approved Annual Leave')
            ->assertDontSee('Pending Annual Leave');
    }

    public function test_edit_hr_approvals_user_can_approve_pending_leave_from_dashboard(): void
    {
        $approver = User::factory()->create([
            'permissions' => ['Edit HR Approvals'],
        ]);
        $requester = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Test HR Org',
            'external_business_uuid' => 'test-hr-org',
            'weekend_days' => [0, 6],
        ]);

        $requesterAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'staff_uuid' => $requester->staff_uuid,
            'staff_name' => $requester->name,
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $annualLeave = LeaveType::create([
            'organization_id' => $organization->id,
            'name' => 'Annual Leave',
            'code' => 'AL',
            'session_type' => LeaveType::SESSION_FULL_DAY,
            'days_deducted_per_workday' => 1,
            'max_days_per_year' => 30,
            'tracks_balance' => true,
            'is_paid' => true,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $workflow = ApprovalWorkflow::create([
            'organization_id' => $organization->id,
            'approval_category' => 'leave',
            'discipline_title' => 'Leave Workflow',
            'is_active' => true,
        ]);

        $request = HrApprovalRequest::create([
            'organization_id' => $organization->id,
            'approval_workflow_id' => $workflow->id,
            'approval_category' => 'leave',
            'leave_type_id' => $annualLeave->id,
            'staff_assignment_id' => $requesterAssignment->id,
            'requester_staff_uuid' => $requester->staff_uuid,
            'requester_name' => $requester->name,
            'subject' => 'Pending Leave To Approve',
            'start_date' => '2026-06-20',
            'end_date' => '2026-06-20',
            'requested_days' => 1,
            'status' => 'pending',
            'current_level' => 'primary',
        ]);

        $request->steps()->create([
            'approver_level' => 'primary',
            'approver_staff_uuid' => $approver->staff_uuid,
            'approver_name' => $approver->name,
            'status' => 'pending',
            'is_current' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($approver);

        Livewire::test(OrganizationLeaveDashboard::class)
            ->call('approveRequest', $request->id)
            ->assertSee('Approval recorded.');

        $this->assertDatabaseHas('hr_approval_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'current_level' => null,
        ]);
    }
}
