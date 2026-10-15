<?php

namespace App\Support\Hr;

use App\Models\User;

/**
 * Mints the signed `email|business_id|timestamp` token the HR module's
 * SsoController and, now, its navigation API both verify. One place to
 * build it, so the embed iframe handshake and a per-user navigation
 * lookup can never drift on the format.
 */
class HrSsoToken
{
    public static function make(User $user): string
    {
        $apiKey = config('services.hr_module.api_key', '');
        $payload = $user->email . '|' . ($user->business_id ?? '') . '|' . time();
        $signature = hash_hmac('sha256', $payload, $apiKey);

        return base64_encode($payload . ':' . $signature);
    }
}
