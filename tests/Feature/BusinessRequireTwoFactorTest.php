<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\SecurityQuestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessRequireTwoFactorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_new_business_requires_two_factor_by_default(): void
    {
        $business = $this->makeHospital();

        $this->assertTrue($business->requiresTwoFactor());
        $this->assertTrue($business->require_2fa);
    }

    public function test_bulk_import_defaults_to_requiring_two_factor(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $import = new \App\Imports\BusinessTemplateImport();
        $created = $import->model([
            'name' => 'Bulk 2FA Default Biz',
            'email' => 'bulk-2fa-'.Str::random(8).'@example.com',
            'phone' => '256700000021',
            'address' => 'Kampala',
        ]);

        $this->assertInstanceOf(Business::class, $created);
        $this->assertTrue($created->requiresTwoFactor());
    }

    public function test_bulk_import_can_turn_two_factor_off(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $import = new \App\Imports\BusinessTemplateImport();
        $created = $import->model([
            'name' => 'Bulk 2FA Off Biz',
            'email' => 'bulk-2fa-off-'.Str::random(8).'@example.com',
            'phone' => '256700000022',
            'address' => 'Kampala',
            'require_2fa' => 'no',
        ]);

        $this->assertInstanceOf(Business::class, $created);
        $this->assertFalse($created->requiresTwoFactor());
    }

    public function test_users_are_not_forced_to_set_up_two_factor_when_business_setting_is_off(): void
    {
        $business = $this->makeHospital(['require_2fa' => false]);
        $user = $this->makeUser($business, 'no-force-2fa@example.com');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Setup 2FA');
    }

    public function test_users_are_forced_to_set_up_two_factor_when_business_setting_is_on(): void
    {
        $business = $this->makeHospital(['require_2fa' => true]);
        $user = $this->makeUser($business, 'force-2fa@example.com');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile.show'));
    }

    public function test_security_questions_satisfy_organisation_two_factor_without_an_authenticator(): void
    {
        $business = $this->makeHospital(['require_2fa' => true]);
        $user = $this->makeUser($business, 'questions-satisfy-2fa-'.Str::random(8).'@example.com');

        $this->configureSecurityQuestions($user, 'security_questions');

        $this->assertTrue($user->fresh()->hasSatisfiedRequiredTwoFactor());
        $this->assertFalse($user->fresh()->hasAuthenticatorConfigured());

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Setup 2FA');
    }

    public function test_questions_primary_login_does_not_then_ask_for_an_authenticator_code(): void
    {
        $business = $this->makeHospital(['require_2fa' => true]);
        $user = $this->makeUser($business, 'questions-primary-login-'.Str::random(8).'@example.com');
        $this->configureSecurityQuestions($user, 'security_questions');
        $user = $user->fresh();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertEquals($user->id, session('login.id'));

        $challenge = $this->get(route('two-factor.login'));
        $challenge->assertOk();
        $challenge->assertSee('Answer your security questions to finish signing in.', false);
        $challenge->assertSee(route('two-factor.security-questions'), false);
        $challenge->assertDontSee(__('Use an authentication code'));
        $challenge->assertDontSee('name="code"', false);

        $keys = session('login.security_question_keys');
        $this->assertIsArray($keys);
        $this->assertNotEmpty($keys);

        $answers = [];
        foreach ($keys as $key) {
            $answers[$key] = match ($key) {
                'first_school' => 'Green Valley',
                'first_pet' => 'Rex',
                'birth_city' => 'Kampala',
                default => 'unknown',
            };
        }

        $this->post(route('two-factor.security-questions'), [
            'security_answers' => $answers,
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Setup 2FA')
            ->assertDontSee('Please confirm access to your account by entering the authentication code');
    }

    public function test_parse_require_two_factor_flag(): void
    {
        $this->assertTrue(Business::parseRequireTwoFactorFlag(null));
        $this->assertTrue(Business::parseRequireTwoFactorFlag(''));
        $this->assertTrue(Business::parseRequireTwoFactorFlag('yes'));
        $this->assertFalse(Business::parseRequireTwoFactorFlag('no'));
        $this->assertFalse(Business::parseRequireTwoFactorFlag('0'));
        $this->assertTrue(Business::parseRequireTwoFactorFlag('1'));
    }

    private function makeHospital(array $overrides = []): Business
    {
        return Business::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => '2FA Policy Test Biz',
            'email' => '2fa-biz-'.uniqid().'@example.com',
            'phone' => '0700000000',
            'address' => 'Kampala',
            'account_number' => 'ACC'.strtoupper(uniqid()),
            'entity_code' => 'E'.strtoupper(substr(uniqid(), -5)),
            'currency_code' => 'UGX',
        ], $overrides));
    }

    private function makeUser(Business $business, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => [],
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    private function configureSecurityQuestions(User $user, string $primaryMethod = 'security_questions'): void
    {
        app(SecurityQuestionService::class)->storeForUser($user, [
            ['question_key' => 'first_school', 'answer' => 'Green Valley'],
            ['question_key' => 'first_pet', 'answer' => 'Rex'],
            ['question_key' => 'birth_city', 'answer' => 'Kampala'],
        ]);

        $user->forceFill([
            'primary_two_factor_method' => $primaryMethod,
        ])->save();
    }
}
