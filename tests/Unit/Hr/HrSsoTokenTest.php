<?php

namespace Tests\Unit\Hr;

use App\Models\User;
use App\Support\Hr\HrSsoToken;
use Tests\TestCase;

/**
 * Contract test: the HR module's own SsoController/KashtreSsoToken verify
 * exactly this format (`email|business_id|timestamp`, base64-wrapped with
 * an HMAC-SHA256 signature using the shared services.hr_module.api_key /
 * HR_MODULE_API_KEY secret). A drift here would silently break both the
 * embed iframe handshake and the per-user navigation filtering fix.
 *
 * Uses the app's own TestCase (needed for the config() helper make() reads)
 * but not RefreshDatabase: an unpersisted `new User(...)` is enough, since
 * make() only reads the given instance's own attributes.
 */
class HrSsoTokenTest extends TestCase
{
    public function test_the_token_decodes_to_the_expected_payload_and_signature(): void
    {
        $user = new User(['email' => 'contract-test@example.com', 'business_id' => 42]);

        $token = HrSsoToken::make($user);
        $raw = base64_decode($token, true);

        $this->assertNotFalse($raw);
        $this->assertStringContainsString(':', $raw);

        $lastColon = strrpos($raw, ':');
        $payload = substr($raw, 0, $lastColon);
        $signature = substr($raw, $lastColon + 1);

        [$email, $businessId, $timestamp] = explode('|', $payload);

        $this->assertSame('contract-test@example.com', $email);
        $this->assertSame('42', $businessId);
        $this->assertEqualsWithDelta(time(), (int) $timestamp, 5);
        $this->assertSame(hash_hmac('sha256', $payload, (string) config('services.hr_module.api_key', '')), $signature);
    }

    public function test_a_user_with_no_business_id_still_produces_a_verifiable_token(): void
    {
        $user = new User(['email' => 'no-business@example.com', 'business_id' => null]);

        $token = HrSsoToken::make($user);
        $raw = base64_decode($token, true);
        $payload = substr($raw, 0, strrpos($raw, ':'));

        [$email, $businessId] = explode('|', $payload);

        $this->assertSame('no-business@example.com', $email);
        $this->assertSame('', $businessId);
    }
}
