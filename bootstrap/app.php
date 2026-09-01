<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureCashier;
use App\Http\Middleware\NormalizeTwoFactorChallengeInput;
use App\Http\Middleware\RequireTwoFactorForKashtre;
use App\Http\Middleware\VerifyClinicalApiKey;
use App\Http\Middleware\VerifyHrApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['web', 'auth', 'verified'])
                ->group(base_path('routes/third_party_vendor_service_charges.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            NormalizeTwoFactorChallengeInput::class,
            RequireTwoFactorForKashtre::class,
        ]);

        $middleware->alias([
            'auth.api' => AuthenticateApiKey::class,
            'require.2fa.kashtre' => RequireTwoFactorForKashtre::class,
            'cashier' => EnsureCashier::class,
            'hr.api' => VerifyHrApiKey::class,
            'clinical.api' => VerifyClinicalApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            TokenMismatchException::class,
        ]);

        $exceptions->renderable(function (HttpException $e, Request $request): ?Response {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $message = 'Your session has expired. Please sign in again.';

            if ($request->is('third-party-payer*', 'third-party-payer-dashboard*')) {
                $loginUrl = route('third-party-payer.login');
            } elseif ($request->is('cashier*', 'cashier-dashboard*')) {
                $loginUrl = route('cashier.login');
            } else {
                $loginUrl = route('login');
            }

            if ($request->expectsJson() && ! $request->header('X-Livewire')) {
                return response()->json([
                    'message' => $message,
                    'redirect' => $loginUrl,
                ], 419);
            }

            return redirect()->guest($loginUrl)->with('status', $message);
        });
    })->create();
