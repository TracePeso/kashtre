<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesBusinessBranding;
use App\Models\Business;
use App\Models\Country;
use App\Support\BusinessBranding;
use App\Support\SupplierCategorySelection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Mail\NewBusinessCreatedMail;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BusinessTemplateExport;
use App\Imports\BusinessTemplateImport;
use Illuminate\Support\Facades\Auth;

class BusinessController extends Controller
{
    use HandlesBusinessBranding;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('businesses.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(array_merge(
            BusinessBranding::validationRules(logoRequired: true),
            [
                'country_id' => 'required|exists:countries,id',
                'financial_year_start_month' => 'required|integer|min:1|max:12',
                'financial_year_start_day' => 'required|integer|min:1|max:31',
                'register_as_supplier' => 'sometimes|boolean',
                'supplier_industry_id' => 'nullable|exists:supplier_industries,id',
                'supplier_sub_category_id' => 'nullable|exists:supplier_sub_categories,id',
            ]
        ));

        try {
            // Country is required: derive currency from selected country.
            $country = Country::with('currency')->findOrFail($validated['country_id']);
            $validated['currency_code'] = $country->currency_code ?? $country->currency?->code ?? 'USD';
            $validated['country_id'] = $country->id;

            $business = new Business($validated);
            $validated = $this->applyBrandingFromRequest($request, $business, $validated, logoRequired: true);

            // Generate time-based account number with prefix '25' and random 2-digit suffix
            $validated['account_number'] = 'KS' . time();
            $registeredAsSupplier = $request->boolean('register_as_supplier');
            $validated['registered_as_supplier'] = $registeredAsSupplier;
            $validated = SupplierCategorySelection::normalize($registeredAsSupplier, $validated);

            // Create business
            $business = Business::create($validated);
            $this->moveIncomingLogoToBusinessDirectory($business);

            // dd($business->email);

            // Send welcome email
            // Mail::to($business->email)->send(new BusinessCreatedMail($business));
            Mail::to($business->email)->send(new NewBusinessCreatedMail($business));


            Log::info('BusinessCreatedMail details:', [
                'name' => $business->name,
                'phone' => $business->phone,
                'email' => $business->email,
                'account_number' => $business->account_number,
            ]);

            return redirect()->back()->with('success', 'Business created successfully!');

        // } catch (\Illuminate\Database\QueryException $e) {
            // if ($e->getCode() == 23000) { // Unique constraint violation
            //     return redirect()->back()->with('error', 'Account number already exists. Please try again.');
            // }

            // Log::error('DB error while creating business: ' . $e->getMessage());
            // return redirect()->back()->with('error', 'A database error occurred. Please contact support.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            dd($e);
            Log::error('General error while creating business: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An unexpected error occurred. Please try again.');
        }
    }



    /**
     * Display the specified resource.
     */
    public function show(Business $business)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Business $business)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Business $business)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Business $business)
    {
        //
    }

    /**
     * Download business template for bulk upload
     */
    public function downloadTemplate()
    {
        // Check if user has permission (only business ID 1 can download templates)
        if (Auth::user()->business_id !== 1) {
            return redirect()->back()->with('error', 'You do not have permission to download business templates.');
        }

        return Excel::download(new BusinessTemplateExport(), 'business_template.xlsx');
    }

    /**
     * Handle bulk upload of business data
     */
    public function bulkUpload(Request $request)
    {
        // Check if user has permission (only business ID 1 can upload businesses)
        if (Auth::user()->business_id !== 1) {
            return redirect()->back()->with('error', 'You do not have permission to upload businesses.');
        }

        $validated = $request->validate([
            'template' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            // Import the data
            Excel::import(new BusinessTemplateImport(), $request->file('template'));

            return redirect()->route('businesses.index')->with('success', 'Business data uploaded and processed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred during import: ' . $e->getMessage());
        }
    }
}
