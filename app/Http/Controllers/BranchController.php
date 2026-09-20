<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Business;
use App\Mail\BranchCreatedMail;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BranchTemplateExport;
use App\Imports\BranchTemplateImport;
use App\Support\SharedTime;

class BranchController extends Controller
{
    /**
     * Display a listing of the branches for the current user's business.
     */
    public function index()
    {
        try {
            $business = Business::all(); 
            // $branches = Branch::where('business_id', Auth::user()->business_id)->get();
            return view('branches.index', compact('business'));
        } catch (\Exception $e) {
            Log::error('Branch index error: ' . $e->getMessage());
            return back()->with('error', 'Failed to load branches.');
        }
    }

    /**
     * Show the form for creating a new branch.
     */
    public function create()
    {
        return view('branches.create');
    }

    /**
     * Store a newly created branch in storage.
     */
    public function store(Request $request)
{
    $request->validate([
        'name'    => 'required|string|max:255',
        'email'   => 'required|email|max:255',
        'phone'   => 'required|string|max:20',
        'address' => 'required|string|max:255',
        'business_id' => 'required|exists:businesses,id',
        'timezone' => SharedTime::timezoneValidationRule(required: false, allowInherit: true),
    ]);

    try {
        $branch = Branch::create([
            'uuid'        => Str::uuid(),
            'business_id' => $request->business_id,
            'name'        => $request->name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'address'     => $request->address,
        ]);

        $company = Business::find($request->business_id);

        try {
            SharedTime::assignBranchTimezone(
                $branch,
                $request->input('timezone'),
                $request->filled('timezone')
                    ? 'Set as a branch override when creating the branch'
                    : 'Inherits the business timezone',
            );
        } catch (\Throwable $e) {
            Log::warning('Branch timezone was not applied: '.$e->getMessage(), [
                'branch_id' => $branch->id,
                'timezone' => $request->input('timezone'),
            ]);
        }

        Mail::send(new BranchCreatedMail($branch, $company));

        return redirect()->route('branches.index')->with('success', 'Branch created successfully.');
    } catch (\Exception $e) {
        Log::error('Branch store error: ' . $e->getMessage());
        return back()->with('error', 'Failed to create branch.')->withInput();
    }
}

    /**
     * Display the specified branch.
     */
    public function show(Branch $branch)
    {
        try {
            if ($branch->business_id !== Auth::user()->business_id) {
                return abort(403);
            }

            return view('branches.show', compact('branch'));
        } catch (\Exception $e) {
            Log::error('Branch show error: ' . $e->getMessage());
            return back()->with('error', 'Failed to load branch.');
        }
    }

    /**
     * Select a branch for the current user session
     */
    public function selectBranch(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $user = Auth::user();
        $allowedBranches = (array) ($user->allowed_branches ?? []);
        
        // Check if the user has permission to access this branch
        if (!in_array($request->branch_id, $allowedBranches)) {
            return response()->json(['error' => 'You do not have permission to access this branch.'], 403);
        }

        session(['current_branch_id' => $request->branch_id]);

        return response()->json(['message' => 'Branch selected successfully.']);
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(Branch $branch)
    {
        try {
            if ($branch->business_id !== Auth::user()->business_id) {
                return abort(403);
            }

            return view('branches.edit', compact('branch'));
        } catch (\Exception $e) {
            Log::error('Branch edit error: ' . $e->getMessage());
            return back()->with('error', 'Failed to load edit form.');
        }
    }

    /**
     * Update the specified branch in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'phone'   => 'required|string|max:20',
            'address' => 'required|string|max:255',
        ]);

        try {
            if ($branch->business_id !== Auth::user()->business_id) {
                return abort(403);
            }

            $branch->update([
                'name'    => $request->name,
                'email'   => $request->email,
                'phone'   => $request->phone,
                'address' => $request->address,
            ]);

            return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
        } catch (\Exception $e) {
            Log::error('Branch update error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update branch.')->withInput();
        }
    }

    /**
     * Remove the specified branch from storage.
     */
    public function destroy(Branch $branch)
    {
        try {
            if ($branch->business_id !== Auth::user()->business_id) {
                return abort(403);
            }

            $branch->delete();

            return redirect()->route('branches.index')->with('success', 'Branch deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Branch destroy error: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete branch.');
        }
    }

    /**
     * Download branch template for bulk upload
     */
    public function downloadTemplate()
    {
        // Check if user has permission (only business ID 1 can download templates)
        if (Auth::user()->business_id !== 1) {
            return redirect()->back()->with('error', 'You do not have permission to download branch templates.');
        }

        return Excel::download(new BranchTemplateExport(), 'branch_template.xlsx');
    }

    /**
     * Handle bulk upload of branch data
     */
    public function bulkUpload(Request $request)
    {
        // Check if user has permission (only business ID 1 can upload branches)
        if (Auth::user()->business_id !== 1) {
            return redirect()->back()->with('error', 'You do not have permission to upload branches.');
        }

        $validated = $request->validate([
            'template' => 'required|file|mimes:xlsx,xls',
            'business_id' => 'required|exists:businesses,id',
        ]);

        try {
            // Import the data
            Excel::import(new BranchTemplateImport($validated['business_id']), $request->file('template'));

            return redirect()->route('branches.index')->with('success', 'Branch data uploaded and processed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred during import: ' . $e->getMessage());
        }
    }
}
