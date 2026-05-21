<?php

namespace App\Http\Controllers;

use App\Services\ThirdPartyApiService;
use App\Services\ThirdPartyPayerStatementPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ThirdPartyVendorsController extends Controller
{
    protected $apiService;

    public function __construct(ThirdPartyApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Display a listing of connected third party vendors
     */
    public function index()
    {
        $business = auth()->user()->business;
        
        if (!$business) {
            return redirect()->route('dashboard')->with('error', 'No business associated with your account.');
        }

        try {
            // Get connected vendors from third-party API
            $baseUrl = config('services.third_party.api_url', env('THIRD_PARTY_API_URL', 'http://127.0.0.1:8001'));
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->get("{$baseUrl}/api/v1/businesses/{$business->id}/connected-vendors");

            $vendors = [];
            
            if ($response->successful()) {
                $data = $response->json();
                $vendors = $data['data'] ?? [];
                
                // Resolve local insurance companies by third-party vendor id
                $insuranceCompanies = \App\Models\InsuranceCompany::where('business_id', $business->id)
                    ->whereNotNull('third_party_business_id')
                    ->get(['id', 'third_party_business_id']);

                $insuranceCompanyIdsByVendorId = [];
                foreach ($insuranceCompanies as $company) {
                    $vendorKey = (string) $company->third_party_business_id;
                    $insuranceCompanyIdsByVendorId[$vendorKey] = $insuranceCompanyIdsByVendorId[$vendorKey] ?? [];
                    $insuranceCompanyIdsByVendorId[$vendorKey][] = (int) $company->id;
                }

                // Load local ThirdPartyPayer records and merge financial summary
                $payers = \App\Models\ThirdPartyPayer::where('business_id', $business->id)
                    ->where('type', 'insurance_company')
                    ->whereNull('client_id')
                    ->get()
                    ->keyBy(fn ($payer) => (int) $payer->insurance_company_id);

                $payerIds = $payers->pluck('id')->all();
                $creditTotalsByPayer = \App\Models\ThirdPartyPayerBalanceHistory::query()
                    ->selectRaw('third_party_payer_id, COALESCE(SUM(change_amount), 0) as total')
                    ->whereIn('third_party_payer_id', $payerIds)
                    ->where('transaction_type', 'credit')
                    ->groupBy('third_party_payer_id')
                    ->pluck('total', 'third_party_payer_id');

                $debitTotalsByPayer = \App\Models\ThirdPartyPayerBalanceHistory::query()
                    ->selectRaw('third_party_payer_id, COALESCE(SUM(change_amount), 0) as total')
                    ->whereIn('third_party_payer_id', $payerIds)
                    ->where('transaction_type', 'debit')
                    ->groupBy('third_party_payer_id')
                    ->pluck('total', 'third_party_payer_id');

                $latestBalanceByPayer = \App\Models\ThirdPartyPayerBalanceHistory::query()
                    ->selectRaw('third_party_payer_id, MAX(id) as latest_id')
                    ->whereIn('third_party_payer_id', $payerIds)
                    ->groupBy('third_party_payer_id')
                    ->pluck('latest_id', 'third_party_payer_id');

                $latestHistories = \App\Models\ThirdPartyPayerBalanceHistory::query()
                    ->whereIn('id', $latestBalanceByPayer->filter()->values())
                    ->get(['id', 'third_party_payer_id', 'new_balance'])
                    ->keyBy('third_party_payer_id');

                // Merge payer status and balances into vendor data
                foreach ($vendors as &$vendor) {
                    $vendorInsuranceCompanyIds = $insuranceCompanyIdsByVendorId[(string) ($vendor['id'] ?? '')] ?? [];
                    $payer = null;
                    foreach ($vendorInsuranceCompanyIds as $companyId) {
                        if (isset($payers[$companyId])) {
                            $payer = $payers[$companyId];
                            break;
                        }
                    }

                    if (!$payer && isset($payers[(int) ($vendor['id'] ?? 0)])) {
                        // Backward-compatible fallback for legacy mappings.
                        $payer = $payers[(int) ($vendor['id'] ?? 0)];
                    }

                    if ($payer) {
                        $creditTotal = (float) ($creditTotalsByPayer[$payer->id] ?? 0);
                        $debitTotal = abs((float) ($debitTotalsByPayer[$payer->id] ?? 0));
                        $currentBalance = $creditTotal - $debitTotal;

                        $vendor['payer_status'] = $payer->status;
                        $vendor['payer_id'] = $payer->id;
                        $vendor['total_balance'] = abs($debitTotal - $creditTotal);
                        $vendor['current_balance'] = $currentBalance;
                    }
                }
                unset($vendor);
            } else {
                Log::warning('Failed to fetch connected vendors', [
                    'business_id' => $business->id,
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
            }

            return view('third-party-vendors.index', compact('vendors', 'business'));
        } catch (\Exception $e) {
            Log::error('Exception while fetching connected vendors', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return view('third-party-vendors.index', [
                'vendors' => [],
                'business' => $business,
                'error' => 'Failed to load connected vendors. Please try again later.',
            ]);
        }
    }

    /**
     * Display detailed balance history for a specific third party vendor
     */
    public function show($vendorId)
    {
        $business = auth()->user()->business;
        
        if (!$business) {
            return redirect()->route('dashboard')->with('error', 'No business associated with your account.');
        }

        try {
            // Get vendor details from third-party API
            $baseUrl = config('services.third_party.api_url', env('THIRD_PARTY_API_URL', 'http://127.0.0.1:8001'));
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->get("{$baseUrl}/api/v1/businesses/{$business->id}/connected-vendors");

            $vendor = null;
            $vendors = [];
            
            if ($response->successful()) {
                $data = $response->json();
                $vendors = $data['data'] ?? [];
                
                // Find the specific vendor
                foreach ($vendors as $v) {
                    if ($v['id'] == $vendorId) {
                        $vendor = $v;
                        break;
                    }
                }
            }

            if (!$vendor) {
                return redirect()->route('third-party-vendors.index')
                    ->with('error', 'Third party vendor not found.');
            }

            // Find the insurance company in Kashtre database scoped to this business
            $insuranceCompany = \App\Models\InsuranceCompany::where('code', $vendor['code'])
                ->where('business_id', $business->id)
                ->first();

            // Find the business-level ThirdPartyPayer for this vendor.
            // Correct approach: resolve local InsuranceCompany IDs via third_party_business_id,
            // then look up the payer by those local IDs (matching how payment flows work).
            $localInsuranceCompanyIds = \App\Models\InsuranceCompany::where('third_party_business_id', $vendorId)
                ->where('business_id', $business->id)
                ->pluck('id');

            $thirdPartyPayer = null;
            if ($localInsuranceCompanyIds->isNotEmpty()) {
                $thirdPartyPayer = \App\Models\ThirdPartyPayer::whereIn('insurance_company_id', $localInsuranceCompanyIds)
                    ->where('business_id', $business->id)
                    ->where('type', 'insurance_company')
                    ->whereNull('client_id')
                    ->first();
            }

            // Fallback: some older records store the third-party vendor ID directly
            if (!$thirdPartyPayer) {
                $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('insurance_company_id', $vendorId)
                    ->where('business_id', $business->id)
                    ->where('type', 'insurance_company')
                    ->whereNull('client_id')
                    ->first();
            }

            $balanceHistories = collect();
            $itemStatementRows = collect();
            $totalCredits = 0;
            $totalDebits = 0;
            $currentBalance = 0;

            if ($thirdPartyPayer) {
                // Get recent balance history (last 10 for summary on details page)
                $balanceHistories = \App\Models\ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
                    ->with(['invoice', 'client', 'business', 'branch', 'user'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                $itemStatementRows = ThirdPartyPayerStatementPresenter::rowsFromHistories($balanceHistories);

                // Calculate totals
                $totalCredits = \App\Models\ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
                    ->where('transaction_type', 'credit')
                    ->sum('change_amount');
                
                $totalDebits = abs(\App\Models\ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
                    ->where('transaction_type', 'debit')
                    ->sum('change_amount'));

                $currentBalance = $totalCredits - $totalDebits;
            }

            return view('third-party-vendors.show', compact(
                'vendor',
                'business',
                'insuranceCompany',
                'thirdPartyPayer',
                'balanceHistories',
                'itemStatementRows',
                'totalCredits',
                'totalDebits',
                'currentBalance'
            ));
        } catch (\Exception $e) {
            Log::error('Exception while fetching vendor details', [
                'vendor_id' => $vendorId,
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('third-party-vendors.index')
                ->with('error', 'Failed to load vendor details. Please try again later.');
        }
    }

    /**
     * Display dedicated balance statement page for a third party vendor
     */
    public function balanceStatement(Request $request, $vendorId)
    {
        $business = auth()->user()->business;
        
        if (!$business) {
            return redirect()->route('dashboard')->with('error', 'No business associated with your account.');
        }

        try {
            // Get vendor details from third-party API
            $baseUrl = config('services.third_party.api_url', env('THIRD_PARTY_API_URL', 'http://127.0.0.1:8001'));
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->get("{$baseUrl}/api/v1/businesses/{$business->id}/connected-vendors");

            $vendor = null;
            
            if ($response->successful()) {
                $data = $response->json();
                $vendors = $data['data'] ?? [];
                
                // Find the specific vendor
                foreach ($vendors as $v) {
                    if ($v['id'] == $vendorId) {
                        $vendor = $v;
                        break;
                    }
                }
            }

            if (!$vendor) {
                return redirect()->route('third-party-vendors.index')
                    ->with('error', 'Third party vendor not found.');
            }

            // Find the business-level ThirdPartyPayer using local InsuranceCompany IDs
            $localInsuranceCompanyIds = \App\Models\InsuranceCompany::where('third_party_business_id', $vendorId)
                ->where('business_id', $business->id)
                ->pluck('id');

            $thirdPartyPayer = null;
            if ($localInsuranceCompanyIds->isNotEmpty()) {
                $thirdPartyPayer = \App\Models\ThirdPartyPayer::whereIn('insurance_company_id', $localInsuranceCompanyIds)
                    ->where('business_id', $business->id)
                    ->where('type', 'insurance_company')
                    ->whereNull('client_id')
                    ->first();
            }
            if (!$thirdPartyPayer) {
                $thirdPartyPayer = \App\Models\ThirdPartyPayer::where('insurance_company_id', $vendorId)
                    ->where('business_id', $business->id)
                    ->where('type', 'insurance_company')
                    ->whereNull('client_id')
                    ->first();
            }

            if (!$thirdPartyPayer) {
                return redirect()->route('third-party-vendors.show', $vendorId)
                    ->with('error', 'No third-party payer account found for this vendor. Balance history will appear here once invoices are created with this vendor.');
            }

            $statementView = $request->query('view', 'items');
            if (! in_array($statementView, ['items', 'transactions'], true)) {
                $statementView = 'items';
            }

            // Calculate totals (table data is loaded via Livewire + Filament)
            $totalCredits = \App\Models\ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
                ->where('transaction_type', 'credit')
                ->sum('change_amount');
            
            $totalDebits = abs(\App\Models\ThirdPartyPayerBalanceHistory::where('third_party_payer_id', $thirdPartyPayer->id)
                ->where('transaction_type', 'debit')
                ->sum('change_amount'));

            return view('third-party-vendors.balance-statement', compact(
                'vendor',
                'business',
                'thirdPartyPayer',
                'statementView',
                'totalCredits',
                'totalDebits'
            ));
        } catch (\Exception $e) {
            Log::error('Exception while fetching vendor balance statement', [
                'vendor_id' => $vendorId,
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('third-party-vendors.index')
                ->with('error', 'Failed to load balance statement. Please try again later.');
        }
    }

    /**
     * Block a vendor.
     */
    public function block(Request $request, $vendorId)
    {
        $business = auth()->user()->business;
        
        if (!$business) {
            return redirect()->route('dashboard')->with('error', 'No business associated with your account.');
        }

        // Find the ThirdPartyPayer record
        $payer = \App\Models\ThirdPartyPayer::where('insurance_company_id', $vendorId)
            ->where('business_id', $business->id)
            ->where('type', 'insurance_company')
            ->whereNull('client_id')
            ->firstOrFail();

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'status' => 'required|in:blocked,suspended',
        ]);

        $statusLabel = $validated['status'] === 'suspended' ? 'Suspended' : 'Blocked';
        
        $payer->block(
            $validated['reason'],
            auth()->id(),
            $validated['status']
        );

        return redirect()
            ->route('third-party-vendors.show', $vendorId)
            ->with('success', "Vendor {$statusLabel} successfully.");
    }

    /**
     * Reactivate a blocked/suspended vendor.
     */
    public function reactivate(Request $request, $vendorId)
    {
        $business = auth()->user()->business;
        
        if (!$business) {
            return redirect()->route('dashboard')->with('error', 'No business associated with your account.');
        }

        // Find the ThirdPartyPayer record
        $payer = \App\Models\ThirdPartyPayer::where('insurance_company_id', $vendorId)
            ->where('business_id', $business->id)
            ->where('type', 'insurance_company')
            ->whereNull('client_id')
            ->firstOrFail();

        if (!$payer->isBlocked() && !$payer->isSuspended()) {
            return redirect()
                ->route('third-party-vendors.show', $vendorId)
                ->with('error', 'Vendor is not blocked or suspended.');
        }

        $payer->reactivate();

        return redirect()
            ->route('third-party-vendors.show', $vendorId)
            ->with('success', 'Vendor reactivated successfully.');
    }

    /**
     * Create a ThirdPartyPayer account for a vendor that doesn't have one
     */
    public function createPayer($vendorId)
    {
        $business = auth()->user()->business;
        
        if (!$business) {
            return redirect()->route('dashboard')->with('error', 'No business associated with your account.');
        }

        try {
            // Get vendor details from third-party API
            $baseUrl = config('services.third_party.api_url', env('THIRD_PARTY_API_URL', 'http://127.0.0.1:8001'));
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->get("{$baseUrl}/api/v1/businesses/{$business->id}/connected-vendors");

            $vendor = null;
            
            if ($response->successful()) {
                $data = $response->json();
                $vendors = $data['data'] ?? [];
                
                // Find the specific vendor
                foreach ($vendors as $v) {
                    if ($v['id'] == $vendorId) {
                        $vendor = $v;
                        break;
                    }
                }
            }

            if (!$vendor) {
                return redirect()->route('third-party-vendors.index')
                    ->with('error', 'Third party vendor not found.');
            }

            // Check if payer account already exists
            $existingPayer = \App\Models\ThirdPartyPayer::where('insurance_company_id', $vendorId)
                ->where('business_id', $business->id)
                ->where('type', 'insurance_company')
                ->whereNull('client_id')
                ->first();

            if ($existingPayer) {
                return redirect()->route('third-party-vendors.show', $vendorId)
                    ->with('info', 'Payer account already exists for this vendor.');
            }

            // Find or create InsuranceCompany record scoped to this business
            $insuranceCompany = \App\Models\InsuranceCompany::where('code', $vendor['code'])
                ->where('business_id', $business->id)
                ->first();

            if (!$insuranceCompany) {
                // Create insurance company record if it doesn't exist
                $insuranceCompany = \App\Models\InsuranceCompany::create([
                    'business_id' => $business->id,
                    'name' => $vendor['name'],
                    'code' => $vendor['code'],
                    'email' => $vendor['email'] ?? null,
                    'phone' => $vendor['phone'] ?? null,
                    'third_party_business_id' => $vendorId,
                ]);

                Log::info('Created insurance company for vendor', [
                    'insurance_company_id' => $insuranceCompany->id,
                    'vendor_id' => $vendorId,
                    'vendor_name' => $vendor['name'],
                ]);
            }

            // Create ThirdPartyPayer account
            $payer = \App\Models\ThirdPartyPayer::create([
                'business_id' => $business->id,
                'type' => 'insurance_company',
                'insurance_company_id' => $insuranceCompany->id,
                'name' => $vendor['name'],
                'email' => $vendor['email'] ?? null,
                'phone_number' => $vendor['phone'] ?? null,
                'status' => 'active',
                'credit_limit' => $business->max_third_party_credit_limit ?? 10000.00,
            ]);

            Log::info('Created third-party payer account', [
                'third_party_payer_id' => $payer->id,
                'vendor_id' => $vendorId,
                'vendor_name' => $vendor['name'],
                'business_id' => $business->id,
            ]);

            return redirect()->route('third-party-vendors.show', $vendorId)
                ->with('success', 'Payer account created successfully for ' . $vendor['name'] . '. Financial data will now be available.');

        } catch (\Exception $e) {
            Log::error('Exception while creating payer account', [
                'vendor_id' => $vendorId,
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('third-party-vendors.show', $vendorId)
                ->with('error', 'Failed to create payer account. Error: ' . $e->getMessage());
        }
    }
}
