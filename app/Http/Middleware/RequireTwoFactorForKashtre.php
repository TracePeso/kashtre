<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorForKashtre
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip 2FA enforcement in local development
        if (config('app.env') === 'local') {
            return $next($request);
        }
        
        // Skip 2FA for third-party payer routes (they use a different guard)
        if ($request->is('third-party-payer*') || $request->is('third-party-payer-dashboard*')) {
            return $next($request);
        }
        
        // Skip 2FA for cashier routes (they use a different guard)
        if ($request->is('cashier*') || $request->is('cashier-dashboard*')) {
            return $next($request);
        }

        // Broadcast auth endpoints must return signed JSON, not interactive redirects.
        if ($request->is('broadcasting/*')) {
            return $next($request);
        }
        
        // Only check for authenticated users
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Enforce 2FA for ALL users (not just Kashtre) in non-local environments

        // Allow access to these routes to avoid redirect loops
        $allowedRoutes = [
            'profile.show',
            'profile.update',
            'logout',
            'login',
            'register',
        ];

        // Check if current route is allowed (handle case where route might be null)
        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, $allowedRoutes)) {
            return $next($request);
        }

        // Allow Livewire requests (needed for 2FA enable button and other Livewire components)
        if ($request->is('livewire/*') || $request->header('X-Livewire')) {
            return $next($request);
        }

        // Allow access to two-factor authentication routes, password confirmation, and profile-related routes
        $allowedPaths = [
            'user/two-factor-authentication',
            'user/confirmed-two-factor-authentication',
            'user/confirm-password',
            'user/recovery-codes',
        ];
        
        foreach ($allowedPaths as $path) {
            if ($request->is($path) || $request->is($path . '*')) {
                return $next($request);
            }
        }
        
        // Also allow password confirmation route name
        if ($routeName === 'password.confirm') {
            return $next($request);
        }

        // Check if 2FA is enabled (two_factor_confirmed_at is set)
        if (empty($user->two_factor_confirmed_at)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Two-factor authentication (2FA) is required before accessing this endpoint.',
                ], 403);
            }

            return redirect()->route('profile.show')
                ->with('warning', 'Two-factor authentication (2FA) is required for all users. You must enable 2FA before accessing other parts of the system. Please set up 2FA in your profile settings below.');
        }

        return $next($request);
    }
}
