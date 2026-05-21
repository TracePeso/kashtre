<?php

namespace App\Http\Controllers;

use App\Models\BusinessBalanceHistory;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessBalanceHistoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Get business account IDs for filtering (only show business_account type, not suspense accounts)
        $businessAccountIds = \App\Models\MoneyAccount::where('type', 'business_account')
            ->when($user->business_id != 1, function($query) use ($user) {
                return $query->where('business_id', $user->business_id);
            })
            ->pluck('id');
        
        // For super business (Kashtre), show all businesses
        if ($user->business_id == 1) {
            $businesses = Business::all();
            $businessBalanceHistories = BusinessBalanceHistory::with('business')
                ->whereIn('money_account_id', $businessAccountIds)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } else {
            // For regular businesses, show only their own history
            $businesses = Business::where('id', $user->business_id)->get();
            $businessBalanceHistories = BusinessBalanceHistory::with('business')
                ->where('business_id', $user->business_id)
                ->whereIn('money_account_id', $businessAccountIds)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }

        // Use centralized method to calculate available balance (source of truth)
        $moneyTrackingService = new \App\Services\MoneyTrackingService();
        
        if ($user->business_id == 1) {
            // For Kashtre (super business), we need to calculate for all businesses
            // Credits: include matured insurance / service-fee pendings via effective status
            $totalCredits = (float) BusinessBalanceHistory::whereIn('money_account_id', $businessAccountIds)
                ->where('type', 'credit')
                ->get()
                ->filter(fn (BusinessBalanceHistory $h): bool => $h->effectiveCreditPaymentStatus() === 'paid')
                ->sum('amount');
            
            $totalDebits = BusinessBalanceHistory::whereIn('money_account_id', $businessAccountIds)
                ->where('type', 'debit')
                ->sum('amount');

            // For Kashtre, calculate withdrawal suspense from all withdrawal suspense accounts
            $withdrawalSuspenseAccountIds = \App\Models\MoneyAccount::where('type', 'withdrawal_suspense_account')
                ->pluck('id');
            
            $withdrawalSuspenseBalance = 0;
            if ($withdrawalSuspenseAccountIds->isNotEmpty()) {
                $suspenseCredits = BusinessBalanceHistory::whereIn('money_account_id', $withdrawalSuspenseAccountIds)
                    ->where('type', 'credit')
                    ->sum('amount');
                
                $suspenseDebits = BusinessBalanceHistory::whereIn('money_account_id', $withdrawalSuspenseAccountIds)
                    ->where('type', 'debit')
                    ->sum('amount');
                
                $withdrawalSuspenseBalance = $suspenseCredits - $suspenseDebits;
            }
        } else {
            // For regular businesses, use centralized calculation method (source of truth)
            $balanceData = $moneyTrackingService->calculateBusinessAvailableBalance($user->business);
            $totalCredits = $balanceData['totalCredits'];
            $totalDebits = $balanceData['totalDebits'];
            $withdrawalSuspenseBalance = $balanceData['withdrawalSuspenseBalance'];
            $availableBalance = $balanceData['availableBalance'];
        }

        // Pending credits: DB pending_payment minus rows whose maturation date has passed (effective still pending)
        $pendingPaymentsBaseQuery = BusinessBalanceHistory::with(['business'])
            ->whereIn('money_account_id', $businessAccountIds)
            ->when($user->business_id != 1, function($query) use ($user) {
                return $query->where('business_id', $user->business_id);
            })
            ->where('payment_status', 'pending_payment')
            ->where('type', 'credit')
            ->orderBy('created_at', 'desc');

        $pendingPaymentsList = $pendingPaymentsBaseQuery->get()
            ->filter(fn (BusinessBalanceHistory $h): bool => $h->effectiveCreditPaymentStatus() === 'pending_payment')
            ->values();

        $pendingPaymentsTotal = (float) $pendingPaymentsList->sum('amount');

        // Total balance = gross business-account position (effective-paid credits + still-pending credits − debits)
        $totalBalance = (float) (($totalCredits ?? 0) + $pendingPaymentsTotal - ($totalDebits ?? 0));

        // Kashtre: available stays withdrawable slice only (effective-paid − debits − withdrawal suspense)
        if ($user->business_id == 1) {
            $availableBalance = ($totalCredits - $totalDebits) - $withdrawalSuspenseBalance;
        }

        // Fetch pending maturity (AccountsReceivable with future due_date)
        $pendingMaturityQuery = \App\Models\AccountsReceivable::with(['client', 'business', 'invoice', 'thirdPartyPayer'])
            ->where('due_date', '>', now()->toDateString())
            ->where('balance', '>', 0); // Only unpaid or partially paid

        if ($user->business_id == 1) {
            // For Kashtre, show all businesses
            $pendingMaturityQuery->where('business_id', '>', 0);
        } else {
            // For regular businesses, show only their own
            $pendingMaturityQuery->where('business_id', $user->business_id);
        }

        $pendingMaturityQuery->orderBy('due_date', 'asc');

        $pendingMaturityList = $pendingMaturityQuery->get()->map(function($ar) {
            // Calculate outstanding days to maturity (days remaining until due date)
            $dueDate = \Carbon\Carbon::parse($ar->due_date);
            $today = \Carbon\Carbon::today();
            $ar->outstanding_days_to_maturity = max(0, $today->diffInDays($dueDate, false));
            return $ar;
        });

        $pendingMaturityTotal = $pendingMaturityList->sum('balance');

        return view('business-balance-statement.index', compact(
            'businessBalanceHistories', 
            'businesses', 
            'totalCredits', 
            'totalDebits', 
            'totalBalance', 
            'withdrawalSuspenseBalance', 
            'availableBalance', 
            'pendingPaymentsTotal',
            'pendingPaymentsList',
            'pendingMaturityTotal',
            'pendingMaturityList'
        ))
            ->with('canUserCreateWithdrawal', function($user) {
                return $this->canUserCreateWithdrawal($user);
            });
    }

    public function show(Business $business)
    {
        $user = Auth::user();
        
        // Check if user has access to this business
        if ($user->business_id != 1 && $user->business_id != $business->id) {
            abort(403, 'Unauthorized access to business balance statement.');
        }

        // Get business account IDs for filtering (only show business_account type, not suspense accounts)
        $businessAccountIds = \App\Models\MoneyAccount::where('business_id', $business->id)
            ->where('type', 'business_account')
            ->pluck('id');

        $businessBalanceHistories = BusinessBalanceHistory::where('business_id', $business->id)
            ->whereIn('money_account_id', $businessAccountIds)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('business-balance-statement.show', compact('businessBalanceHistories', 'business'));
    }

    /**
     * Show Kashtre (super business) balance statement
     */
    public function kashtreStatement()
    {
        $user = Auth::user();
        
        // Only super business users can access Kashtre statement
        if ($user->business_id != 1) {
            abort(403, 'Unauthorized access to Kashtre balance statement.');
        }

        $kashtreBalanceHistories = BusinessBalanceHistory::where('business_id', 1)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Credits split by effective status (includes matured service-fee pendings as available)
        $totalCredits = BusinessBalanceHistory::sumKashtreCreditAvailableAmount();
        $pendingPayments = BusinessBalanceHistory::sumKashtreCreditPendingAmount();

        $totalDebits = BusinessBalanceHistory::where('business_id', 1)
            ->where('type', 'debit')
            ->sum('amount');

        return view('kashtre-balance-statement.index', compact('kashtreBalanceHistories', 'totalCredits', 'totalDebits', 'pendingPayments'));
    }

    /**
     * Show detailed Kashtre balance statement
     */
    public function kashtreStatementShow()
    {
        $user = Auth::user();
        
        // Only super business users can access Kashtre statement
        if ($user->business_id != 1) {
            abort(403, 'Unauthorized access to Kashtre balance statement.');
        }

        $kashtreBalanceHistories = BusinessBalanceHistory::where('business_id', 1)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $totalCredits = BusinessBalanceHistory::sumKashtreCreditAvailableAmount();
        $pendingPayments = BusinessBalanceHistory::sumKashtreCreditPendingAmount();

        $totalDebits = BusinessBalanceHistory::where('business_id', 1)
            ->where('type', 'debit')
            ->sum('amount');

        return view('kashtre-balance-statement.show', compact('kashtreBalanceHistories', 'totalCredits', 'totalDebits', 'pendingPayments'));
    }

    /**
     * Check if a user can create withdrawal requests
     */
    private function canUserCreateWithdrawal($user)
    {
        // Check if user has withdrawal settings configured for their business
        $withdrawalSetting = \App\Models\WithdrawalSetting::where('business_id', $user->business_id)
            ->where('is_active', true)
            ->first();

        if (!$withdrawalSetting) {
            return false;
        }

        // Check if user is an initiator for this business
        $isInitiator = \App\Models\WithdrawalSettingApprover::where('withdrawal_setting_id', $withdrawalSetting->id)
            ->where('approver_id', $user->id)
            ->where('approver_type', 'user')
            ->where('approver_level', 'business')
            ->where('approval_level', 'initiator')
            ->exists();

        return $isInitiator;
    }
}

