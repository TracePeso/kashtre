<?php

namespace Tests\Feature\Clinical;

use App\Models\ClinicalBreakGlassLog;
use App\Models\ClinicalCareAssignment;
use App\Services\Clinical\ZtnaAccessGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ZtnaAccessGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    private function requestFromIp(string $ip): Request
    {
        return Request::create('/clinical/patients/CLIENT-001/observations', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
    }

    public function test_localhost_is_treated_as_on_premises_by_default(): void
    {
        $this->assertTrue(app(ZtnaAccessGuard::class)->isOnPremises($this->requestFromIp('127.0.0.1')));
    }

    public function test_a_public_ip_is_treated_as_off_premises(): void
    {
        $this->assertFalse(app(ZtnaAccessGuard::class)->isOnPremises($this->requestFromIp('8.8.8.8')));
    }

    public function test_an_mtls_verified_header_counts_as_on_premises_regardless_of_ip(): void
    {
        $request = Request::create('/clinical/patients/CLIENT-001/observations', 'GET', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
            'HTTP_X_KASHTRE_MTLS_VERIFIED' => 'true',
        ]);

        $this->assertTrue(app(ZtnaAccessGuard::class)->isOnPremises($request));
    }

    public function test_mutation_is_blocked_off_premises(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('ZTNA_OFF_PREMISE_RESTRICTION');

        app(ZtnaAccessGuard::class)->assertMutationAllowedOnPremises($this->requestFromIp('8.8.8.8'));
    }

    public function test_mutation_is_allowed_on_premises(): void
    {
        app(ZtnaAccessGuard::class)->assertMutationAllowedOnPremises($this->requestFromIp('127.0.0.1'));
        $this->assertTrue(true); // no exception thrown
    }

    public function test_access_is_granted_via_an_active_care_assignment(): void
    {
        ClinicalCareAssignment::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-ZTNA-1',
            'assignment_model' => ClinicalCareAssignment::MODEL_INDIVIDUAL,
            'primary_nurse_user_id' => 42,
            'is_active' => true,
        ]);

        $this->assertTrue(app(ZtnaAccessGuard::class)->hasAccess(42, 'CLIENT-ZTNA-1', 1));
    }

    public function test_access_is_denied_without_a_relationship_or_break_glass_grant(): void
    {
        $this->assertFalse(app(ZtnaAccessGuard::class)->hasAccess(99, 'CLIENT-ZTNA-2', 1));
    }

    public function test_granting_break_glass_records_and_permits_access(): void
    {
        $guard = app(ZtnaAccessGuard::class);

        $this->assertFalse($guard->hasAccess(55, 'CLIENT-ZTNA-3', 1));

        $log = $guard->grantBreakGlass(55, 1, 'CLIENT-ZTNA-3', 'VISIT-1', 'OVERRIDE_EMERGENCY_RESUS', 'Crash call.');

        $this->assertInstanceOf(ClinicalBreakGlassLog::class, $log);
        $this->assertTrue($guard->hasAccess(55, 'CLIENT-ZTNA-3', 1));
    }

    public function test_an_expired_break_glass_grant_no_longer_permits_access(): void
    {
        ClinicalBreakGlassLog::create([
            'business_id' => 1,
            'user_id' => 77,
            'client_id' => 'CLIENT-ZTNA-4',
            'reason_code' => 'OVERRIDE_EMERGENCY_RESUS',
            'granted_until' => now()->subMinute(),
        ]);

        $this->assertFalse(app(ZtnaAccessGuard::class)->hasAccess(77, 'CLIENT-ZTNA-4', 1));
    }
}
