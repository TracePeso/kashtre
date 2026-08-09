<?php

namespace App\Http\Middleware;

use App\Services\Clinical\ZtnaAccessGuard;
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
    public function __construct(private readonly ZtnaAccessGuard $guard)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $isOnPremises = $this->guard->isOnPremises($request);
        $request->attributes->set('is_on_premises', $isOnPremises);
        view()->share('ztnaOffPremises', ! $isOnPremises);

        $user = $request->user();
        $clientId = $request->route('clientId');

        if ($clientId && $user && ! $this->guard->hasAccess((int) $user->id, $clientId, (int) $user->business_id)) {
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
