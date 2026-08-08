<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;

class FailedTwoFactorLoginResponse implements FailedTwoFactorLoginResponseContract
{
    public function toResponse($request)
    {
        [$key, $message] = $request->filled('recovery_code')
            ? ['recovery_code', __('The provided two factor recovery code was invalid.')]
            : ['code', __('The provided two factor authentication code was invalid.')];

        if ($request->wantsJson()) {
            throw ValidationException::withMessages([
                $key => [$message],
            ]);
        }

        return redirect()
            ->route('two-factor.login', ['mode' => $this->challengeMode($request)])
            ->withErrors([$key => $message]);
    }

    private function challengeMode(Request $request): string
    {
        $mode = $request->input('challenge_mode');

        if (in_array($mode, ['code', 'recovery'], true)) {
            return $mode;
        }

        return $request->filled('recovery_code') ? 'recovery' : 'code';
    }
}
