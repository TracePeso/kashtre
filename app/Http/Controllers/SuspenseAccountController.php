<?php

namespace App\Http\Controllers;

use App\Models\MoneyAccount;
use App\Models\Business;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SuspenseAccountController extends Controller
{
    /**
     * Display suspense accounts dashboard
     */
    public function index(Request $request)
    {
        try {
            $businessId = Auth::user()->business_id;
            $business = Business::findOrFail($businessId);

            // For Kashtre (business_id = 1), show suspense accounts from ALL businesses
            if ($businessId == 1) {
                // Get all suspense accounts from all businesses
                $suspenseAccounts = MoneyAccount::whereIn('type', [
                    'package_suspense_account',
                    'general_suspense_account', 
                    'kashtre_suspense_account',
                    'withdrawal_suspense_account'
                ])
                ->with(['business', 'client'])
                ->orderBy('type')
                ->orderBy('balance', 'desc')
                ->get();

                // Get client suspense accounts from all businesses
                $clientSuspenseAccounts = MoneyAccount::whereIn('type', [
                    'package_suspense_account',
                    'general_suspense_account', 
                    'kashtre_suspense_account',
                    'withdrawal_suspense_account'
                ])
                ->whereNotNull('client_id')
                ->with('client')
                ->orderBy('balance', 'desc')
                ->get();
            } else {
                // For regular businesses, only show their own suspense accounts
                $suspenseAccounts = MoneyAccount::where('business_id', $businessId)
                    ->whereIn('type', [
                        'package_suspense_account',
                        'general_suspense_account', 
                        'kashtre_suspense_account',
                        'withdrawal_suspense_account'
                    ])
                    ->with(['business', 'client'])
                    ->orderBy('type')
                    ->orderBy('balance', 'desc')
                    ->get();

                // Get client suspense accounts for this business only
                $clientSuspenseAccounts = MoneyAccount::where('business_id', $businessId)
                    ->whereIn('type', [
                        'package_suspense_account',
                        'general_suspense_account', 
                        'kashtre_suspense_account',
                        'withdrawal_suspense_account'
                    ])
                    ->whereNotNull('client_id')
                    ->with('client')
                    ->orderBy('balance', 'desc')
                    ->get();
            }

            // Calculate totals
            $totalPackageSuspense = $suspenseAccounts->where('type', 'package_suspense_account')->sum('balance');
            $totalGeneralSuspense = $suspenseAccounts->where('type', 'general_suspense_account')->sum('balance');
            $totalKashtreSuspense = $suspenseAccounts->where('type', 'kashtre_suspense_account')->sum('balance');
            $totalWithdrawalSuspense = $suspenseAccounts->where('type', 'withdrawal_suspense_account')->sum('balance');
            $totalClientSuspense = $clientSuspenseAccounts->sum('balance');
            
            // Calculate total suspense balance (sum of all suspense accounts)
            $totalSuspenseBalance = $totalPackageSuspense + $totalGeneralSuspense + $totalKashtreSuspense + $totalWithdrawalSuspense;


            // Get recent money movements
            if ($businessId == 1) {
                // For Kashtre, show movements from all businesses
                $recentMovements = \App\Models\MoneyTransfer::whereHas('fromAccount', function($query) {
                        $query->whereIn('type', ['package_suspense_account', 'general_suspense_account', 'kashtre_suspense_account', 'withdrawal_suspense_account']);
                    })
                    ->orWhereHas('toAccount', function($query) {
                        $query->whereIn('type', ['package_suspense_account', 'general_suspense_account', 'kashtre_suspense_account', 'withdrawal_suspense_account']);
                    })
                    ->with(['fromAccount', 'toAccount', 'invoice.client.insuranceCompany'])
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();
            } else {
                // For regular businesses, only show their own movements
                $recentMovements = \App\Models\MoneyTransfer::whereHas('fromAccount', function($query) use ($businessId) {
                        $query->where('business_id', $businessId);
                    })
                    ->orWhereHas('toAccount', function($query) use ($businessId) {
                        $query->where('business_id', $businessId);
                    })
                    ->with(['fromAccount', 'toAccount', 'invoice.client.insuranceCompany'])
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();
            }

            Log::info("Suspense accounts dashboard accessed", [
                'business_id' => $businessId,
                'business_name' => $business->name,
                'suspense_accounts_count' => $suspenseAccounts->count(),
                'client_suspense_accounts_count' => $clientSuspenseAccounts->count(),
                'total_package_suspense' => $totalPackageSuspense,
                'total_general_suspense' => $totalGeneralSuspense,
                'total_kashtre_suspense' => $totalKashtreSuspense,
                'total_withdrawal_suspense' => $totalWithdrawalSuspense,
                'total_suspense_balance' => $totalSuspenseBalance
            ]);

            return view('suspense-accounts.index', compact(
                'business',
                'suspenseAccounts',
                'clientSuspenseAccounts',
                'totalPackageSuspense',
                'totalGeneralSuspense',
                'totalKashtreSuspense',
                'totalWithdrawalSuspense',
                'totalClientSuspense',
                'totalSuspenseBalance',
                'recentMovements'
            ));

        } catch (\Exception $e) {
            Log::error("Error accessing suspense accounts dashboard", [
                'error' => $e->getMessage(),
                'business_id' => Auth::user()->business_id ?? null,
                'user_id' => Auth::id()
            ]);

            return redirect()->back()->with('error', 'Failed to load suspense accounts dashboard.');
        }
    }

    /**
     * Show detailed view of a specific suspense account
     */
    public function show($id)
    {
        try {
            $businessId = Auth::user()->business_id;
            $account = MoneyAccount::where('id', $id)
                ->where('business_id', $businessId)
                ->with(['business', 'client'])
                ->firstOrFail();

            // Get money movements for this account
            $moneyMovements = \App\Models\MoneyTransfer::where('from_account_id', $account->id)
                ->orWhere('to_account_id', $account->id)
                ->with(['fromAccount', 'toAccount'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            // Get balance history
            // For withdrawal suspense accounts (business-level), use BusinessBalanceHistory
            // For other suspense accounts (client-level), use BalanceHistory
            if ($account->type === 'withdrawal_suspense_account') {
                // Ensure we only get records for this specific withdrawal suspense account
                // Double-check by verifying the money_account type matches
                // Also exclude any records that look like invoice/package transactions (they shouldn't be here)
                $balanceHistory = \App\Models\BusinessBalanceHistory::where('business_balance_histories.money_account_id', $account->id)
                    ->where('business_balance_histories.business_id', $account->business_id)
                    ->whereHas('moneyAccount', function($query) {
                        $query->where('type', 'withdrawal_suspense_account');
                    })
                    ->where(function($query) {
                        // Only show withdrawal-related records
                        $query->where('description', 'like', '%Withdrawal%')
                              ->orWhere('description', 'like', '%Bank Schedule%');
                    })
                    ->with(['business', 'user', 'moneyAccount'])
                    ->orderBy('business_balance_histories.created_at', 'desc')
                    ->limit(50)
                    ->get();
            } else {
                $balanceHistory = \App\Models\BalanceHistory::where('account_id', $account->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();
            }

            Log::info("Suspense account details accessed", [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'balance' => $account->balance,
                'business_id' => $businessId
            ]);

            return view('suspense-accounts.show', compact('account', 'moneyMovements', 'balanceHistory'));

        } catch (\Exception $e) {
            Log::error("Error accessing suspense account details", [
                'error' => $e->getMessage(),
                'account_id' => $id,
                'business_id' => Auth::user()->business_id ?? null
            ]);

            return redirect()->back()->with('error', 'Failed to load suspense account details.');
        }
    }

    /**
     * Get suspense accounts data for API
     */
    public function getSuspenseAccountsData()
    {
        try {
            $businessId = Auth::user()->business_id;
            
            $suspenseAccounts = MoneyAccount::where('business_id', $businessId)
                ->whereIn('type', [
                    'package_suspense_account',
                    'general_suspense_account', 
                    'kashtre_suspense_account',
                    'withdrawal_suspense_account'
                ])
                ->with(['business', 'client'])
                ->get();

            $data = [
                'package_suspense' => [
                    'accounts' => $suspenseAccounts->where('type', 'package_suspense_account'),
                    'total_balance' => $suspenseAccounts->where('type', 'package_suspense_account')->sum('balance')
                ],
                'general_suspense' => [
                    'accounts' => $suspenseAccounts->where('type', 'general_suspense_account'),
                    'total_balance' => $suspenseAccounts->where('type', 'general_suspense_account')->sum('balance')
                ],
                'kashtre_suspense' => [
                    'accounts' => $suspenseAccounts->where('type', 'kashtre_suspense_account'),
                    'total_balance' => $suspenseAccounts->where('type', 'kashtre_suspense_account')->sum('balance')
                ],
                'withdrawal_suspense' => [
                    'accounts' => $suspenseAccounts->where('type', 'withdrawal_suspense_account'),
                    'total_balance' => $suspenseAccounts->where('type', 'withdrawal_suspense_account')->sum('balance')
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting suspense accounts data", [
                'error' => $e->getMessage(),
                'business_id' => Auth::user()->business_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load suspense accounts data'
            ], 500);
        }
    }
}
