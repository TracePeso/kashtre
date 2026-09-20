<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\SharedTime;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyVisitsController extends Controller
{
    /**
     * Display visits record
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Check if user has permission to view visits
        if (!in_array('View Visits', $user->permissions ?? [])) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to view visits.');
        }
        
        $business = $user->business;
        $currentBranch = $user->current_branch;
        
        // Check if current branch exists
        if (!$currentBranch) {
            return redirect()->route('dashboard')->with('error', 'No branch assigned. Please contact administrator.');
        }
        
        // Get the requested branch or use current branch
        $selectedBranchId = $request->get('branch_id', $currentBranch->id);
        
        // Check if user has access to the selected branch
        $allowedBranches = (array) ($user->allowed_branches ?? []);
        if (!in_array($selectedBranchId, $allowedBranches)) {
            $selectedBranchId = $currentBranch->id;
        }
        
        $selectedBranch = \App\Models\Branch::find($selectedBranchId) ?? $currentBranch;
        
        // Date filter (default to today if not specified)
        $selectedDate = $request->get('date', SharedTime::businessToday());
        
        // For Kashtre (business_id == 1), show all clients registered on selected date
        if ($business->id == 1) {
            $dailyVisits = Client::where('business_id', '!=', 1)
                ->whereDate('created_at', $selectedDate)
                ->with(['business', 'branch'])
                ->orderBy('created_at', 'desc')
                ->get();

            $totalVisits = Client::where('business_id', '!=', 1)
                ->whereDate('created_at', $selectedDate)
                ->count();

            $uniqueClients = $totalVisits; // same as total when listing clients

            $totalRevenue = 0;
            $totalPaid = 0;
        } else {
            // Get clients registered for the selected business and branch
            $dailyVisits = Client::where('business_id', $business->id)
                ->where('branch_id', $selectedBranch->id)
                ->whereDate('created_at', $selectedDate)
                ->with(['branch'])
                ->orderBy('created_at', 'desc')
                ->get();

            $totalVisits = Client::where('business_id', $business->id)
                ->where('branch_id', $selectedBranch->id)
                ->whereDate('created_at', $selectedDate)
                ->count();

            $uniqueClients = $totalVisits;
            $totalRevenue = 0;
            $totalPaid = 0;
        }
        
        // Get all branches the user has access to for the filter
        $availableBranches = \App\Models\Branch::whereIn('id', $allowedBranches)->get();
        
        // Get date range for calendar picker (last 30 days and next 7 days)
        $minDate = now()->subDays(30)->format('Y-m-d');
        $maxDate = now()->addDays(7)->format('Y-m-d');
        
        return view('daily-visits.index', compact(
            'dailyVisits',
            'totalVisits',
            'uniqueClients',
            'totalRevenue',
            'totalPaid',
            'business',
            'currentBranch',
            'selectedBranch',
            'availableBranches',
            'selectedDate',
            'minDate',
            'maxDate'
        ));
    }
}
