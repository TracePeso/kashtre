<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    // public function index()
    // {

    //     $user = Auth::user();
    //     $business = $user->business; // Make sure 'business' relationship exists
    //     $branch = $user->branch;

    //    //select  rooms that belong to this branch
    //    $rooms = Room::where('branch_id', $branch->id)->get();
    //     return view('pages/dashboard/dashboard', compact('business', 'branch', 'rooms'));
    // }

    public function index(Request $request)
    {
        $user = Auth::user();

        $business = $user->business;
        $branch = $user->branch;
        $rooms = Room::where('branch_id', $branch->id)->get();

        // Get allowed branches for the user
        $allowedBranches = [];
        if ($user->allowed_branches) {
            $allowedBranches = \App\Models\Branch::whereIn('id', (array) $user->allowed_branches)->get();
        }

        // Get current selected branch from session or default to user's branch
        $currentBranchId = session('current_branch_id', $user->branch_id);
        $currentBranch = \App\Models\Branch::find($currentBranchId);

        // Handle POST to set room_id in session
        if ($request->isMethod('post')) {
            $request->validate([
                'room_id' => 'required|exists:rooms,id',
            ]);
            session(['room_id' => $request->room_id]);

            return redirect()->route('dashboard'); // or redirect back to same page to avoid resubmission
        }

        return view('pages/dashboard/dashboard', compact('business', 'branch', 'rooms', 'allowedBranches', 'currentBranch'));
    }

    /**
     * Super-admin only (business_id = 1): run the same Artisan sequence used for dev/staging QA —
     * cancel pending service queues, clear suspense-related data, then full statement reset (incl. third-party).
     */
    public function clearTestingEnvironment(Request $request)
    {
        $user = Auth::user();
        if (! $user || (int) $user->business_id !== 1 || $user->status !== 'active') {
            abort(403, 'Only active Kashtre super-admin users can reset testing data.');
        }
        if (! $user->permissions || ! is_array($user->permissions)) {
            abort(403, 'Insufficient permissions.');
        }

        try {
            $steps = [];

            Artisan::call('service-queues:reset', ['--all' => true, '--force' => true]);
            $steps[] = ['service-queues:reset', Artisan::output()];

            Artisan::call('suspense:clear', ['--force' => true]);
            $steps[] = ['suspense:clear', Artisan::output()];

            Artisan::call('reset:account-statements', ['--confirm' => true]);
            $steps[] = ['reset:account-statements', Artisan::output()];

            Log::warning('[Dashboard] Super-admin testing environment reset executed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'steps_preview' => array_map(fn ($s) => $s[0], $steps),
            ]);

            return redirect()->route('dashboard')->with(
                'success',
                'Testing reset finished: pending queues cancelled, suspense cleared, statements / transfers / AR / third-party payer histories cleared and balances zeroed.'
            );
        } catch (\Throwable $e) {
            Log::error('[Dashboard] Testing environment reset failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('dashboard')->with(
                'error',
                'Testing reset failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Displays the analytics screen
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function analytics()
    {
        return view('pages/dashboard/analytics');
    }

    /**
     * Displays the fintech screen
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function fintech()
    {
        return view('pages/dashboard/fintech');
    }
}
