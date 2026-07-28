<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RIS Amendment v2.6, Chunk 8. Same shared-secret idiom as VerifyHrApiKey —
 * the eventual Clinical Module authenticates as a single trusted
 * integration partner and specifies business_id/user_id per-request where
 * relevant, rather than this middleware resolving a business/user from the
 * key itself.
 */
class VerifyImagingApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Imaging-API-Key');
        $expectedKey = config('services.imaging_module.api_key');

        if (! is_string($expectedKey) || $expectedKey === '' || ! is_string($apiKey) || ! hash_equals($expectedKey, $apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
