<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\BedsideScratchpad;
use App\Models\CdeObservation;
use App\Models\User;
use Database\Seeders\ClinicalMasterDictionariesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BedsideScratchpadTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    protected function setUp(): void
    {
        parent::setUp();

        (new ClinicalMasterDictionariesSeeder())->run();
        config(['services.ai_gateway.url' => null]); // unconfigured by default, like production
    }

    private function userWithPermissions(array $permissions): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'scratchpad-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'permissions' => $permissions,
        ]);
    }

    public function test_a_user_without_permission_is_blocked(): void
    {
        $user = $this->userWithPermissions([]);

        Livewire::actingAs($user)
            ->test(BedsideScratchpad::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_the_manual_fallback_works_with_no_ai_gateway_configured(): void
    {
        $user = $this->userWithPermissions(['Add Clinical Observations']);

        Livewire::actingAs($user)
            ->test(BedsideScratchpad::class, ['clientId' => 'CLIENT-SP-1'])
            ->assertSet('aiAvailable', false)
            ->assertDontSee('Extract Observations with AI')
            ->set('scratchpadText', 'Patient resting comfortably, no acute distress.')
            ->call('saveAsNote')
            ->assertSee('Note saved.');

        $note = CdeObservation::where('client_id', 'CLIENT-SP-1')->where('cde_code', 'BEDSIDE_NOTE')->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('resting comfortably', $note->captured_value_text);
    }

    public function test_ai_extraction_degrades_gracefully_when_the_gateway_fails(): void
    {
        config(['services.ai_gateway.url' => 'https://ai-gateway.kashtre.test']);
        Http::fake(['ai-gateway.kashtre.test/*' => Http::response([], 500)]);

        $user = $this->userWithPermissions(['Add Clinical Observations']);

        Livewire::actingAs($user)
            ->test(BedsideScratchpad::class, ['clientId' => 'CLIENT-SP-2'])
            ->set('scratchpadText', 'glucose was 7.2')
            ->call('extractWithAi')
            ->assertSee('AI Gateway')
            ->assertSet('proposedObservations', []);
    }

    public function test_a_successful_extraction_requires_explicit_commit_before_landing_as_an_observation(): void
    {
        config(['services.ai_gateway.url' => 'https://ai-gateway.kashtre.test']);
        Http::fake([
            'ai-gateway.kashtre.test/*' => Http::response([
                'observations' => [
                    ['cde_code' => 'GLUCOSE_RANDOM', 'dataElement' => 'Glucose', 'value' => 7.0, 'unit' => 'mmol/L'],
                ],
                'requiresValidation' => true,
            ], 200),
        ]);

        $user = $this->userWithPermissions(['Add Clinical Observations']);

        $component = Livewire::actingAs($user)
            ->test(BedsideScratchpad::class, ['clientId' => 'CLIENT-SP-3'])
            ->set('scratchpadText', 'glucose was 7.0')
            ->call('extractWithAi')
            ->assertSee('AI-Proposed Observations');

        // Not committed yet — still just proposed.
        $this->assertSame(0, CdeObservation::where('client_id', 'CLIENT-SP-3')->where('cde_code', 'GLUCOSE_RANDOM')->count());

        $component->call('commitObservation', 0)->assertSee('Committed GLUCOSE_RANDOM');

        $observation = CdeObservation::where('client_id', 'CLIENT-SP-3')->where('cde_code', 'GLUCOSE_RANDOM')->first();
        $this->assertNotNull($observation);
        $this->assertSame(CdeObservation::METHOD_VOICE_DICTATION, $observation->capture_method);
        $this->assertEqualsWithDelta(7.0, (float) $observation->base_value_numeric, 0.01);
    }

    public function test_a_proposed_observation_can_be_discarded_without_committing(): void
    {
        config(['services.ai_gateway.url' => 'https://ai-gateway.kashtre.test']);
        Http::fake([
            'ai-gateway.kashtre.test/*' => Http::response([
                'observations' => [
                    ['cde_code' => 'GLUCOSE_RANDOM', 'dataElement' => 'Glucose', 'value' => 7.0, 'unit' => 'mmol/L'],
                ],
            ], 200),
        ]);

        $user = $this->userWithPermissions(['Add Clinical Observations']);

        Livewire::actingAs($user)
            ->test(BedsideScratchpad::class, ['clientId' => 'CLIENT-SP-4'])
            ->set('scratchpadText', 'glucose was 7.0')
            ->call('extractWithAi')
            ->call('rejectObservation', 0)
            ->assertSet('proposedObservations', []);

        $this->assertSame(0, CdeObservation::where('client_id', 'CLIENT-SP-4')->count());
    }
}
