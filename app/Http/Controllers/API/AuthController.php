<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Services\Clinical\Api\ClinicalRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Login with the same email/password used on the web app.
     * When authenticator 2FA is enabled, also send `code`.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('email', strtolower($request->string('email')->toString()))
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('The provided credentials do not match our records.')],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => [__('Your account has been deactivated. Please contact support.')],
            ]);
        }

        if ($user->two_factor_confirmed_at) {
            $code = preg_replace('/\s+/', '', (string) $request->input('code', ''));

            if ($code === '') {
                return response()->json([
                    'success' => false,
                    'message' => __('Two-factor authentication code is required.'),
                    'two_factor_required' => true,
                ], 422);
            }

            $valid = app(TwoFactorAuthenticationProvider::class)->verify(
                decrypt($user->two_factor_secret),
                $code
            );

            if (! $valid) {
                throw ValidationException::withMessages([
                    'code' => [__('The provided two factor authentication code was invalid.')],
                ]);
            }
        }

        $deviceName = $request->input('device_name', 'api');
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load(['business', 'branch', 'defaultStore']);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Revoke the current API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => __('Logged out successfully.'),
        ]);
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['business', 'branch', 'defaultStore']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * OAuth2-style token introspection (RFC 7662 shape) for the Clinical
     * Module — their checklist §7, option (b).
     *
     * Their verifier wants to attribute a clinician without trusting an
     * X-User-Id header a browser could set. Main issues opaque Sanctum tokens
     * rather than RS256 JWTs, so it answers "who is this token" directly and
     * Clinical caches the result.
     *
     * Deliberately never 401s on a bad token: RFC 7662 says an inactive token
     * is `{"active": false}` with 200, and returning an error instead lets a
     * caller distinguish "malformed" from "revoked" and probe for valid ones.
     */
    public function introspect(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $accessToken = PersonalAccessToken::findToken($request->string('token')->toString());

        /** @var User|null $user */
        $user = $accessToken?->tokenable instanceof User ? $accessToken->tokenable : null;

        if (! $accessToken || ! $user || $user->status !== 'active') {
            return response()->json(['active' => false]);
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return response()->json(['active' => false]);
        }

        $context = app(ClinicalRequestContext::class);

        return response()->json([
            'active' => true,
            'user_id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $context->rolesFor($user),
            'tenant_id' => $context->tenantId($user->business_id),
            'business_id' => $user->business_id,
            'branch_id' => $user->branch_id,
            'issued_at' => $accessToken->created_at?->toIso8601String(),
            'last_used_at' => $accessToken->last_used_at?->toIso8601String(),
            'expires_at' => $accessToken->expires_at?->toIso8601String(),
        ]);
    }
}
