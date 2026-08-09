<?php

namespace App\Services\Clinical\Api;

use App\Models\Business;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Translates this module's vocabulary into the Clinical Module's.
 *
 * The two do not agree on identifiers, and that mismatch is the single
 * biggest source of integration bugs in this split:
 *
 *   here                        Clinical API
 *   ----------------------      --------------------------------
 *   users.business_id (int)     X-Tenant-Id (string, §4)
 *   users.branch_id (int)       — no equivalent; Clinical is tenant-scoped only
 *   clients.client_id (string)  global_client_id / {patientId} (§10)
 *   clients.visit_id (string)   visit_id
 *   users.permissions (array)   X-User-Roles / JWT roles claim (§3.2)
 *
 * Nothing else in the codebase should be building these headers by hand.
 */
class ClinicalRequestContext
{
    /**
     * Main's permission strings mapped onto the role codes Clinical's ReBAC
     * and prescriber-accountability checks understand.
     *
     * Clinical decides what a role may do; this only asserts what the user
     * *is*. Sending a role the user does not hold does not widen their access
     * — Clinical still applies the care-relationship gate — but it does
     * misattribute the action in the audit trail, so keep this honest.
     *
     * @var array<string, string>
     */
    private const ROLE_MAP = [
        'Act As Consultant (Clinical)' => 'CONSULTANT',
        'Act As Ward Nurse (Clinical)' => 'WARD_NURSE',
        'Override CDSS Safety Block' => 'SENIOR_CLINICIAN',
        'Prescribe Medication Orders' => 'PRESCRIBER',
        'Administer MAR Doses' => 'ADMINISTERING_NURSE',
        'Manage Care Assignments' => 'DUTY_RESIDENT',
    ];

    /**
     * §4: "If a token carries tenant_id, the token wins" — a header a caller
     * sets freely must not decide whose charts they see. We are the issuer of
     * that identity, so the tenant we send is always derived from the
     * authenticated user's business, never from anything client-supplied.
     */
    public function tenantId(?int $businessId = null): string
    {
        $businessId ??= Auth::user()?->business_id;

        if (! $businessId) {
            return (string) config('services.clinical.default_tenant', 'DEFAULT');
        }

        // Businesses change rarely and this is on the path of every clinical
        // call; a short cache keeps the translation off the hot path without
        // making a renamed entity_code take effect only after a deploy.
        return Cache::remember(
            "clinical:tenant:{$businessId}",
            now()->addMinutes(10),
            function () use ($businessId): string {
                $business = Business::find($businessId);

                if (! $business) {
                    return (string) config('services.clinical.default_tenant', 'DEFAULT');
                }

                // entity_code is the human-meaningful facility identifier and
                // is what an operator will recognise in Clinical's audit
                // trail. Fall back to a synthetic but stable id rather than
                // silently pooling an unmapped business into DEFAULT, which
                // would let one facility read another's charts.
                return $business->entity_code
                    ? strtoupper((string) $business->entity_code)
                    : "TENANT-{$businessId}";
            }
        );
    }

    /**
     * The Clinical API addresses patients by Main's global_client_id string,
     * never by our numeric primary key. Accepts either and returns the
     * string form.
     */
    public function patientId(Client|string|int $client): string
    {
        if ($client instanceof Client) {
            return (string) $client->client_id;
        }

        if (is_int($client)) {
            return (string) Client::whereKey($client)->value('client_id');
        }

        return $client;
    }

    /**
     * Numeric key for a global_client_id — the reverse trip, needed whenever
     * a Clinical response has to be joined back onto our own tables (queues,
     * invoices, money accounts all key on clients.id).
     */
    public function localClientId(string $patientId, ?int $businessId = null): ?int
    {
        $businessId ??= Auth::user()?->business_id;

        return Client::query()
            ->when($businessId, fn ($query) => $query->where('business_id', $businessId))
            ->where('client_id', $patientId)
            ->value('id');
    }

    /**
     * §3.2 identity headers. Returning an empty array is meaningful, not a
     * failure: "no identity at all" tells Clinical this is module traffic
     * rather than a person, which skips the care-relationship gate but also
     * bars anything needing a named clinician.
     *
     * @return array<string, string>
     */
    public function identityHeaders(?User $user = null): array
    {
        $user ??= Auth::user();

        if (! $user) {
            return [];
        }

        if (config('services.clinical.identity_transport') === 'jwt') {
            $token = $this->identityToken($user);

            // Falling back to headers when the token is unavailable would be
            // a silent privilege downgrade that works right up until Clinical
            // sets IDENTITY_JWT_REQUIRED=true and starts refusing headers with
            // 401. Better to send nothing and fail loudly as module traffic.
            return $token ? ['Authorization' => 'Bearer '.$token] : [];
        }

        return array_filter([
            'X-User-Id' => (string) $user->id,
            'X-User-Name' => (string) $user->name,
            'X-User-Roles' => implode(',', $this->rolesFor($user)),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function rolesFor(?User $user = null): array
    {
        $user ??= Auth::user();
        $permissions = $user?->permissions ?? [];

        $roles = [];

        foreach (self::ROLE_MAP as $permission => $role) {
            if (in_array($permission, $permissions, true)) {
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }

    public function hasRole(string $role, ?User $user = null): bool
    {
        return in_array($role, $this->rolesFor($user), true);
    }

    /**
     * §3.2 preferred transport: an RS256 token minted by Main and verified
     * against our public key, with iss and aud asserted so a token minted for
     * LIMS cannot be replayed here.
     *
     * Not built yet — §14 lists identity tokens as "verifier built and
     * switched off" on Clinical's side, and Main has no signing key deployed.
     * Returning null keeps the seam honest: the transport switch exists and
     * is wired, but selecting 'jwt' before this is implemented degrades the
     * caller to module traffic rather than pretending to have signed a token.
     */
    private function identityToken(User $user): ?string
    {
        return null;
    }
}
