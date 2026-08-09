<?php

namespace App\Services\Clinical;

use App\Models\ClinicalBreakGlassLog;
use Illuminate\Http\Request;

/**
 * Engineering doc's ZtnaContextMiddleware, split into a plain service so
 * both real HTTP middleware (page-load context + watermark headers) and
 * Livewire component actions (the actual mutating "live order" calls,
 * which all share one generic /livewire/update route — middleware can't
 * distinguish "this is a prescribe() call" from "this is a render" at
 * the route level) can use the same checks. Chunk 2's
 * CareRelationshipChecker already covers the ReBAC half; this adds
 * on-premises detection, break-glass, and watermarking on top.
 */
class ZtnaAccessGuard
{
    public function __construct(private readonly CareRelationshipChecker $careChecker)
    {
    }

    public function isOnPremises(Request $request): bool
    {
        $subnets = config('services.ztna.hospital_subnets', []);
        $ip = $request->ip();

        foreach ($subnets as $subnet) {
            if ($this->ipInSubnet($ip, trim($subnet))) {
                return true;
            }
        }

        return $request->header('X-KashTre-mTLS-Verified') === 'true';
    }

    public function hasAccess(int $userId, string $clientId, int $businessId): bool
    {
        if ($this->careChecker->hasActiveRelationship($userId, $clientId, $businessId)) {
            return true;
        }

        return ClinicalBreakGlassLog::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->where('granted_until', '>', now())
            ->exists();
    }

    public function grantBreakGlass(
        int $userId,
        int $businessId,
        string $clientId,
        ?string $visitId,
        string $reasonCode,
        ?string $justificationNote = null,
        int $minutesValid = 15,
    ): ClinicalBreakGlassLog {
        return ClinicalBreakGlassLog::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'client_id' => $clientId,
            'visit_id' => $visitId,
            'reason_code' => $reasonCode,
            'justification_note' => $justificationNote,
            'granted_until' => now()->addMinutes($minutesValid),
        ]);
    }

    /**
     * Doc: "Live medication ordering and MAR dose administration are
     * strictly prohibited off-premises. Draft completion only." — called
     * at the top of the specific Livewire actions that place live orders,
     * not applied blanket to every mutation (see class docblock).
     */
    public function assertMutationAllowedOnPremises(Request $request): void
    {
        abort_unless(
            $this->isOnPremises($request),
            403,
            'ZTNA_OFF_PREMISE_RESTRICTION: Live medication ordering and MAR dose administration are strictly prohibited off-premises.'
        );
    }

    /**
     * @return array<string, string>
     */
    public function watermarkHeaders(Request $request): array
    {
        $user = $request->user();

        return [
            'X-KashTre-Watermark-User' => $user ? "{$user->name} (ID: {$user->id})" : 'unknown',
            'X-KashTre-Watermark-IP' => (string) $request->ip(),
            'X-KashTre-Watermark-Timestamp' => now()->toIso8601String(),
        ];
    }

    private function ipInSubnet(string $ip, string $subnet): bool
    {
        if (! str_contains($subnet, '/')) {
            return $ip === $subnet;
        }

        [$subnetIp, $bits] = explode('/', $subnet);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnetIp);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
