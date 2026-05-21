<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyHrApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-HR-API-Key');
        $expectedKey = config('services.hr_module.api_key');

        if (!is_string($expectedKey) || $expectedKey === '' || !is_string($apiKey) || !hash_equals($expectedKey, $apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
