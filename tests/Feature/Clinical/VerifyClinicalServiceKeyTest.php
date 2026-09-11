<?php

namespace Tests\Feature\Clinical;

use Tests\TestCase;

/**
 * Guards the inbound endpoints Clinical calls on us (§12, §14).
 *
 * These accept patient-affecting callbacks, so the failure mode that matters
 * is accepting one from an unauthenticated caller. Every case here is a
 * rejection, and every rejection is checked before the request reaches a
 * controller — which is also why these run without a database.
 */
class VerifyClinicalServiceKeyTest extends TestCase
{
    public function test_a_request_with_no_service_key_is_rejected(): void
    {
        config(['services.clinical.inbound_keys' => ['valid-key']]);

        $this->postJson('/api/v1/events', ['event_id' => 'e1', 'fact_token' => 'X'])
            ->assertStatus(401)
            ->assertJsonPath('errors.error_code', 'SERVICE_KEY_REQUIRED');
    }

    public function test_a_request_with_the_wrong_service_key_is_rejected(): void
    {
        config(['services.clinical.inbound_keys' => ['valid-key']]);

        $this->withHeaders(['X-Service-Key' => 'wrong-key'])
            ->postJson('/api/v1/events', ['event_id' => 'e1', 'fact_token' => 'X'])
            ->assertStatus(401)
            ->assertJsonPath('errors.error_code', 'INVALID_SERVICE_KEY');
    }

    public function test_an_unconfigured_allowlist_rejects_everything(): void
    {
        config(['services.clinical.inbound_keys' => []]);

        // Fails closed. An integration nobody configured must not be an open
        // door — the alternative is accepting clinical events from anyone who
        // can reach the host.
        $this->withHeaders(['X-Service-Key' => 'anything'])
            ->postJson('/api/v1/events', ['event_id' => 'e1', 'fact_token' => 'X'])
            ->assertStatus(401);
    }

    public function test_any_key_in_the_rotation_list_is_accepted(): void
    {
        config(['services.clinical.inbound_keys' => ['old-key', 'new-key']]);

        // A 422 proves the middleware let this through to validation — the
        // point of the multi-key list is that both are live during a rotation,
        // so neither service needs a synchronised restart.
        $this->withHeaders(['X-Service-Key' => 'new-key'])
            ->postJson('/api/v1/catalogue/resolve', [])
            ->assertStatus(422);

        $this->withHeaders(['X-Service-Key' => 'old-key'])
            ->postJson('/api/v1/catalogue/resolve', [])
            ->assertStatus(422);
    }

    public function test_the_catalogue_lookup_is_not_reachable_unauthenticated(): void
    {
        config(['services.clinical.inbound_keys' => ['valid-key']]);

        $this->postJson('/api/v1/catalogue/resolve', [
            'tenant_id' => 'FACILITY_ALPHA',
            'requested_term' => 'Ceftriaxone',
        ])->assertStatus(401);

        $this->getJson('/api/v1/catalogue/items/DRUG-1?tenant_id=FACILITY_ALPHA')
            ->assertStatus(401);
    }
}
