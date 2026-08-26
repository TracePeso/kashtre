<?php

namespace App\Http\Middleware;

use App\Models\KashtreHrModuleSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyHrApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = KashtreHrModuleSetting::resolved()->apiKey();

        // Spec uses X-API-Key; keep X-HR-API-Key for backwards compatibility.
        $apiKey = $request->header('X-API-Key')
            ?: $request->header('X-HR-API-Key');

        if ($expectedKey === '' || ! is_string($apiKey) || ! hash_equals($expectedKey, $apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
