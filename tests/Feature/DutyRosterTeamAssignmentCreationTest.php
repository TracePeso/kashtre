<?php

namespace Tests\Feature;

use App\Livewire\DutyRosterManager;
use App\Models\HrDutyRoster;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DutyRosterTeamAssignmentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_assignments_can_be_set_during_roster_creation(): void
    {
        $user = User::factory()->create([
            'is_hr_admin' => true,
        ]);

        $organization = Organization::create([
            'name' => 'Roster Team Org',
            'external_business_uuid' => 'roster-team-org',
            'weekend_days' => [0, 6],
        ]);

        $clientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'name' => 'Delivery Ward',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $firstAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $clientSpace->id,
            'staff_uuid' => 'staff-alpha',
            'staff_name' => 'Alice Admin',
            'staff_title' => 'Nurse',
            'status' => 'active',
        ]);

        $secondAssignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $clientSpace->id,
            'staff_uuid' => 'staff-bravo',
            'staff_name' => 'Ben Builder',
            'staff_title' => 'Nurse',
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        Livewire::test(DutyRosterManager::class)
            ->set('selectedClientSpaceId', $clientSpace->id)
            ->call('openCreateModal')
            ->set('newRosterUsesTeams', true)
            ->assertSee('Assign Staff to Teams')
            ->assertSee('Alice Admin')
            ->assertSee('Ben Builder')
            ->set('newRosterTeamNames', ['Team A', 'Team B'])
            ->set("newRosterTeamAssignments.{$firstAssignment->id}", 'Team A')
            ->set("newRosterTeamAssignments.{$secondAssignment->id}", 'Team B')
            ->call('createRoster')
            ->assertHasNoErrors();

        $roster = HrDutyRoster::query()
            ->where('organization_id', $organization->id)
            ->where('organizational_unit_id', $clientSpace->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($roster);
        $this->assertSame(['Team A', 'Team B'], $roster->teamNames());
        $this->assertSame([
            (string) $firstAssignment->id => 'Team A',
            (string) $secondAssignment->id => 'Team B',
        ], $roster->teamAssignments());
    }
}
