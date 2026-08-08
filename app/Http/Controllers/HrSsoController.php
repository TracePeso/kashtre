<?php

namespace App\Http\Controllers;

use App\Services\HrModuleSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HrSsoController extends Controller
{
    /**
     * Redirect an authenticated Kashtre user into the HR Module via SSO token.
     */
    public function redirect(Request $request, HrModuleSyncService $hrSync): RedirectResponse
    {
        $user = $request->user();

        $url = $hrSync->ssoRedirectUrl($user);

        if (! $url) {
            return redirect()
                ->back()
                ->with('error', __('HR Module URL is not configured.'));
        }

        return redirect()->away($url);
    }
}
