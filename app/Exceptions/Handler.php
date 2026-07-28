<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        TokenMismatchException::class,
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            return $this->sessionExpiredResponse($request);
        });

        // routes/api.php is unambiguously an API surface — force JSON error
        // responses there regardless of the client's Accept header, rather
        // than falling back to Laravel's default web-form behavior
        // (a redirect on validation failure, an HTML page on 404) that
        // only kicks in when Accept: application/json wasn't sent.
        $this->renderable(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        });

        $this->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['error' => 'Not found.'], 404);
        });
    }

    /**
     * Redirect to login when the session or CSRF token has expired (419 Page Expired).
     */
    protected function sessionExpiredResponse(Request $request): Response
    {
        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $message = 'Your session has expired. Please sign in again.';
        $loginUrl = $this->loginUrlForExpiredSession($request);

        if ($request->expectsJson() && ! $request->header('X-Livewire')) {
            return response()->json([
                'message' => $message,
                'redirect' => $loginUrl,
            ], 419);
        }

        return redirect()->guest($loginUrl)->with('status', $message);
    }

    protected function loginUrlForExpiredSession(Request $request): string
    {
        if ($request->is('third-party-payer*', 'third-party-payer-dashboard*')) {
            return route('third-party-payer.login');
        }

        if ($request->is('cashier*', 'cashier-dashboard*')) {
            return route('cashier.login');
        }

        return route('login');
    }
}
