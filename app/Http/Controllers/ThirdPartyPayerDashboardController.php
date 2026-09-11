<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;
use App\Services\AccountBalanceSummaryService;
use App\Models\AccountsReceivable;
use App\Models\Item;
use App\Models\Business;
use App\Http\Middleware\Authenticate;

class ThirdPartyPayerDashboardController extends Controller
{
    /**
     * Display the third-party payer dashboard
     */
    public function index()
    {
        \Log::info('Third-party payer dashboard access attempt', [
            'auth_check' => Auth::guard('third_party_payer')->check() ? 'yes' : 'no',
            'user_id' => Auth::guard('third_party_payer')->id(),
        ]);
        
        $account = Auth::guard('third_party_payer')->user();
        
        if (!$account) {
            \Log::warning('Third-party payer dashboard access denied: No authenticated user');
            return redirect()->route('third-party-payer.login');
        }

        $thirdPartyPayer = $account->thirdPartyPayer;
        
        if (!$thirdPartyPayer) {
            abort(404, 'Third-party payer not found.');
        }

        // Calculate balance from balance history (source of truth)
        // Credits increase balance, debits decrease balance
        $totalCredits = ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
            ->where('transaction_type', 'credit')
            ->sum('change_amount');
        
        $totalDebits = abs(ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
            ->where('transaction_type', 'debit')
            ->sum('change_amount'));
        
        $calculatedBalance = $totalCredits - $totalDebits;
        
        // Current balance is the calculated balance (negative means they owe money)
        $currentBalance = $calculatedBalance;
        
        // Get recent transactions with relationships
        $recentTransactions = ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
            ->with(['invoice', 'client', 'business', 'branch'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Get accounts receivable summary
        $accountsReceivable = AccountsReceivable::where('third_party_payer_id', $thirdPartyPayer->id)
            ->where('status', '!=', 'paid')
            ->get();
        
        $totalOutstanding = $accountsReceivable->sum('balance');
        $totalDue = $accountsReceivable->sum('amount_due');
        $totalPaid = $accountsReceivable->sum('amount_paid');

        // Get business using the business_id stored on the third-party payer record
        // Ensure we're using the business_id that was set when the third-party payer was created
        if (!$thirdPartyPayer->business_id) {
            Log::error('Third-party payer missing business_id', [
                'third_party_payer_id' => $thirdPartyPayer->id,
                'third_party_payer_name' => $thirdPartyPayer->name,
            ]);
            abort(500, 'Third-party payer is not associated with a business.');
        }

        $business = Business::find($thirdPartyPayer->business_id);
        
        if (!$business) {
            Log::error('Business not found for third-party payer', [
                'third_party_payer_id' => $thirdPartyPayer->id,
                'business_id' => $thirdPartyPayer->business_id,
            ]);
            abort(404, 'Business not found for this third-party payer.');
        }

        // Get items for displaying exclusions using the business_id from the third-party payer
        $items = Item::where('business_id', $thirdPartyPayer->business_id)
            ->orderBy('name')
            ->get();
        
        // Use third-party payer's credit limit if set (and > 0), otherwise use business-level default
        // The business_id on the third-party payer record tracks where it was created from
        // If payer's credit_limit is null or 0, we use the business default
        $payerCreditLimit = $thirdPartyPayer->credit_limit;
        $businessCreditLimit = $business->max_third_party_credit_limit ?? null;
        
        // Use payer's limit only if it's set and greater than 0, otherwise use business default
        if ($payerCreditLimit && $payerCreditLimit > 0) {
            $effectiveCreditLimit = $payerCreditLimit;
        } else {
            $effectiveCreditLimit = $businessCreditLimit ?? 0;
        }

        // Merge business-level and individual third-party payer exclusions
        // Business-level exclusions apply to all third-party payers, individual exclusions are specific to this payer
        // If third_party_excluded_items is not set, fall back to credit_excluded_items
        $businessExcludedItems = $business->third_party_excluded_items;
        if (empty($businessExcludedItems) || (!is_array($businessExcludedItems) && is_null($businessExcludedItems))) {
            // Fall back to credit exclusions if third-party exclusions are not set
            $businessExcludedItems = $business->credit_excluded_items;
        }
        $payerExcludedItems = $thirdPartyPayer->excluded_items;
        
        // Ensure both are arrays before merging
        // Handle null, empty string, or non-array values
        if (is_null($businessExcludedItems) || $businessExcludedItems === '' || !is_array($businessExcludedItems)) {
            $businessExcludedItems = [];
        }
        if (is_null($payerExcludedItems) || $payerExcludedItems === '' || !is_array($payerExcludedItems)) {
            $payerExcludedItems = [];
        }
        
        // Filter out any null or invalid values from the arrays
        $businessExcludedItems = array_filter($businessExcludedItems, function($item) {
            return !is_null($item) && $item !== '';
        });
        $payerExcludedItems = array_filter($payerExcludedItems, function($item) {
            return !is_null($item) && $item !== '';
        });
        
        // Convert all item IDs to integers for consistent comparison
        // This handles cases where IDs are stored as strings in JSON
        $businessExcludedItems = array_map('intval', array_values($businessExcludedItems));
        $payerExcludedItems = array_map('intval', array_values($payerExcludedItems));
        
        // Merge and get unique item IDs
        $effectiveExcludedItems = array_values(array_unique(array_merge(
            $businessExcludedItems,
            $payerExcludedItems
        )));

        // Debug logging to help diagnose issues
        Log::info('Third-party payer dashboard data', [
            'third_party_payer_id' => $thirdPartyPayer->id,
            'third_party_payer_name' => $thirdPartyPayer->name,
            'business_id' => $thirdPartyPayer->business_id,
            'business_name' => $business->name,
            'payer_credit_limit' => $payerCreditLimit,
            'payer_credit_limit_type' => gettype($payerCreditLimit),
            'business_credit_limit' => $businessCreditLimit,
            'business_credit_limit_type' => gettype($businessCreditLimit),
            'effective_credit_limit' => $effectiveCreditLimit,
            'business_third_party_excluded_items_raw' => $business->third_party_excluded_items,
            'business_excluded_items_processed' => $businessExcludedItems,
            'business_excluded_items_count' => count($businessExcludedItems),
            'payer_excluded_items_raw' => $thirdPartyPayer->excluded_items,
            'payer_excluded_items_processed' => $payerExcludedItems,
            'payer_excluded_items_count' => count($payerExcludedItems),
            'effective_excluded_items' => $effectiveExcludedItems,
            'effective_excluded_items_count' => count($effectiveExcludedItems),
            'items_count' => $items->count(),
            'sample_item_ids' => $items->take(5)->pluck('id')->toArray(),
        ]);

        return view('third-party-payer-dashboard.index', compact(
            'thirdPartyPayer',
            'business',
            'calculatedBalance',
            'currentBalance',
            'recentTransactions',
            'accountsReceivable',
            'totalOutstanding',
            'totalDue',
            'totalPaid',
            'items',
            'effectiveCreditLimit',
            'effectiveExcludedItems'
        ));
    }

    /**
     * Display balance statement for third-party payer
     */
    public function balanceStatement()
    {
        $account = Auth::guard('third_party_payer')->user();
        
        if (!$account) {
            return redirect()->route('third-party-payer.login');
        }

        $thirdPartyPayer = $account->thirdPartyPayer;
        
        if (!$thirdPartyPayer) {
            abort(404, 'Third-party payer not found.');
        }

        // Get all balance history records with all relationships
        $balanceHistories = ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
            ->orderBy('created_at', 'desc')
            ->with(['invoice', 'client', 'business', 'branch', 'user'])
            ->paginate(50);

        $balanceSummary = app(AccountBalanceSummaryService::class)->forThirdPartyPayer($thirdPartyPayer);

        $balanceHistories = app(AccountBalanceSummaryService::class)
            ->enrichThirdPartyPayerHistories($balanceHistories);

        return view('third-party-payer-dashboard.balance-statement', compact(
            'thirdPartyPayer',
            'balanceHistories',
            'balanceSummary',
        ));
    }
}
