<?php

namespace App\Http\Controllers;

use App\Exports\GoodsServicesTemplateExport;
use App\Imports\GoodsServicesTemplateImport;
use App\Imports\TestImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Business;
use App\Models\Group;
use App\Models\Department;
use App\Support\SharedUnits;
use App\Models\ServicePoint;
use App\Models\ContractorProfile;

class ItemBulkUploadController extends Controller
{
    /**
     * Show the bulk upload form for goods and services only
     */
    public function index()
    {
        // Get businesses based on user permissions
        if (Auth::user()->business_id == 1) {
            $businesses = Business::where('id', '!=', 1)->get();
        } else {
            $businesses = Business::where('id', Auth::user()->business_id)->get();
        }

        return view('items.bulk-upload', compact('businesses'));
    }

    /**
     * Download the template for goods and services with dropdown data
     */
    public function downloadTemplate(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
        ]);

        // Validate business access
        if (Auth::user()->business_id != 1 && Auth::user()->business_id != $request->business_id) {
            return redirect()->back()->with('error', 'Unauthorized access to business data');
        }

        try {
            $business = Business::find($request->business_id);
            $filename = 'goods_services_template_' . str_replace(' ', '_', $business->name) . '.xlsx';
            
            // Log for debugging
            Log::info('Downloading goods/services template for business: ' . $business->name);
            
            return Excel::download(new GoodsServicesTemplateExport($request->business_id), $filename);
            
        } catch (\Exception $e) {
            Log::error('Error downloading template: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error downloading template: ' . $e->getMessage());
        }
    }

    /**
     * Import the uploaded file for goods and services
     */
    public function import(Request $request)
    {
        Log::info("=== GOODS & SERVICES IMPORT REQUEST STARTED ===");
        Log::info("User: " . (Auth::user()->name ?? 'Unknown') . " (ID: " . (Auth::user()->id ?? 'N/A') . ")");
        Log::info("Business ID: " . ($request->business_id ?? 'Not provided'));
        Log::info("File: " . ($request->file('file') ? $request->file('file')->getClientOriginalName() : 'No file'));
        Log::info("File Size: " . ($request->file('file') ? $request->file('file')->getSize() . ' bytes' : 'N/A'));
        Log::info("File MIME: " . ($request->file('file') ? $request->file('file')->getMimeType() : 'N/A'));
        Log::info("Request Method: " . $request->method());
        Log::info("Request IP: " . $request->ip());
        Log::info("Timestamp: " . now()->toDateTimeString());
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            'business_id' => 'required|exists:businesses,id',
        ]);

        // Validate business access
        if (Auth::user()->business_id != 1 && Auth::user()->business_id != $request->business_id) {
            Log::error("Unauthorized access attempt - User business ID: " . Auth::user()->business_id . ", Requested business ID: " . $request->business_id);
            return redirect()->back()->with('error', 'Unauthorized access to business data');
        }

        try {
            Log::info("Starting Excel import process...");
            $import = new GoodsServicesTemplateImport($request->business_id);
            
            Log::info("Executing Excel::import()...");
            Excel::import($import, $request->file('file'));
            Log::info("Excel import completed successfully");

            Log::info("Creating branch prices...");
            $import->createBranchPrices();
            Log::info("Branch prices creation completed");

            Log::info("Creating branch service points...");
            $import->createBranchServicePoints();
            Log::info("Branch service points creation completed");

            $successCount = $import->getSuccessCount();
            $errorCount = $import->getErrorCount();
            $errors = $import->getErrors();

            Log::info("=== IMPORT SUMMARY ===");
            Log::info("Successfully imported: {$successCount} items");
            Log::info("Errors encountered: {$errorCount} items");
            Log::info("Total errors: " . count($errors));

            $message = "Import completed. Successfully imported {$successCount} goods/services.";
            
            if ($errorCount > 0) {
                $message .= " {$errorCount} records had errors.";
                Log::warning("Import completed with errors: " . implode('; ', $errors));
            }

            if (!empty($errors)) {
                Log::info("Redirecting back with errors");
                return redirect()->back()
                    ->with('success', $message)
                    ->with('import_errors', $errors);
            }

            Log::info("Redirecting to items index with success message");
            return redirect()->route('items.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error("=== IMPORT FAILED ===");
            Log::error("Error message: " . $e->getMessage());
            Log::error("Error trace: " . $e->getTraceAsString());
            Log::error("File: " . ($request->file('file') ? $request->file('file')->getClientOriginalName() : 'No file'));
            Log::error("Business ID: " . $request->business_id);
            
            return redirect()->back()
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Get filtered data for a specific business (AJAX)
     */
    public function getFilteredData(Request $request)
    {
        $businessId = $request->input('business_id');
        
        if (!$businessId) {
            return response()->json([]);
        }

        // Check permissions
        if (Auth::user()->business_id != 1 && Auth::user()->business_id != $businessId) {
            return response()->json([]);
        }

        $data = [
            'groups' => Group::where('business_id', $businessId)->get(),
            'departments' => Department::where('business_id', $businessId)->get(),
            'itemUnits' => SharedUnits::itemUnitsForBusiness((int) $businessId),
            'servicePoints' => ServicePoint::where('business_id', $businessId)->get(),
            'contractors' => ContractorProfile::with('business')->where('business_id', $businessId)->get(),
        ];

        return response()->json($data);
    }
} 