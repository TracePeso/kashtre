<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Payments\YoAPI;
use App\Support\YoDepositGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $rooms = $branch ? Room::where('branch_id', $branch->id)->get() : collect();

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
     * Send a minimal Yo! Payments pull (acdepositfunds) to verify prompts and API credentials on the server.
     */
    public function testYoPayment(Request $request)
    {
        $validated = $request->validate([
            'payment_phone' => 'required|string|max:32',
            'amount' => 'nullable|numeric|min:500|max:100000',
        ]);

        if (! YoDepositGate::useLiveYoDeposits()) {
            return redirect()->route('dashboard')->with('success',
                'Yo test skipped: live deposits are disabled (YO_LIVE_DEPOSITS_ENABLED is not true). '
                .'No call to Yo was made. Phone: '.($validated['payment_phone'] ?? '').'.'
            );
        }

        if (! config('payments.yo_username') || ! config('payments.yo_password')) {
            return redirect()->route('dashboard')
                ->with('error', 'Yo Payments is not configured. Set YO_PAYMENTS_USERNAME and YO_PAYMENTS_PASSWORD in .env.');
        }

        $amount = (float) ($validated['amount'] ?? 500);
        $phone = trim($validated['payment_phone']);
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '256'.substr($phone, 1);
        }
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) < 12) {
            return redirect()->route('dashboard')
                ->with('error', 'Enter a valid mobile number (e.g. 075… or +25675…).');
        }

        $externalRef = 'YOTEST-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
        $description = 'Kashtre dashboard Yo test';

        try {
            $yoApi = new YoAPI(
                config('payments.yo_username'),
                config('payments.yo_password')
            );
            $webhook = config('payments.webhook_url');
            if (! empty($webhook)) {
                $yoApi->set_instant_notification_url($webhook);
            }
            $yoApi->set_external_reference($externalRef);

            Log::info('Dashboard Yo test: initiating ac_deposit_funds', [
                'phone' => $phone,
                'amount' => $amount,
                'external_reference' => $externalRef,
                'webhook_configured' => (bool) $webhook,
            ]);

            $yoResult = $yoApi->ac_deposit_funds($phone, $amount, $description);

            Log::info('Dashboard Yo test: YoAPI response', ['result' => $yoResult]);

            $status = strtoupper((string) ($yoResult['Status'] ?? ''));
            if ($status === 'OK' && ! empty($yoResult['TransactionReference'])) {
                $txRef = $yoResult['TransactionReference'];

                return redirect()->route('dashboard')->with('success',
                    'Yo prompt initiated. Check the phone for the mobile money prompt. '
                    .'TransactionReference: '.$txRef.'. ExternalReference: '.$externalRef.'.'
                );
            }

            $msg = $yoResult['StatusMessage'] ?? json_encode($yoResult);

            return redirect()->route('dashboard')->with('error', 'Yo did not accept the request: '.$msg);
        } catch (\Throwable $e) {
            Log::error('Dashboard Yo test failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('dashboard')->with('error', 'Yo test failed: '.$e->getMessage());
        }
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
