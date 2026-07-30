<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSecurityQuestion;
use App\Services\SecurityQuestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_configure_security_questions(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two factor authentication is not enabled.');
        }

        $user = User::factory()->create();

        $this->actingAs($user);
        $this->withSession(['auth.password_confirmed_at' => time()]);

        Livewire::test(\App\Livewire\Profile\SecurityQuestionsForm::class)
            ->set('questions', [
                ['question_key' => 'first_school', 'answer' => 'Green Valley'],
                ['question_key' => 'first_pet', 'answer' => 'Rex'],
                ['question_key' => 'birth_city', 'answer' => 'Kampala'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->security_questions_enabled_at);
        $this->assertCount(3, $user->securityQuestions);
        $this->assertTrue($user->hasSecurityQuestionsConfigured());
    }

    public function test_security_question_login_completes_two_factor_challenge(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two factor authentication is not enabled.');
        }

        $user = User::factory()->create([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        app(SecurityQuestionService::class)->storeForUser($user, [
            ['question_key' => 'first_school', 'answer' => 'Green Valley'],
            ['question_key' => 'first_pet', 'answer' => 'Rex'],
            ['question_key' => 'birth_city', 'answer' => 'Kampala'],
        ]);

        $challengeKeys = collect(app(SecurityQuestionService::class)->prepareLoginChallenge($user->fresh()))
            ->pluck('key')
            ->all();

        $answers = [];
        foreach ($challengeKeys as $key) {
            $answers[$key] = match ($key) {
                'first_school' => 'Green Valley',
                'first_pet' => 'Rex',
                'birth_city' => 'Kampala',
                default => 'unknown',
            };
        }

        $response = $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
            'login.security_question_keys' => $challengeKeys,
        ])->post(route('two-factor.security-questions'), [
            'security_answers' => $answers,
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_security_question_answers_are_rejected(): void
    {
        $user = User::factory()->create();

        app(SecurityQuestionService::class)->storeForUser($user, [
            ['question_key' => 'first_school', 'answer' => 'Green Valley'],
            ['question_key' => 'first_pet', 'answer' => 'Rex'],
            ['question_key' => 'birth_city', 'answer' => 'Kampala'],
        ]);

        $challengeKeys = ['first_school', 'first_pet'];

        $response = $this->withSession([
            'login.id' => $user->id,
            'login.security_question_keys' => $challengeKeys,
        ])->from(route('two-factor.login'))->post(route('two-factor.security-questions'), [
            'security_answers' => [
                'first_school' => 'Wrong',
                'first_pet' => 'Wrong',
            ],
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHasErrors('security_answers');
        $this->assertGuest();
    }

    public function test_user_can_disable_security_questions(): void
    {
        $user = User::factory()->create();

        app(SecurityQuestionService::class)->storeForUser($user, [
            ['question_key' => 'first_school', 'answer' => 'Green Valley'],
            ['question_key' => 'first_pet', 'answer' => 'Rex'],
            ['question_key' => 'birth_city', 'answer' => 'Kampala'],
        ]);

        $this->actingAs($user->fresh());
        $this->withSession(['auth.password_confirmed_at' => time()]);

        Livewire::test(\App\Livewire\Profile\SecurityQuestionsForm::class)
            ->call('disable')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertNull($user->security_questions_enabled_at);
        $this->assertSame(0, UserSecurityQuestion::query()->where('user_id', $user->id)->count());
    }
}
