<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\HrApprovalRequest;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Services\DutyRosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterApprovalWorkflowResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_workflow_resolution_prefers_client_space_rule_over_legacy_title_specific_and_global_rules(): void
    {
        $organization = Organization::create([
            'name' => 'Roster Resolution Org',
            'external_business_uuid' => 'roster-resolution-org',
            'weekend_days' => [0, 6],
        ]);

        $clientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'name' => 'Children Ward',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $globalRule = ApprovalWorkflow::create([
            'organization_id' => $organization->id,
            'approval_category' => 'roster',
            'organizational_unit_id' => null,
            'discipline_title' => null,
            'is_active' => true,
        ]);

        $legacyClientSpaceRule = ApprovalWorkflow::create([
            'organization_id' => $organization->id,
            'approval_category' => 'roster',
            'organizational_unit_id' => $clientSpace->id,
            'discipline_title' => 'Nurse',
            'is_active' => true,
        ]);

        $clientSpaceRule = ApprovalWorkflow::create([
            'organization_id' => $organization->id,
            'approval_category' => 'roster',
            'organizational_unit_id' => $clientSpace->id,
            'discipline_title' => null,
            'is_active' => true,
        ]);

        $resolved = app(DutyRosterService::class)->previewRosterApprovalWorkflow($clientSpace);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($clientSpaceRule));
        $this->assertFalse($resolved->is($legacyClientSpaceRule));
        $this->assertFalse($resolved->is($globalRule));
    }

    public function test_two_level_roster_workflow_creates_only_primary_and_secondary_steps(): void
    {
        $organization = Organization::create([
            'name' => 'Two Level Resolution Org',
            'external_business_uuid' => 'two-level-resolution-org',
            'weekend_days' => [0, 6],
        ]);

        $workflow = ApprovalWorkflow::create([
            'organization_id' => $organization->id,
            'approval_category' => 'roster',
            'discipline_title' => null,
            'is_active' => true,
        ]);

        $workflow->syncApprovers('primary', [
            ['uuid' => 'primary-1', 'name' => 'Primary One'],
            ['uuid' => 'primary-2', 'name' => 'Primary Two'],
            ['uuid' => 'primary-3', 'name' => 'Primary Three'],
        ]);
        $workflow->syncApprovers('secondary', [
            ['uuid' => 'secondary-1', 'name' => 'Secondary One'],
            ['uuid' => 'secondary-2', 'name' => 'Secondary Two'],
            ['uuid' => 'secondary-3', 'name' => 'Secondary Three'],
        ]);

        $request = HrApprovalRequest::submitFromWorkflow($workflow, [
            'requester_staff_uuid' => 'requester-1',
            'requester_name' => 'Requester One',
            'subject' => 'Roster approval request',
        ]);

        $this->assertSame('primary', $request->current_level);
        $this->assertSame(3, $request->steps()->where('approver_level', 'primary')->count());
        $this->assertSame(3, $request->steps()->where('approver_level', 'secondary')->count());
        $this->assertSame(0, $request->steps()->where('approver_level', 'tertiary')->count());

        $request->approveCurrentStep($request->steps()->where('approver_level', 'primary')->firstOrFail()->id);
        $request->refresh();
        $this->assertSame('secondary', $request->current_level);

        $request->approveCurrentStep($request->steps()->where('approver_level', 'secondary')->firstOrFail()->id);
        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertNull($request->current_level);
    }
}
