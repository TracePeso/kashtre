<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the Clinical Module when it calls *us* — the mirror image of
 * the X-Service-Key we present to it (API Integration Guide §3.1).
 *
 * Reads the same header name Clinical uses, so both directions of the
 * integration share one convention and one thing to rotate.
 *
 * CLINICAL_INBOUND_SERVICE_KEYS is comma-separated specifically so a key can
 * be rotated without downtime: publish the new key, let both be accepted,
 * cut Clinical over, then drop the old one. A single-valued secret forces a
 * synchronised restart of two services, which in a hospital means a
 * maintenance window.
 */
class VerifyClinicalServiceKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $presented = $request->header('X-Service-Key');
        $accepted = (array) config('services.clinical.inbound_keys', []);

        // An empty allowlist means the integration was never configured.
        // Failing closed matters here: the alternative is an endpoint that
        // accepts patient-affecting callbacks from anyone.
        if ($accepted === [] || ! is_string($presented) || $presented === '') {
            return response()->json([
                'message' => 'Unauthorized.',
                'errors' => ['error_code' => 'SERVICE_KEY_REQUIRED'],
            ], 401);
        }

        foreach ($accepted as $key) {
            // hash_equals rather than === so a wrong key cannot be recovered
            // one byte at a time by timing the response.
            if (is_string($key) && $key !== '' && hash_equals($key, $presented)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Unauthorized.',
            'errors' => ['error_code' => 'INVALID_SERVICE_KEY'],
        ], 401);
    }
}
