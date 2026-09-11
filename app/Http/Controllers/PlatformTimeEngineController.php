<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class PlatformTimeEngineController extends Controller
{
    public function index()
    {
        $permissions = Auth::user()->permissions ?? [];
        $allowed = in_array('View Time Engine', $permissions, true)
            || in_array('Manage System Settings', $permissions, true)
            || in_array('Manage Settings', $permissions, true)
            || (int) Auth::user()->business_id === 1;

        abort_unless($allowed, 403);

        return view('platform.time.index', [
            'engineEnabled' => (bool) config('time.enabled'),
        ]);
    }
}
