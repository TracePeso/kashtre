<?php

namespace App\Http\Controllers;

use App\Models\VisitArchive;
use App\Support\SharedTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VisitArchivesController extends Controller
{
    public function index(Request $request, string $recordType)
    {
        $user = Auth::user();

        // Reuse the same permission gate as Daily Visits.
        if (!$user || !in_array('View Visits', $user->permissions ?? [])) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to view visits.');
        }

        if (!in_array($recordType, ['snapshot', 'previous'], true)) {
            abort(404);
        }

        $business = $user->business;
        $currentBranch = $user->current_branch;

        if (!$currentBranch) {
            return redirect()->route('dashboard')->with('error', 'No branch assigned. Please contact administrator.');
        }

        // Branch filter (optional override).
        $selectedBranchId = (int) $request->get('branch_id', $currentBranch->id);
        $allowedBranches = (array) ($user->allowed_branches ?? []);
        if (!empty($allowedBranches) && $business->id !== 1 && !in_array($selectedBranchId, $allowedBranches, true)) {
            $selectedBranchId = (int) $currentBranch->id;
        }

        $selectedDate = $request->get('date', SharedTime::businessToday());

        return view('visits.archives.index', [
            'recordType' => $recordType,
            'business' => $business,
            'currentBranch' => $currentBranch,
            'selectedBranchId' => $selectedBranchId,
            'selectedDate' => $selectedDate,
        ]);
    }
}

