<?php

namespace App\Http\Middleware;

use App\Contracts\Clinical\CareAccessGateway;
use App\Services\Clinical\ZtnaAccessGuard;
use App\Support\Clinical\ClinicalActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied only to the patient-chart page-load route (observations.show) —
 * this is where "does this clinician have a right to view this patient"
 * naturally gates, and where the visible watermark overlay needs to
 * render. It does NOT gate every Livewire AJAX update for that page
 * (Livewire's /livewire/update route is shared/generic across every
 * component and page, so per-page middleware can't reach it) — repeat
 * mutating actions call ZtnaAccessGuard directly instead (see
 * MedicationOrdersPanel).
 */
class ZtnaContextMiddleware
{
    public function __construct(
        private readonly ZtnaAccessGuard $guard,
        private readonly CareAccessGateway $careAccess,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $isOnPremises = $this->guard->isOnPremises($request);
        $request->attributes->set('is_on_premises', $isOnPremises);
        view()->share('ztnaOffPremises', ! $isOnPremises);

        $user = $request->user();
        $clientId = $request->route('clientId');

        // Through the gateway, not ZtnaAccessGuard: the guard reads the local
        // clinical_care_assignments table, which under CLINICAL_DRIVER=api does
        // not exist here — the care relationship lives in the Clinical Module.
        // (The guard is still the local *implementation* behind this gateway,
        // so asking it directly would pin the check to one driver.)
        $hasAccess = $clientId && $user
            ? $this->careAccess->hasActiveRelationship(ClinicalActor::fromUser($user), $clientId)
            : true;

        if ($clientId && $user && ! $hasAccess) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'REBAC_ACCESS_DENIED',
                    'message' => 'No active care relationship found. Break-Glass emergency override required to view this patient chart.',
                    'requires_break_glass' => true,
                ], 403);
            }

            return response()->view('clinical.access-denied.show', [
                'clientId' => $clientId,
                'visitId' => $request->query('visit_id'),
            ], 403);
        }

        $response = $next($request);

        if (! $isOnPremises && $response instanceof Response) {
            foreach ($this->guard->watermarkHeaders($request) as $key => $value) {
                $response->headers->set($key, $value);
            }
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        return $response;
    }
}
