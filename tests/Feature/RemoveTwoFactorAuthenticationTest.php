<?php

namespace Tests\Feature;

use App\Livewire\Admins;
use App\Livewire\ListUsers;
use App\Models\Business;
use App\Models\User;
use App\Services\SecurityQuestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class RemoveTwoFactorAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_kashtre_admin_can_remove_staff_two_factor_authentication(): void
    {
        [$kashtreAdmin, $staff] = $this->kashtreAdminAndStaffWithTwoFactor();

        $this->actingAs($kashtreAdmin);

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('remove_2fa', $staff)
            ->callTableAction('remove_2fa', $staff);

        $staff->refresh();

        $this->assertFalse($staff->hasTwoFactorEnabled());
        $this->assertNull($staff->two_factor_secret);
        $this->assertNull($staff->two_factor_recovery_codes);
        $this->assertNull($staff->two_factor_confirmed_at);
        $this->assertNull($staff->security_questions_enabled_at);
        $this->assertSame('authenticator', $staff->primary_two_factor_method);
        $this->assertCount(0, $staff->securityQuestions);
    }

    public function test_kashtre_admin_can_remove_admin_two_factor_authentication(): void
    {
        $kashtre = $this->kashtreBusiness();
        $actor = $this->makeUser($kashtre, 'kashtre.actor.remove2fa@example.com');
        $otherAdmin = $this->makeUserWithTwoFactor($kashtre, 'kashtre.admin.remove2fa@example.com');

        $this->actingAs($actor);

        Livewire::test(Admins::class)
            ->assertTableActionVisible('remove_2fa', $otherAdmin)
            ->callTableAction('remove_2fa', $otherAdmin);

        $otherAdmin->refresh();

        $this->assertFalse($otherAdmin->hasTwoFactorEnabled());
        $this->assertNull($otherAdmin->two_factor_secret);
        $this->assertNull($otherAdmin->two_factor_confirmed_at);
    }

    public function test_remove_2fa_action_is_hidden_when_two_factor_is_not_enabled(): void
    {
        [$kashtreAdmin, $staff] = $this->kashtreAdminAndStaffWithTwoFactor();
        $staff->removeTwoFactorAuthentication();

        $this->actingAs($kashtreAdmin);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('remove_2fa', $staff->fresh());
    }

    public function test_business_user_cannot_remove_kashtre_admin_two_factor(): void
    {
        $hospital = $this->hospitalBusiness();
        $hospitalUser = $this->makeUser($hospital, 'hospital.admin.remove2fa@example.com');
        $kashtreAdmin = $this->makeUserWithTwoFactor($this->kashtreBusiness(), 'kashtre.hidden.remove2fa@example.com');

        $this->actingAs($hospitalUser);

        Livewire::test(Admins::class)
            ->assertTableActionHidden('remove_2fa', $kashtreAdmin);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function kashtreAdminAndStaffWithTwoFactor(): array
    {
        return [
            $this->makeUser($this->kashtreBusiness(), 'kashtre.admin.remove2fa@example.com'),
            $this->makeUserWithTwoFactor($this->hospitalBusiness(), 'hospital.staff.remove2fa@example.com'),
        ];
    }

    private function kashtreBusiness(): Business
    {
        $business = Business::query()->find(1);

        if (! $business) {
            $this->markTestSkipped('Kashtre business (id 1) is required.');
        }

        return $business;
    }

    private function hospitalBusiness(): Business
    {
        $business = Business::query()->where('id', '!=', 1)->first();

        if (! $business) {
            $this->markTestSkipped('A non-Kashtre business is required.');
        }

        return $business;
    }

    private function makeUser(Business $business, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => [],
        ]);
    }

    private function makeUserWithTwoFactor(Business $business, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => [],
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['abcd-efgh'])),
            'two_factor_confirmed_at' => now(),
            'primary_two_factor_method' => 'security_questions',
        ]);

        app(SecurityQuestionService::class)->storeForUser($user, [
            ['question_key' => 'first_school', 'answer' => 'Green Valley'],
            ['question_key' => 'first_pet', 'answer' => 'Rex'],
            ['question_key' => 'birth_city', 'answer' => 'Kampala'],
        ]);

        return $user->fresh();
    }
}
