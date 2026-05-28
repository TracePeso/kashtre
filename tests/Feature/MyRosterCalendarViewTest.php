<?php

namespace Tests\Feature;

use App\Models\HrDutyRoster;
use App\Models\HrDutyRosterEntry;
use App\Models\HrOrganizationalUnit;
use App\Models\Organization;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyRosterCalendarViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_roster_page_shows_diagonal_roster_boxes_with_shift_and_client_space_keys(): void
    {
        $user = User::factory()->create();

        $organization = Organization::create([
            'name' => 'Roster Org',
            'external_business_uuid' => 'roster-org',
            'weekend_days' => [0, 6],
        ]);

        $clientSpace = HrOrganizationalUnit::create([
            'organization_id' => $organization->id,
            'name' => 'Ward Alpha',
            'type' => 'Client Space',
            'unit_kind' => HrOrganizationalUnit::KIND_CLIENT_SPACE,
        ]);

        $shiftType = ShiftType::create([
            'organization_id' => $organization->id,
            'name' => 'Morning Shift',
            'code' => 'AM',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'color' => '#2563EB',
            'is_active' => true,
            'is_rosterable' => true,
        ]);

        $roster = HrDutyRoster::create([
            'organization_id' => $organization->id,
            'organizational_unit_id' => $clientSpace->id,
            'name' => 'June Ward Alpha',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => HrDutyRoster::STATUS_DRAFT,
        ]);

        HrDutyRosterEntry::create([
            'organization_id' => $organization->id,
            'duty_roster_id' => $roster->id,
            'roster_date' => '2026-06-10',
            'staff_uuid' => $user->staff_uuid,
            'staff_name' => $user->name,
            'shift_type_id' => $shiftType->id,
        ]);

        $this->actingAs($user);
        $this->withSession(['current_organization_id' => $organization->id]);

        $response = $this->get(route('hr.my-roster.index', ['month' => '2026-06']));

        $response->assertOk();
        $response->assertSee('Diagonal Key');
        $response->assertSee('Client Space Key');
        $response->assertSee('Shift Key');
        $response->assertSee('Shift / Space');
        $response->assertSee('Ward Alpha');
        $response->assertSee('Morning Shift');
        $response->assertSee('10');
    }
}
