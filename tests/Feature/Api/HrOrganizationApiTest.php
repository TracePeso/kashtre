<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Business;
use App\Models\ClientSpace;
use App\Models\Department;
use App\Models\KashtreHrModuleSetting;
use App\Models\OrgAssignment;
use App\Models\OrgUnit;
use App\Models\ReportingRelationship;
use App\Models\StaffDeployment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrOrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'hr-org-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        KashtreHrModuleSetting::query()->create([
            'url' => 'http://hr.test',
            'api_key' => self::API_KEY,
            'sync_enabled' => true,
        ]);
        KashtreHrModuleSetting::forgetCache();
    }

    public function test_org_structure_endpoints_require_the_hr_api_key(): void
    {
        $this->getJson('/api/org-units')->assertUnauthorized();
        $this->getJson('/api/hr/assignments')->assertUnauthorized();
    }

    public function test_hr_can_read_org_units_assignments_reporting_and_deployments(): void
    {
        $business = Business::query()->create([
            'name' => 'Org API Clinic',
            'email' => 'org-api@example.com',
            'address' => 'Demo City',
            'account_number' => 'KS-ORG-API',
        ]);
        $other = Business::query()->create([
            'name' => 'Other Clinic',
            'email' => 'other-org@example.com',
            'address' => 'Demo City',
            'account_number' => 'KS-OTHER-ORG',
        ]);

        $branch = Branch::query()->create([
            'business_id' => $business->id,
            'name' => 'Main Clinic',
            'email' => 'main@example.com',
            'address' => 'Demo City',
        ]);
        $department = Department::query()->create([
            'business_id' => $business->id,
            'name' => 'Nursing',
        ]);
        $user = User::factory()->create([
            'name' => 'Grace Njeri',
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $space = ClientSpace::query()->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'name' => 'Recovery Bay',
        ]);

        $root = OrgUnit::query()->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'external_id' => 'MCC-OU-001',
            'name' => 'Executive Office',
            'org_unit_type' => 'Executive Office',
            'org_level' => 1,
            'is_terminal' => false,
            'org_path' => 'Executive Office',
        ]);
        $team = OrgUnit::query()->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'parent_id' => $root->id,
            'head_user_id' => $user->id,
            'external_id' => 'MCC-OU-005',
            'name' => 'Nursing and Recovery Team',
            'org_unit_type' => 'Team',
            'head_name' => 'Grace Njeri',
            'org_level' => 2,
            'is_terminal' => true,
            'org_path' => 'Executive Office > Nursing and Recovery Team',
        ]);
        $wing = OrgUnit::query()->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'parent_id' => $root->id,
            'external_id' => 'MCC-OU-010',
            'name' => 'Clinical Wing',
            'org_unit_type' => 'Department',
            'org_level' => 2,
            'is_terminal' => false,
            'org_path' => 'Executive Office > Clinical Wing',
        ]);
        OrgUnit::query()->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'parent_id' => $wing->id,
            'external_id' => 'MCC-OU-011',
            'name' => 'Recovery Bay Team',
            'org_unit_type' => 'Team',
            'org_level' => 3,
            'is_terminal' => true,
            'org_path' => 'Executive Office > Clinical Wing > Recovery Bay Team',
        ]);
        OrgUnit::query()->create([
            'business_id' => $other->id,
            'external_id' => 'OTH-OU-001',
            'name' => 'Other Root',
            'org_level' => 1,
            'is_terminal' => true,
        ]);

        $assignment = OrgAssignment::query()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'org_unit_id' => $team->id,
            'terminal_org_unit_id' => $team->id,
            'department_id' => $department->id,
            'branch_id' => $branch->id,
            'client_space_id' => $space->id,
            'external_id' => 'MCC-ASN-0003',
            'position_title' => 'Lead Nurse',
            'assignment_type' => 'Primary',
            'is_primary' => true,
            'hierarchy_level' => 2,
            'roster_eligible' => true,
            'client_space_label' => 'Main Clinic / Recovery Bay',
            'effective_from' => '2026-01-01',
        ]);
        $team->forceFill([
            'head_assignment_id' => $assignment->id,
            'head_assignment_external_id' => $assignment->external_id,
        ])->save();

        ReportingRelationship::query()->create([
            'business_id' => $business->id,
            'subject_assignment_id' => $assignment->id,
            'subject_user_id' => $user->id,
            'approval_terminal_assignment_id' => $assignment->id,
            'hierarchy_level' => 2,
            'approval_route' => 'Organizational ancestry',
            'approval_depth' => 1,
            'approval_path_external_ids' => ['MCC-ASN-0001'],
        ]);

        StaffDeployment::query()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'org_assignment_id' => $assignment->id,
            'org_unit_id' => $team->id,
            'branch_id' => $branch->id,
            'client_space_id' => $space->id,
            'external_id' => 'MCC-DEP-0001',
            'client_space_external_id' => 'MCC-CS-005',
            'position_title' => 'Lead Nurse',
            'allocation_percent' => 100,
            'effective_from' => '2026-09-01',
            'purpose' => 'Routine service delivery',
        ]);

        $headers = ['X-API-Key' => self::API_KEY];

        $terminals = $this->getJson('/api/org-units?business_id='.$business->id.'&is_terminal=yes', $headers)
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.external_id', 'MCC-OU-005')
            ->assertJsonPath('0.parent_external_id', 'MCC-OU-001')
            ->assertJsonPath('0.department.name', 'Nursing')
            ->assertJsonPath('0.head_user.uuid', $user->uuid)
            ->assertJsonPath('0.is_terminal', true)
            ->assertJsonPath('0.terminal_nodes.0.external_id', 'MCC-OU-005');
        $this->assertSame(
            ['MCC-OU-005', 'MCC-OU-011'],
            collect($terminals->json())->pluck('external_id')->all()
        );

        $rootPayload = $this->getJson('/api/org-units/MCC-OU-001', $headers)
            ->assertOk()
            ->assertJsonPath('external_id', 'MCC-OU-001')
            ->assertJsonPath('name', 'Executive Office')
            ->assertJsonPath('is_terminal', false);
        $this->assertSame(
            ['MCC-OU-005', 'MCC-OU-011'],
            collect($rootPayload->json('terminal_nodes'))->pluck('external_id')->all()
        );
        $this->assertSame('MCC-OU-010', $rootPayload->json('terminal_nodes.1.parent_external_id'));

        $this->getJson('/api/org-units/MCC-OU-010', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'terminal_nodes')
            ->assertJsonPath('terminal_nodes.0.external_id', 'MCC-OU-011')
            ->assertJsonPath('terminal_nodes.0.name', 'Recovery Bay Team')
            ->assertJsonPath('terminal_nodes.0.is_terminal', true);

        $this->getJson('/api/hr/assignments?business_id='.$business->id.'&user_uuid='.$user->uuid.'&is_primary=yes', $headers)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.external_id', 'MCC-ASN-0003')
            ->assertJsonPath('0.position_title', 'Lead Nurse')
            ->assertJsonPath('0.org_unit.external_id', 'MCC-OU-005')
            ->assertJsonPath('0.roster_eligible', true)
            ->assertJsonPath('0.client_space.name', 'Recovery Bay')
            ->assertJsonPath('0.effective_from', '2026-01-01');

        $this->getJson('/api/assignments/MCC-ASN-0003', $headers)
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->getJson('/api/reporting-relationships?business_id='.$business->id, $headers)
            ->assertOk()
            ->assertJsonPath('0.subject_assignment.external_id', 'MCC-ASN-0003')
            ->assertJsonPath('0.approval_path_external_ids.0', 'MCC-ASN-0001')
            ->assertJsonPath('0.approval_depth', 1);

        $this->getJson('/api/hr/deployments?client_space_external_id=MCC-CS-005', $headers)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.external_id', 'MCC-DEP-0001')
            ->assertJsonPath('0.allocation_percent', 100)
            ->assertJsonPath('0.terminal_node.external_id', 'MCC-OU-005')
            ->assertJsonPath('0.branch.name', 'Main Clinic');
    }
}
