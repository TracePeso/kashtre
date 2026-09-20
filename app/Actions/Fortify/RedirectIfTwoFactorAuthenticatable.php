<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as FortifyRedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;

class RedirectIfTwoFactorAuthenticatable extends FortifyRedirectIfTwoFactorAuthenticatable
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        $user = $this->validateCredentials($request);

        if ($this->shouldChallenge($user)) {
            return $this->twoFactorChallengeResponse($request, $user);
        }

        return $next($request);
    }

    private function shouldChallenge(mixed $user): bool
    {
        if (! $user || ! in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)) {
            return false;
        }

        if ($user instanceof User) {
            return $user->hasSatisfiedRequiredTwoFactor();
        }

        if (Fortify::confirmsTwoFactorAuthentication()) {
            return $user->two_factor_secret && ! is_null($user->two_factor_confirmed_at);
        }

        return (bool) $user->two_factor_secret;
    }
}
