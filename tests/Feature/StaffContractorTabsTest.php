<?php

namespace Tests\Feature;

use App\Livewire\ListUsers;
use App\Models\Business;
use App\Models\ContractorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class StaffContractorTabsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_staff_tab_lists_normal_users_and_hides_contractors(): void
    {
        [$actor, $staff, $contractor] = $this->hospitalStaffAndContractor();

        $this->actingAs($actor);

        Livewire::test(ListUsers::class)
            ->assertSet('activeTab', 'staff')
            ->assertSee('Staff')
            ->assertSee('Contractors')
            ->assertSee($staff->name)
            ->assertDontSee($contractor->name);
    }

    public function test_contractors_tab_lists_contractors_and_hides_staff(): void
    {
        [$actor, $staff, $contractor] = $this->hospitalStaffAndContractor();

        $this->actingAs($actor);

        Livewire::test(ListUsers::class)
            ->call('setActiveTab', 'contractors')
            ->assertSet('activeTab', 'contractors')
            ->assertSee($contractor->name)
            ->assertDontSee($staff->name);
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function hospitalStaffAndContractor(): array
    {
        $business = Business::query()->where('id', '!=', 1)->first();
        if (! $business) {
            $this->markTestSkipped('A non-Kashtre business is required.');
        }

        $suffix = uniqid();
        $actor = $this->makeUser($business, 'tabs.actor.'.$suffix.'@example.test', 'employee');
        $staff = $this->makeUser($business, 'tabs.staff.'.$suffix.'@example.test', 'employee', 'Tab Staff '.$suffix);
        $contractor = $this->makeUser($business, 'tabs.contractor.'.$suffix.'@example.test', 'contractor', 'Tab Contractor '.$suffix);

        ContractorProfile::query()->create([
            'user_id' => $contractor->id,
            'business_id' => $business->id,
            'bank_name' => 'Demo Bank',
            'account_name' => $contractor->name,
            'account_number' => 'TAB-'.$suffix,
        ]);

        return [$actor, $staff, $contractor];
    }

    private function makeUser(Business $business, string $email, string $employmentType, ?string $name = null): User
    {
        return User::factory()->create([
            'name' => $name ?: $email,
            'email' => $email,
            'business_id' => $business->id,
            'status' => 'active',
            'employment_type' => $employmentType,
            'permissions' => $employmentType === 'contractor' ? ['Contractor'] : [],
        ]);
    }
}
