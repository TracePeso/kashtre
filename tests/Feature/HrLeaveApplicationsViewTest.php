<?php

namespace Tests\Feature;

use App\Livewire\ApprovalRequestQueue;
use App\Models\ApprovalWorkflow;
use App\Models\HrApprovalRequest;
use App\Models\Organization;
use App\Models\LeaveType;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HrLeaveApplicationsViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_only_view_shows_only_current_users_pending_and_approved_leave_applications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Test HR Org',
            'external_business_uuid' => 'test-hr-org',
            'weekend_days' => [0, 6],
        ]);

        $currentAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'staff_uuid' => $user->staff_uuid,
            'staff_name' => $user->name,
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $otherAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'staff_uuid' => $otherUser->staff_uuid,
            'staff_name' => $otherUser->name,
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

        LeaveType::create([
            'organization_id' => $organization->id,
            'name' => 'Sick Leave',
            'code' => 'SL',
            'session_type' => LeaveType::SESSION_FULL_DAY,
            'days_deducted_per_workday' => 1,
            'max_days_per_year' => 10,
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

        HrApprovalRequest::create([
            'organization_id' => $organization->id,
            'approval_workflow_id' => $workflow->id,
            'approval_category' => 'leave',
            'leave_type_id' => $annualLeave->id,
            'staff_assignment_id' => $currentAssignment->id,
            'requester_staff_uuid' => $user->staff_uuid,
            'requester_name' => $user->name,
            'subject' => 'My Pending Leave',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'requested_days' => 3,
            'status' => 'pending',
            'current_level' => 'primary',
        ]);

        HrApprovalRequest::create([
            'organization_id' => $organization->id,
            'approval_workflow_id' => $workflow->id,
            'approval_category' => 'leave',
            'leave_type_id' => $annualLeave->id,
            'staff_assignment_id' => $currentAssignment->id,
            'requester_staff_uuid' => $user->staff_uuid,
            'requester_name' => $user->name,
            'subject' => 'My Approved Leave',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-02',
            'requested_days' => 2,
            'status' => 'approved',
            'current_level' => null,
        ]);

        HrApprovalRequest::create([
            'organization_id' => $organization->id,
            'approval_workflow_id' => $workflow->id,
            'approval_category' => 'leave',
            'leave_type_id' => $annualLeave->id,
            'staff_assignment_id' => $currentAssignment->id,
            'requester_staff_uuid' => $user->staff_uuid,
            'requester_name' => $user->name,
            'subject' => 'My Rejected Leave',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-01',
            'requested_days' => 1,
            'status' => 'rejected',
            'current_level' => null,
        ]);

        $needsMyApproval = HrApprovalRequest::create([
            'organization_id' => $organization->id,
            'approval_workflow_id' => $workflow->id,
            'approval_category' => 'leave',
            'leave_type_id' => $annualLeave->id,
            'staff_assignment_id' => $otherAssignment->id,
            'requester_staff_uuid' => $otherUser->staff_uuid,
            'requester_name' => $otherUser->name,
            'subject' => 'Needs My Approval',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
            'requested_days' => 2,
            'status' => 'pending',
            'current_level' => 'primary',
        ]);

        $needsMyApproval->steps()->create([
            'approver_level' => 'primary',
            'approver_staff_uuid' => $user->staff_uuid,
            'approver_name' => $user->name,
            'status' => 'pending',
            'is_current' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(ApprovalRequestQueue::class, ['leaveOnly' => true])
            ->assertSee('My Leave Applications')
            ->assertSee('Leave Days Used')
            ->assertSee('My Pending Leave')
            ->assertSee('My Approved Leave')
            ->assertSee('Annual Leave')
            ->assertDontSee('My Rejected Leave')
            ->assertDontSee('Needs My Approval')
            ->assertDontSee('Sick Leave');
    }
}
