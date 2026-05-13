<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Invoice;
use Carbon\Carbon;

class CashierDashboardController extends Controller
{
    /**
     * Display the cashier dashboard
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user || !$user->isCashier()) {
            return redirect()->route('cashier.login');
        }

        // Get today's invoices created by this cashier (exclude insurer cascade trace copies)
        $todayInvoices = Invoice::where('created_by', $user->id)
            ->whereNull('parent_invoice_id')
            ->whereDate('created_at', today())
            ->get();

        // Get today's sales total
        $todaySalesTotal = $todayInvoices->sum('total_amount');

        // Get today's transaction count (based on invoices)
        $todayTransactionCount = $todayInvoices->count();

        // Get recent invoices (last 10) created by this cashier
        $recentInvoices = Invoice::where('created_by', $user->id)
            ->whereNull('parent_invoice_id')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->with(['client'])
            ->get();

        return view('cashier-dashboard.index', compact(
            'user',
            'todaySalesTotal',
            'todayTransactionCount',
            'recentInvoices'
        ));
    }
}

