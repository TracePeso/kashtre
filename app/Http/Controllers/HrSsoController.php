<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrSsoController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $user    = Auth::user();
        $apiKey  = config('services.hr_module.api_key', '');
        $hrUrl   = rtrim(config('services.hr_module.url', ''), '/');

        $payload   = $user->email . '|' . ($user->business_id ?? '') . '|' . time();
        $signature = hash_hmac('sha256', $payload, $apiKey);
        $token     = base64_encode($payload . ':' . $signature);

        $intendedPath = $request->input('path', '/hr/dashboard');

        return redirect($hrUrl . '/hr/sso?token=' . urlencode($token) . '&redirect=' . urlencode($intendedPath));
    }
}
