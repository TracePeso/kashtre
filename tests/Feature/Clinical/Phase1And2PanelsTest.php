<?php

namespace Tests\Feature\Clinical;

use App\Contracts\Clinical\ClientSpaceAssignmentGateway;
use App\Contracts\Clinical\DelegationGateway;
use App\Contracts\Clinical\EncounterGateway;
use App\Contracts\Clinical\IdentityConcernGateway;
use App\Contracts\Clinical\IdentityConfirmationGateway;
use App\Contracts\Clinical\PatientWorkspaceGateway;
use App\Contracts\Clinical\PermissionCatalogGateway;
use App\Contracts\Clinical\PrivilegeGateway;
use App\Contracts\Clinical\SensitivityRestrictionGateway;
use App\Livewire\Clinical\ClientSpaceAssignmentsPanel;
use App\Livewire\Clinical\DelegationsPanel;
use App\Livewire\Clinical\EncounterWorkspacePanel;
use App\Livewire\Clinical\IdentityConcernsPanel;
use App\Livewire\Clinical\IdentityConfirmationsPanel;
use App\Livewire\Clinical\PatientWorkspacePanel;
use App\Livewire\Clinical\PermissionCatalogPanel;
use App\Livewire\Clinical\PrivilegesPanel;
use App\Livewire\Clinical\SensitivityRestrictionsPanel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * SRD v6.1 Phase 1 & 2 governance/patient-workspace panels — mount-permission
 * gates and one real happy-path mutation per panel, against a mocked gateway
 * (these are pure API-gateway pass-throughs with no local table, same
 * reasoning as every other raw-array gateway in this codebase).
 */
class Phase1And2PanelsTest extends TestCase
{
    use DatabaseTransactions;

    private function userWithPermissions(array $permissions): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'phase12-panel-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'permissions' => $permissions,
        ]);
    }

    // ---- Client-Space Assignments ------------------------------------

    public function test_client_space_assignments_panel_is_blocked_without_manage_ward_census(): void
    {
        Livewire::actingAs($this->userWithPermissions([]))
            ->test(ClientSpaceAssignmentsPanel::class)
            ->assertForbidden();
    }

    public function test_client_space_assignments_panel_assigns_via_the_gateway(): void
    {
        $gateway = Mockery::mock(ClientSpaceAssignmentGateway::class);
        $gateway->shouldReceive('assign')->once()->andReturn(['id' => 'CSA-1']);
        $this->app->instance(ClientSpaceAssignmentGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['Manage Ward Census']))
            ->test(ClientSpaceAssignmentsPanel::class)
            ->set('userId', '42')
            ->set('clientSpaceId', '7')
            ->set('effectiveStart', '2026-01-01T08:00')
            ->call('assign')
            ->assertSee('Assignment recorded.');
    }

    // ---- Privileges ----------------------------------------------------

    public function test_privileges_panel_is_blocked_without_manage_ward_census(): void
    {
        Livewire::actingAs($this->userWithPermissions([]))
            ->test(PrivilegesPanel::class)
            ->assertForbidden();
    }

    public function test_privileges_panel_grants_via_the_gateway(): void
    {
        $gateway = Mockery::mock(PrivilegeGateway::class);
        $gateway->shouldReceive('grant')->once()->andReturn(['id' => 'PRIV-1']);
        $this->app->instance(PrivilegeGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['Manage Ward Census']))
            ->test(PrivilegesPanel::class)
            ->set('userId', '42')
            ->set('effectiveStart', '2026-01-01T08:00')
            ->call('grant')
            ->assertSee('Privilege granted.');
    }

    // ---- Delegations -----------------------------------------------------

    public function test_delegations_panel_is_blocked_without_manage_ward_census(): void
    {
        Livewire::actingAs($this->userWithPermissions([]))
            ->test(DelegationsPanel::class)
            ->assertForbidden();
    }

    public function test_delegations_panel_rejects_an_end_before_start_client_side(): void
    {
        Livewire::actingAs($this->userWithPermissions(['Manage Ward Census']))
            ->test(DelegationsPanel::class)
            ->set('delegatorUserId', '1')
            ->set('delegateUserId', '2')
            ->set('permissionBundle', 'MEDICAL_OFFICER_GENERAL')
            ->set('start', '2026-01-10T08:00')
            ->set('end', '2026-01-01T08:00')
            ->set('reason', 'cross-cover')
            ->call('delegate')
            ->assertSee('End must be after start.');
    }

    public function test_delegations_panel_delegates_via_the_gateway(): void
    {
        $gateway = Mockery::mock(DelegationGateway::class);
        $gateway->shouldReceive('delegate')->once()->andReturn(['id' => 'DEL-1']);
        $this->app->instance(DelegationGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['Manage Ward Census']))
            ->test(DelegationsPanel::class)
            ->set('delegatorUserId', '1')
            ->set('delegateUserId', '2')
            ->set('permissionBundle', 'MEDICAL_OFFICER_GENERAL')
            ->set('start', '2026-01-01T08:00')
            ->set('end', '2026-01-10T08:00')
            ->set('reason', 'cross-cover')
            ->call('delegate')
            ->assertSee('Delegation recorded.');
    }

    // ---- Permission Catalogue --------------------------------------------

    public function test_permission_catalog_panel_is_blocked_without_manage_clinical_module(): void
    {
        $gateway = Mockery::mock(PermissionCatalogGateway::class);
        $gateway->shouldReceive('list')->andReturn(['data' => [], 'meta' => []]);
        $this->app->instance(PermissionCatalogGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions([]))
            ->test(PermissionCatalogPanel::class)
            ->assertForbidden();
    }

    public function test_permission_catalog_panel_registers_a_new_code_via_the_gateway(): void
    {
        $gateway = Mockery::mock(PermissionCatalogGateway::class);
        $gateway->shouldReceive('list')->andReturn(['data' => [], 'meta' => ['count' => 163]]);
        $gateway->shouldReceive('register')->once()->andReturn(['id' => 'PC-1']);
        $this->app->instance(PermissionCatalogGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['Manage Clinical Module']))
            ->test(PermissionCatalogPanel::class)
            ->set('newCode', 'clinical.example.approve')
            ->set('newDescription', 'Approve example')
            ->call('register')
            ->assertSee('Registered');
    }

    // ---- Sensitivity Restrictions -----------------------------------------

    public function test_sensitivity_restrictions_panel_is_blocked_without_view_permission(): void
    {
        $gateway = Mockery::mock(SensitivityRestrictionGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $this->app->instance(SensitivityRestrictionGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions([]))
            ->test(SensitivityRestrictionsPanel::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_sensitivity_restrictions_panel_restricts_via_the_gateway(): void
    {
        $gateway = Mockery::mock(SensitivityRestrictionGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $gateway->shouldReceive('restrict')->once()->andReturn(['id' => 'SR-1']);
        $this->app->instance(SensitivityRestrictionGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['View Clinical Observations', 'Manage Ward Census']))
            ->test(SensitivityRestrictionsPanel::class, ['clientId' => 'CLIENT-001'])
            ->set('label', 'VIP')
            ->set('reason', 'staff-member privacy')
            ->call('restrict')
            ->assertSee('Restriction applied.');
    }

    // ---- Identity Confirmations --------------------------------------------

    public function test_identity_confirmations_panel_is_blocked_without_view_permission(): void
    {
        $gateway = Mockery::mock(IdentityConfirmationGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $this->app->instance(IdentityConfirmationGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions([]))
            ->test(IdentityConfirmationsPanel::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_identity_confirmations_panel_confirms_via_the_gateway(): void
    {
        $gateway = Mockery::mock(IdentityConfirmationGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $gateway->shouldReceive('confirm')->once()->andReturn(['id' => 'IC-1']);
        $this->app->instance(IdentityConfirmationGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['View Clinical Observations', 'Add Clinical Observations']))
            ->test(IdentityConfirmationsPanel::class, ['clientId' => 'CLIENT-001'])
            ->call('confirm')
            ->assertSee('Identity confirmed and recorded.');
    }

    // ---- Identity Concerns ---------------------------------------------

    public function test_identity_concerns_panel_is_blocked_without_view_permission(): void
    {
        $gateway = Mockery::mock(IdentityConcernGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $this->app->instance(IdentityConcernGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions([]))
            ->test(IdentityConcernsPanel::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_identity_concerns_panel_reports_via_the_gateway(): void
    {
        $gateway = Mockery::mock(IdentityConcernGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $gateway->shouldReceive('report')->once()->andReturn(['id' => 'CONC-1']);
        $this->app->instance(IdentityConcernGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['View Clinical Observations', 'Add Clinical Observations']))
            ->test(IdentityConcernsPanel::class, ['clientId' => 'CLIENT-001'])
            ->set('description', 'Two patients with near-identical names on the same ward.')
            ->call('report')
            ->assertSee('Concern reported');
    }

    public function test_identity_concerns_panel_resolve_requires_manage_ward_census(): void
    {
        $gateway = Mockery::mock(IdentityConcernGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $this->app->instance(IdentityConcernGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['View Clinical Observations']))
            ->test(IdentityConcernsPanel::class, ['clientId' => 'CLIENT-001'])
            ->call('resolve', 'CONC-1')
            ->assertForbidden();
    }

    // ---- Encounter Workspace -----------------------------------------------

    public function test_encounter_workspace_panel_is_blocked_without_view_permission(): void
    {
        $gateway = Mockery::mock(EncounterGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $this->app->instance(EncounterGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions([]))
            ->test(EncounterWorkspacePanel::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_encounter_workspace_panel_creates_an_encounter_via_the_gateway(): void
    {
        $gateway = Mockery::mock(EncounterGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $gateway->shouldReceive('create')->once()->andReturn(['id' => 'ENC-1', 'minimum_data_pending' => false]);
        $this->app->instance(EncounterGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['View Clinical Observations', 'Add Clinical Observations']))
            ->test(EncounterWorkspacePanel::class, ['clientId' => 'CLIENT-001', 'visitId' => 'VISIT-1'])
            ->call('create')
            ->assertSee('Encounter created.');
    }

    public function test_encounter_workspace_panel_surfaces_a_refused_illegal_transition(): void
    {
        $gateway = Mockery::mock(EncounterGateway::class);
        $gateway->shouldReceive('forPatient')->andReturn([]);
        $gateway->shouldReceive('show')->andReturn(['id' => 'ENC-1', 'status' => 'PLANNED']);
        $gateway->shouldReceive('transition')->once()->andThrow(
            new \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException('ILLEGAL_ENCOUNTER_TRANSITION: cannot move from PLANNED to CLOSED directly.')
        );
        $this->app->instance(EncounterGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['View Clinical Observations', 'Add Clinical Observations']))
            ->test(EncounterWorkspacePanel::class, ['clientId' => 'CLIENT-001', 'visitId' => 'VISIT-1'])
            ->call('selectEncounter', 'ENC-1')
            ->set('newStatus', 'CLOSED')
            ->call('transition')
            ->assertSee('ILLEGAL_ENCOUNTER_TRANSITION');
    }

    // ---- Patient Workspace -----------------------------------------------

    public function test_patient_workspace_panel_is_blocked_without_view_permission(): void
    {
        $gateway = Mockery::mock(PatientWorkspaceGateway::class);
        $gateway->shouldReceive('banner')->andReturn([]);
        $gateway->shouldReceive('timeline')->andReturn([]);
        $this->app->instance(PatientWorkspaceGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions([]))
            ->test(PatientWorkspacePanel::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_patient_workspace_panel_renders_the_banner_and_timeline(): void
    {
        $gateway = Mockery::mock(PatientWorkspaceGateway::class);
        $gateway->shouldReceive('banner')->once()->andReturn([
            'encounter' => ['encounter_class' => 'INPATIENT', 'status' => 'IN_PROGRESS'],
            'location' => ['ward_name' => 'Gynaecology Ward'],
            'confidentiality' => ['restricted' => false],
        ]);
        $gateway->shouldReceive('timeline')->once()->andReturn([
            ['type' => 'OBSERVATION', 'summary' => 'BP 120/80', 'occurred_at' => '2026-01-01T08:00:00Z'],
        ]);
        $this->app->instance(PatientWorkspaceGateway::class, $gateway);

        Livewire::actingAs($this->userWithPermissions(['View Clinical Observations']))
            ->test(PatientWorkspacePanel::class, ['clientId' => 'CLIENT-001'])
            ->assertSee('Gynaecology Ward')
            ->assertSee('BP 120/80');
    }
}
