<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\WardCensusBoard;
use App\Models\ClinicalBed;
use App\Models\ClinicalWard;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class WardCensusBoardTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Wrap both the app's default connection (for the test User) and
     * 'clinical' (for wards/beds) in rolled-back transactions — neither
     * should leave rows behind in the real dev database.
     */
    protected $connectionsToTransact = [null, 'clinical'];

    /**
     * Not User::factory()->create() — the factory sets current_team_id
     * (Jetstream teams), which this app's real users table doesn't have a
     * column for (teams aren't in use here). User::create() only inserts
     * $fillable attributes, so it avoids that pre-existing mismatch.
     */
    private function userWithPermissions(array $permissions): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'clinical-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'branch_id' => 1,
            'permissions' => $permissions,
        ]);
    }

    public function test_a_user_without_view_permission_is_blocked(): void
    {
        $user = $this->userWithPermissions([]);

        Livewire::actingAs($user)
            ->test(WardCensusBoard::class)
            ->assertForbidden();
    }

    public function test_it_renders_wards_beds_and_census_counts(): void
    {
        $user = $this->userWithPermissions(['View Ward Census']);

        $ward = ClinicalWard::create([
            'business_id' => 1,
            'ward_code' => 'GYN-01',
            'ward_name' => 'Gynaecology Ward',
        ]);

        ClinicalBed::create(['ward_id' => $ward->id, 'bed_code' => 'BED-01']);
        ClinicalBed::create(['ward_id' => $ward->id, 'bed_code' => 'BED-02', 'operational_state' => ClinicalBed::STATE_OCCUPIED, 'current_client_id' => 'CLIENT-001']);

        Livewire::actingAs($user)
            ->test(WardCensusBoard::class)
            ->assertSee('Gynaecology Ward')
            ->assertSee('BED-01')
            ->assertSee('CLIENT-001')
            ->assertViewHas('census', fn ($census) => $census['total'] === 2 && $census['occupied'] === 1 && $census['available'] === 1);
    }

    public function test_it_can_add_an_overflow_bed_only_with_permission(): void
    {
        $ward = ClinicalWard::create(['business_id' => 1, 'ward_code' => 'ICU-01', 'ward_name' => 'ICU']);

        $withoutPermission = $this->userWithPermissions(['View Ward Census']);

        Livewire::actingAs($withoutPermission)
            ->test(WardCensusBoard::class)
            ->call('addOverflowBed', $ward->id)
            ->assertForbidden();

        $withPermission = $this->userWithPermissions(['View Ward Census', 'Add Overflow Beds']);

        Livewire::actingAs($withPermission)
            ->test(WardCensusBoard::class)
            ->call('addOverflowBed', $ward->id);

        $bed = $ward->beds()->first();
        $this->assertTrue($bed->is_overflow);
        $this->assertSame('BED-1-EXTRA', $bed->bed_code);
    }

    public function test_it_can_occupy_and_release_a_bed(): void
    {
        $user = $this->userWithPermissions(['View Ward Census', 'Manage Ward Census']);
        $ward = ClinicalWard::create(['business_id' => 1, 'ward_code' => 'GYN-02', 'ward_name' => 'Gynaecology Ward 2']);
        $bed = ClinicalBed::create(['ward_id' => $ward->id, 'bed_code' => 'BED-01']);

        Livewire::actingAs($user)
            ->test(WardCensusBoard::class)
            ->call('startOccupy', $bed->id)
            ->set('occupyClientId', 'CLIENT-777')
            ->call('confirmOccupy');

        $bed->refresh();
        $this->assertSame(ClinicalBed::STATE_OCCUPIED, $bed->operational_state);
        $this->assertSame('CLIENT-777', $bed->current_client_id);

        Livewire::actingAs($user)
            ->test(WardCensusBoard::class)
            ->call('releaseBed', $bed->id);

        $this->assertSame(ClinicalBed::STATE_AVAILABLE, $bed->fresh()->operational_state);
    }

    public function test_releasing_an_overflow_bed_deletes_it(): void
    {
        $user = $this->userWithPermissions(['View Ward Census', 'Manage Ward Census']);
        $ward = ClinicalWard::create(['business_id' => 1, 'ward_code' => 'GYN-03', 'ward_name' => 'Gynaecology Ward 3']);
        $bed = ClinicalBed::create(['ward_id' => $ward->id, 'bed_code' => 'BED-1-EXTRA', 'is_overflow' => true, 'operational_state' => ClinicalBed::STATE_OCCUPIED, 'current_client_id' => 'CLIENT-1']);

        Livewire::actingAs($user)
            ->test(WardCensusBoard::class)
            ->call('releaseBed', $bed->id);

        $this->assertNull(ClinicalBed::find($bed->id));
    }
}
