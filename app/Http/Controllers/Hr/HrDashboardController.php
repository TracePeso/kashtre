<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Support\HrModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HrDashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if (! HrModule::userCanAccess($user)) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access HR Manager.');
        }

        return view('hr.index', [
            'hrPermissions' => array_values(array_intersect(HrModule::ACCESS_PERMISSIONS, (array) ($user?->permissions ?? []))),
        ]);
    }
}
