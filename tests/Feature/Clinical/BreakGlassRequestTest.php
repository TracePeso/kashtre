<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\BreakGlassRequest;
use App\Models\ClinicalBreakGlassLog;
use App\Models\User;
use Database\Seeders\ClinicalMasterDictionariesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class BreakGlassRequestTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    protected function setUp(): void
    {
        parent::setUp();

        (new ClinicalMasterDictionariesSeeder())->run();
    }

    public function test_granting_break_glass_without_permission_is_blocked(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'break-glass-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'business_id' => 1,
            'permissions' => [],
        ]);

        Livewire::actingAs($user)
            ->test(BreakGlassRequest::class, ['clientId' => 'CLIENT-BG-1'])
            ->set('reasonCode', 'OVERRIDE_ON_CALL_COVER')
            ->call('grant')
            ->assertForbidden();
    }

    public function test_a_reason_requiring_free_text_cannot_be_granted_without_a_justification(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'break-glass-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'business_id' => 1,
            'permissions' => ['Trigger Break Glass Override'],
        ]);

        Livewire::actingAs($user)
            ->test(BreakGlassRequest::class, ['clientId' => 'CLIENT-BG-2'])
            ->set('reasonCode', 'OVERRIDE_EMERGENCY_RESUS') // requires_free_text = true (seeded)
            ->call('grant')
            ->assertHasErrors(['justificationNote']);

        $this->assertSame(0, ClinicalBreakGlassLog::where('client_id', 'CLIENT-BG-2')->count());
    }

    public function test_granting_with_a_justification_creates_the_log_and_redirects_to_the_chart(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'break-glass-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'business_id' => 1,
            'permissions' => ['Trigger Break Glass Override'],
        ]);

        Livewire::actingAs($user)
            ->test(BreakGlassRequest::class, ['clientId' => 'CLIENT-BG-3'])
            ->set('reasonCode', 'OVERRIDE_EMERGENCY_RESUS')
            ->set('justificationNote', 'Crash call, patient unresponsive.')
            ->call('grant')
            ->assertRedirect(route('clinical.observations.show', ['clientId' => 'CLIENT-BG-3']));

        $log = ClinicalBreakGlassLog::where('client_id', 'CLIENT-BG-3')->first();
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertTrue($log->granted_until->isFuture());
    }
}
