<?php

namespace App\Services\Clinical\Api;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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

        // The tenant IS the business id. Nothing to look up, nothing to keep in
        // step, and no way for a facility to become unmapped by an edit to some
        // other field — which is exactly what a derived code allowed.
        //
        // It also round-trips: resolveBusinessId() on the inbound side reads a
        // numeric tenant straight back to this business, so both directions
        // agree without a translation table.
        return (string) $businessId;
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
            // Main authorises by permission, and most users hold no clinical
            // duty role at all — a gate on roles alone refuses everybody. Send
            // the permission column too so Clinical can grant on either.
            // Comma-separated rather than the raw JSON array: header values are
            // a poor place for JSON, and Clinical accepts both.
            'X-User-Permissions' => implode(',', $this->permissionsFor($user)),
        ]);
    }

    /**
     * The user's permission strings, de-duplicated. Main's `permissions` column
     * repeats group headings alongside the capabilities under them, and sending
     * the raw column would push several hundred bytes of duplicates into a
     * header on every clinical call.
     *
     * @return array<int, string>
     */
    public function permissionsFor(?User $user = null): array
    {
        $user ??= Auth::user();

        $permissions = array_filter(
            (array) ($user?->permissions ?? []),
            fn ($permission) => is_string($permission) && $permission !== '',
        );

        return array_values(array_unique($permissions));
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
