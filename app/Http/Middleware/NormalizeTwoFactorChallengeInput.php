<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeTwoFactorChallengeInput
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('POST')
            && $request->is('two-factor-challenge', 'user/two-factor-security-questions')
        ) {
            $payload = [];

            if ($request->exists('code')) {
                $normalized = preg_replace('/\s+/', '', (string) $request->input('code', ''));
                $payload['code'] = $normalized !== '' ? $normalized : null;
            }

            if ($request->exists('recovery_code')) {
                $normalized = preg_replace('/\s+/', '', (string) $request->input('recovery_code', ''));
                $payload['recovery_code'] = $normalized !== '' ? $normalized : null;
            }

            if ($payload !== []) {
                $request->merge($payload);
            }
        }

        return $next($request);
    }
}
