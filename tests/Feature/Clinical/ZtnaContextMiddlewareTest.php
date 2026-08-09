<?php

namespace Tests\Feature\Clinical;

use App\Http\Middleware\RequireTwoFactorForKashtre;
use App\Models\ClinicalCareAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ZtnaContextMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    protected function setUp(): void
    {
        parent::setUp();

        // Orthogonal to what this test verifies (ZTNA context, not
        // account security posture) — isolate it the same way the app
        // itself already does for other guarded route groups
        // (third-party-payer*, cashier*) that don't need 2FA enforcement.
        $this->withoutMiddleware(RequireTwoFactorForKashtre::class);
    }

    private const FULL_CHART_PERMISSIONS = [
        'View Clinical Observations', 'Add Clinical Observations',
        'View Clinical Diagnoses', 'Add Clinical Diagnoses',
        'View Clinical Process Registry', 'Progress Clinical Process Registry',
        'View Medication Orders', 'Prescribe Medication Orders',
        'View Clinical Work Orders', 'Add Clinical Work Orders',
        'View Clinical Audit Trail', 'Trigger Break Glass Override',
    ];

    private function user(array $permissions): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'ztna-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'branch_id' => 1,
            'permissions' => $permissions,
        ]);
    }

    public function test_a_user_without_a_care_relationship_sees_the_break_glass_screen(): void
    {
        $user = $this->user(self::FULL_CHART_PERMISSIONS);

        $response = $this->actingAs($user)->get('/clinical/patients/CLIENT-ZTNA-MW-1/observations');

        $response->assertStatus(403);
        $response->assertSee('Break Glass');
    }

    public function test_a_user_with_an_active_care_assignment_can_view_the_chart(): void
    {
        $user = $this->user(self::FULL_CHART_PERMISSIONS);

        ClinicalCareAssignment::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-ZTNA-MW-2',
            'assignment_model' => ClinicalCareAssignment::MODEL_INDIVIDUAL,
            'primary_nurse_user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/clinical/patients/CLIENT-ZTNA-MW-2/observations');

        $response->assertOk();
    }

    public function test_watermark_headers_are_present_only_when_off_premises(): void
    {
        $user = $this->user(self::FULL_CHART_PERMISSIONS);

        ClinicalCareAssignment::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-ZTNA-MW-3',
            'assignment_model' => ClinicalCareAssignment::MODEL_INDIVIDUAL,
            'primary_nurse_user_id' => $user->id,
            'is_active' => true,
        ]);

        $onPremises = $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/clinical/patients/CLIENT-ZTNA-MW-3/observations');

        $onPremises->assertOk();
        $onPremises->assertHeaderMissing('X-KashTre-Watermark-User');

        $offPremises = $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->get('/clinical/patients/CLIENT-ZTNA-MW-3/observations');

        $offPremises->assertOk();
        $offPremises->assertHeader('X-KashTre-Watermark-User');
        $offPremises->assertHeader('X-KashTre-Watermark-IP', '8.8.8.8');
        $offPremises->assertSee('watermarked');
    }
}
