<?php

namespace App\Http\Controllers;

use App\Models\AccountsReceivable;
use App\Models\Business;
use App\Services\AccountsReceivableStatementPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountsReceivableController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $statementView = $request->query('view', 'items');
        if (! in_array($statementView, ['items', 'transactions'], true)) {
            $statementView = 'items';
        }
        
        // Check permission
        if (!in_array('View Accounts Receivable', $user->permissions ?? [])) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to view accounts receivable.');
        }
        
        // For Kashtre (business_id == 1), show all accounts receivable
        // For regular businesses, show only their own
        if ($user->business_id == 1) {
            $accountsReceivable = AccountsReceivable::with(['client', 'business', 'branch', 'invoice'])
                ->where('status', '!=', 'paid')
                ->orderBy('invoice_date', 'desc')
                ->orderBy('due_date', 'asc')
                ->paginate(50)
                ->withQueryString();
            
            $businesses = Business::all();
        } else {
            $accountsReceivable = AccountsReceivable::with(['client', 'business', 'branch', 'invoice'])
                ->where('business_id', $user->business_id)
                ->where('status', '!=', 'paid')
                ->orderBy('invoice_date', 'desc')
                ->orderBy('due_date', 'asc')
                ->paginate(50)
                ->withQueryString();
            
            $businesses = Business::where('id', $user->business_id)->get();
        }

        $arItemLines = $statementView === 'items'
            ? AccountsReceivableStatementPresenter::flattenedLines(collect($accountsReceivable->items()))
            : collect();
        
        // Calculate summary statistics
        $totalOutstanding = AccountsReceivable::where('status', '!=', 'paid')
            ->when($user->business_id != 1, function($query) use ($user) {
                return $query->where('business_id', $user->business_id);
            })
            ->sum('balance');
        
        $totalCurrent = AccountsReceivable::where('status', 'current')
            ->when($user->business_id != 1, function($query) use ($user) {
                return $query->where('business_id', $user->business_id);
            })
            ->sum('balance');
        
        $totalOverdue = AccountsReceivable::where('status', 'overdue')
            ->when($user->business_id != 1, function($query) use ($user) {
                return $query->where('business_id', $user->business_id);
            })
            ->sum('balance');
        
        // Aging summary
        $agingCurrent = AccountsReceivable::where('aging_bucket', 'current')
            ->where('status', '!=', 'paid')
            ->when($user->business_id != 1, function($query) use ($user) {
                return $query->where('business_id', $user->business_id);
            })
            ->sum('balance');
        
        $aging30_60 = AccountsReceivable::where('aging_bucket', 'days_30_60')
            ->where('status', '!=', 'paid')
            ->when($user->business_id != 1, function($query) use ($user) {
                return $query->where('business_id', $user->business_id);
            })
            ->sum('balance');
        
        $aging60_90 = AccountsReceivable::where('aging_bucket', 'days_60_90')
            ->where('status', '!=', 'paid')
            ->when($user->business_id != 1, function($query) use ($user) {
                return $query->where('business_id', $user->business_id);
            })
            ->sum('balance');
        
        $agingOver90 = AccountsReceivable::where('aging_bucket', 'over_90')
            ->where('status', '!=', 'paid')
            ->when($user->business_id != 1, function($query) use ($user) {
                return $query->where('business_id', $user->business_id);
            })
            ->sum('balance');
        
        return view('accounts-receivable.index', compact(
            'accountsReceivable',
            'arItemLines',
            'statementView',
            'businesses',
            'totalOutstanding',
            'totalCurrent',
            'totalOverdue',
            'agingCurrent',
            'aging30_60',
            'aging60_90',
            'agingOver90'
        ));
    }
}
