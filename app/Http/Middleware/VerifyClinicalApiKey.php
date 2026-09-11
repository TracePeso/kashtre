<?php

namespace App\Http\Middleware;

use App\Models\KashtreClinicalModuleSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyClinicalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = KashtreClinicalModuleSetting::resolved()->inboundApiKey();

        $provided = $request->header('X-Service-Key')
            ?: $request->header('X-API-Key');

        if ($expected === '' || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
