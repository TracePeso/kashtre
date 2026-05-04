<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Business;
use App\Models\Branch;
use App\Models\MaturationPeriod;
use App\Models\InsuranceCompany;
use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerAccount;
use App\Models\ConnectedAccount;
use App\Services\ThirdPartyApiService;
use App\Services\MultiVendorClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
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
        
        $selectedBranch = Branch::find($selectedBranchId) ?? $currentBranch;
        
        // For Kashtre (business_id == 1), show all clients from all businesses
        if ($business->id == 1) {
            $clients = Client::where('business_id', '!=', 1)
                ->with(['business', 'branch'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
                
            // Get today's clients count for all businesses
            $todayClients = Client::where('business_id', '!=', 1)
                ->whereDate('created_at', today())
                ->count();
        } else {
            // Get clients for the selected business and branch
            $clients = Client::where('business_id', $business->id)
                ->where('branch_id', $selectedBranch->id)
                ->orderBy('created_at', 'desc')
                ->paginate(15);
                
            // Get today's clients count for the selected branch
            $todayClients = Client::where('business_id', $business->id)
                ->where('branch_id', $selectedBranch->id)
                ->whereDate('created_at', today())
                ->count();
        }
            
        // Get all branches the user has access to for the filter
        $availableBranches = Branch::whereIn('id', $allowedBranches)->get();
            
        return view('clients.index', compact('clients', 'todayClients', 'business', 'currentBranch', 'selectedBranch', 'availableBranches'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        $business = $user->business;
        $currentBranch = $user->current_branch;
        
        // Check if current branch exists
        if (!$currentBranch) {
            return redirect()->route('dashboard')->with('error', 'No branch assigned. Please contact administrator.');
        }
            
            // Get available payment methods from maturation periods for this business
            $availablePaymentMethods = MaturationPeriod::where('business_id', $business->id)
                ->where('is_active', true)
                ->get()
                ->pluck('payment_method')
                ->unique()
                ->values()
                ->toArray();
            
            // Define the order for payment methods
            $paymentMethodOrder = [
                'insurance' => 1,
                'mobile_money' => 2,
                'v_card' => 3,
                'p_card' => 4,
                'bank_transfer' => 5,
                'cash' => 6,
            ];
            
            // Sort payment methods according to the defined order
            // Methods not in the order list will come after (with higher priority number)
            usort($availablePaymentMethods, function ($a, $b) use ($paymentMethodOrder) {
                $orderA = $paymentMethodOrder[$a] ?? 999;
                $orderB = $paymentMethodOrder[$b] ?? 999;
                
                if ($orderA === $orderB) {
                    // If both have the same priority (or both not in list), maintain original order
                    return 0;
                }
                
                return $orderA <=> $orderB;
            });
            
            // Payment method display names
            $paymentMethodNames = [
                'insurance' => '🛡️ Insurance',
                'credit_arrangement' => '💳 Credit Arrangement',
                'mobile_money' => '📱 MM (Mobile Money)',
                'v_card' => '💳 V Card (Virtual Card)',
                'p_card' => '💳 P Card (Physical Card)',
                'bank_transfer' => '🏦 Bank Transfer',
                'cash' => '💵 Cash',
            ];

        // Get connected third-party vendors for this business
        $connectedVendors = [];
        $insuranceCompanies = [];
        
        try {
            $apiService = new ThirdPartyApiService();
            $baseUrl = config('services.third_party.api_url', env('THIRD_PARTY_API_URL', 'http://127.0.0.1:8001'));
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->get("{$baseUrl}/api/v1/businesses/{$business->id}/connected-vendors");

            if ($response->successful()) {
                $data = $response->json();
                $connectedVendors = $data['data'] ?? [];

                // Load suspended/blocked payers for this business to filter them out
                $suspendedPayerIds = \App\Models\ThirdPartyPayer::where('business_id', $business->id)
                    ->where('type', 'insurance_company')
                    ->whereNull('client_id')
                    ->whereIn('status', ['suspended', 'blocked'])
                    ->pluck('insurance_company_id')
                    ->map(fn($id) => (string) $id)
                    ->toArray();

                // Map third-party vendors to local insurance companies
                // Find or create local insurance company records for each connected vendor
                foreach ($connectedVendors as $vendor) {
                    $thirdPartyBusinessId = $vendor['id'] ?? null;
                    if (!$thirdPartyBusinessId) {
                        continue;
                    }

                    // Skip suspended or blocked vendors
                    if (in_array((string) $thirdPartyBusinessId, $suspendedPayerIds)) {
                        continue;
                    }

                    // Try to find existing local insurance company by third_party_business_id
                    $insuranceCompany = InsuranceCompany::where('third_party_business_id', $thirdPartyBusinessId)
                        ->where(function($query) use ($business) {
                            $query->where('business_id', $business->id)
                                  ->orWhereNull('business_id');
                        })
                        ->first();

                    // If not found, create a new local insurance company record
                    if (!$insuranceCompany) {
                        $insuranceCompany = InsuranceCompany::create([
                            'business_id' => $business->id,
                            'name' => $vendor['name'] ?? 'Unknown Insurance Company',
                            'code' => $vendor['code'] ?? null,
                            'email' => $vendor['email'] ?? null,
                            'phone' => $vendor['phone'] ?? null,
                            'third_party_business_id' => (string)$thirdPartyBusinessId,
                        ]);

                        Log::info('Created local insurance company for connected vendor', [
                            'insurance_company_id' => $insuranceCompany->id,
                            'third_party_business_id' => $thirdPartyBusinessId,
                            'name' => $vendor['name'] ?? null,
                        ]);
                    }

                    // Add to list with local ID
                    $insuranceCompanies[] = [
                        'id' => $insuranceCompany->id, // Local insurance company ID
                        'name' => $insuranceCompany->name,
                        'code' => $insuranceCompany->code ?? $vendor['code'] ?? null,
                        'third_party_id' => $thirdPartyBusinessId, // Keep for reference
                    ];
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch connected vendors for client creation', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);
        }

        $countries = \App\Services\CountriesService::getCountriesForSelect();

        return view('clients.create', compact('business', 'currentBranch', 'availablePaymentMethods', 'paymentMethodNames', 'connectedVendors', 'insuranceCompanies', 'countries'));
    }

    /**
     * Search for existing client by surname, first name, and date of birth
     */
    public function searchExistingClient(Request $request)
    {
        $user = Auth::user();
        $business = $user->business;
        $currentBranch = $user->current_branch;

        $request->validate([
            'surname' => 'required|string',
            'first_name' => 'required|string',
            'date_of_birth' => 'required|date',
        ]);

        // Search for existing client with matching surname, first_name, and date_of_birth
        $existingClient = Client::where('business_id', $business->id)
            ->where('branch_id', $currentBranch->id)
            ->where('surname', $request->surname)
            ->where('first_name', $request->first_name)
            ->where('date_of_birth', $request->date_of_birth)
            ->first();

        if ($existingClient) {
            return response()->json([
                'found' => true,
                'client' => [
                    'id' => $existingClient->id,
                    'client_id' => $existingClient->client_id,
                    'other_names' => $existingClient->other_names,
                    'nin' => $existingClient->nin,
                    'tin_number' => $existingClient->tin_number,
                    'sex' => $existingClient->sex,
                    'marital_status' => $existingClient->marital_status,
                    'occupation' => $existingClient->occupation,
                    'phone_number' => $existingClient->phone_number,
                    'village' => $existingClient->village,
                    'county' => $existingClient->county,
                    'email' => $existingClient->email,
                    'services_category' => $existingClient->services_category,
                    'payment_methods' => $existingClient->payment_methods,
                    'payment_phone_number' => $existingClient->payment_phone_number,
                    'nok_surname' => $existingClient->nok_surname,
                    'nok_first_name' => $existingClient->nok_first_name,
                    'nok_other_names' => $existingClient->nok_other_names,
                    'nok_sex' => $existingClient->nok_sex,
                    'nok_marital_status' => $existingClient->nok_marital_status,
                    'nok_occupation' => $existingClient->nok_occupation,
                    'nok_phone_number' => $existingClient->nok_phone_number,
                    'nok_village' => $existingClient->nok_village,
                    'nok_county' => $existingClient->nok_county,
                ]
            ]);
        }

        return response()->json(['found' => false]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $business = $user->business;
        $currentBranch = $user->current_branch;
        
        // Check if current branch exists
        if (!$currentBranch) {
            return redirect()->route('dashboard')->with('error', 'No branch assigned. Please contact administrator.');
        }
        
        $clientType = $request->input('client_type', 'individual');
        
        // Handle different client types
        switch ($clientType) {
            case 'individual':
                return $this->storeIndividual($request, $business, $currentBranch);
            case 'company':
                return $this->storeCompany($request, $business, $currentBranch);
            case 'walk_in':
                return $this->storeWalkIn($request, $business, $currentBranch);
            default:
                return redirect()->route('clients.create')
                    ->with('error', 'Invalid client type.')
                    ->withInput();
        }
    }
    
    /**
     * Store an individual repeat customer
     */
    private function storeIndividual(Request $request, $business, $currentBranch)
    {
        Log::info('FLOW: storeIndividual - Form submission received', [
            'business_id' => $business->id,
            'branch_id' => $currentBranch->id,
            'user_id' => Auth::id(),
            'payment_methods' => $request->input('payment_methods', []),
            'insurance_company_ids_raw' => $request->input('insurance_company_ids'),
            'insurance_company_ids_array' => $request->input('insurance_company_ids', []),
            'insurance_company_ids_count' => count($request->input('insurance_company_ids', [])),
            'all_request_data' => array_filter($request->all(), fn($key) => strpos($key, 'insurance') !== false, ARRAY_FILTER_USE_KEY),
            'data_open_enrollment' => $request->input('data_open_enrollment', false),
            'existing_client_id' => $request->input('existing_client_id'),
        ]);

        // If the user chose to auto-fill an existing client, update their details and start a fresh visit.
        $existingClientId = $request->input('existing_client_id');

        // Re-verification enforcement:
        // If insurance is selected, validate that at least one vendor is selected
        // NOTE: These checks do NOT apply to open enrollment (legacy single-vendor flow)
        $isInsuranceSelected = in_array('insurance', $request->input('payment_methods', []));
        
        // Support both multi-vendor (insurance_company_ids array) and legacy single-vendor (insurance_company_id)
        $selectedVendorIds = $request->input('insurance_company_ids', []);
        $legacySingleVendorId = $request->input('insurance_company_id');
        
        Log::debug('MULTI-VENDOR: After extracting from request', [
            'selectedVendorIds' => $selectedVendorIds,
            'selectedVendorIds_count' => count($selectedVendorIds),
            'selectedVendorIds_type' => gettype($selectedVendorIds),
            'legacySingleVendorId' => $legacySingleVendorId,
            'raw_request_all_keys' => array_keys($request->all()),
        ]);
        
        // For backward compatibility, if no multi-vendor IDs but single ID exists, use it
        if (empty($selectedVendorIds) && !empty($legacySingleVendorId)) {
            $selectedVendorIds = [(int) $legacySingleVendorId];
        }
        
        Log::debug('MULTI-VENDOR: After backward compatibility check', [
            'selectedVendorIds' => $selectedVendorIds,
            'selectedVendorIds_count' => count($selectedVendorIds),
        ]);
        
        if ($isInsuranceSelected && empty($selectedVendorIds)) {
            return redirect()->back()
                ->withErrors(['insurance_company_ids' => 'Please select at least one insurance company when insurance payment method is selected.'])
                ->withInput();
        }

        if ($existingClientId) {
            $existingClient = Client::where('business_id', $business->id)
                ->where('branch_id', $currentBranch->id)
                ->where('id', (int) $existingClientId)
                ->first();

            if ($existingClient) {
                // Update the client with submitted values (so edits on the page are saved)
                $existingClient->fill($request->except(['_token', '_method', 'client_type', 'existing_client_id']));
                $existingClient->save();

                // Always issue a new visit id for this fresh session
                $existingClient->issueNewVisitId();

                return redirect()->route('pos.item-selection', $existingClient)
                    ->with('success', 'Client details updated. New visit started. Client ID: ' . $existingClient->client_id);
            }
        }

        // Check if client already exists with same surname, first_name, and date_of_birth
        $existingClient = Client::where('business_id', $business->id)
            ->where('branch_id', $currentBranch->id)
            ->where('surname', $request->surname)
            ->where('first_name', $request->first_name)
            ->where('date_of_birth', $request->date_of_birth)
            ->first();

        // If existing client found, update their details and handle multi-vendor attachment
        if ($existingClient) {
            Log::info('FLOW: Existing client found - will update vendors', [
                'existing_client_id' => $existingClient->id,
                'client_id' => $existingClient->client_id,
                'isInsuranceSelected' => $isInsuranceSelected,
                'selectedVendorIds' => $selectedVendorIds,
            ]);
            
            // If insurance is selected with multi-vendor, handle vendor attachment
            if ($isInsuranceSelected && !empty($selectedVendorIds)) {
                Log::info('FLOW: Attaching vendors to existing client', [
                    'existing_client_id' => $existingClient->id,
                    'vendor_count' => count($selectedVendorIds),
                    'selectedVendorIds' => $selectedVendorIds,
                ]);
                
                try {
                    $multiVendorService = new MultiVendorClientService();
                    
                    // Prepare vendor data from form submission
                    $vendorData = [];
                    $insuranceVendorData = $request->input('insurance_vendor_data', []);
                    $insurancePriority = $request->input('insurance_priority', []);
                    
                    foreach ($selectedVendorIds as $vendorId) {
                        if (isset($insuranceVendorData[$vendorId])) {
                            $vendorData[$vendorId] = $insuranceVendorData[$vendorId];
                        } else {
                            // Keep payload empty so service preserves existing policy_number (don't wipe mappings).
                            $vendorData[$vendorId] = [];
                        }
                        
                        // Add priority if provided
                        if (isset($insurancePriority[$vendorId])) {
                            $vendorData[$vendorId]['priority'] = (int) $insurancePriority[$vendorId];
                        }
                    }
                    
                    // Attach vendors to existing client
                    $attachmentResult = $multiVendorService->attachMultipleVendors($existingClient, $vendorData);
                    
                    Log::info('FLOW: Vendors attached to existing client', [
                        'existing_client_id' => $existingClient->id,
                        'success_count' => count($attachmentResult['success'] ?? []),
                        'failed_count' => count($attachmentResult['failed'] ?? []),
                    ]);
                    
                    // Register authorized visits with each vendor
                    $visitRegistrationResult = $multiVendorService->registerAuthorizedVisitsMultiVendor($existingClient);
                    
                    Log::info('FLOW: Authorized visits registered for existing client', [
                        'existing_client_id' => $existingClient->id,
                        'registered_vendors' => array_keys($visitRegistrationResult['registered'] ?? []),
                    ]);
                } catch (\Exception $e) {
                    Log::error('FLOW: Error attaching vendors to existing client', [
                        'existing_client_id' => $existingClient->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Issue new visit for this session
            $existingClient->issueNewVisitId();
            
            return redirect()->route('pos.item-selection', $existingClient)
                ->with('success', 'Existing client found! Vendors updated and new visit started. Client ID: ' . $existingClient->client_id);
        }

        // Validate NIN for new clients
        $ninValidation = 'nullable|string|max:255';
        
        // Get available payment methods from maturation periods for this business
        $availablePaymentMethods = MaturationPeriod::where('business_id', $business->id)
            ->where('is_active', true)
            ->pluck('payment_method')
            ->unique()
            ->values()
            ->toArray();
        
        // Validate payment methods - check if business has any set up
        if (empty($availablePaymentMethods)) {
            return redirect()->route('clients.create')
                ->with('error', 'No payment methods have been set up for your business. Please contact the administrator to configure payment methods in Maturation Periods.')
                ->withInput();
        }
        
        // Check if insurance payment method is selected
        $isInsuranceSelected = in_array('insurance', $request->input('payment_methods', []));
        
        // Validate fallback payment method if insurance is selected
        if ($isInsuranceSelected) {
            $paymentMethods = $request->input('payment_methods', []);
            $nonInsuranceMethods = array_filter($paymentMethods, function($method) {
                return $method !== 'insurance';
            });
            
            if (empty($nonInsuranceMethods)) {
                return redirect()->back()
                    ->withErrors(['payment_methods' => 'Please select at least one other payment method (cash, mobile money, or credit) in case insurance doesn\'t cover the full amount.'])
                    ->withInput();
            }
        }
        
        // Validate physical ID verification if insurance is selected (but NOT for open enrollment or multi-vendor)
        // Multi-vendor uses per-vendor physical_insurance_card_verified fields instead.
        if ($isInsuranceSelected && empty($selectedVendorIds)) {
            // Check if this is open enrollment
            $isOpenEnrollment = (bool) $request->input('data_open_enrollment', false);

            // Only validate verification if NOT open enrollment
            if (!$isOpenEnrollment) {
                // Get insurance company to check if physical ID is required
                $insuranceCompanyId = $request->input('insurance_company_id');
                $requirePhysicalId = true; // Default to required
                
                if ($insuranceCompanyId) {
                    // Fetch from third-party API to get settings
                    try {
                        $apiService = new ThirdPartyApiService();
                        $settingsResponse = $apiService->getInsuranceCompanySettings((int)$insuranceCompanyId);
                        if ($settingsResponse && isset($settingsResponse['verification_settings']['require_physical_id'])) {
                            $requirePhysicalId = $settingsResponse['verification_settings']['require_physical_id'];
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Failed to fetch insurance company settings, defaulting to require physical ID', [
                            'insurance_company_id' => $insuranceCompanyId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                // Only validate physical ID if it's required by the insurance company
                if ($requirePhysicalId && !$request->boolean('physical_id_verified')) {
                    return redirect()->back()
                        ->withErrors(['physical_id_verified' => 'Physical National ID verification is required by this insurance company.'])
                        ->withInput();
                }
                
                if (!$request->boolean('policy_verified')) {
                    return redirect()->back()
                        ->withErrors(['policy_number' => 'Policy number must be verified before submitting the form.'])
                        ->withInput();
                }
            }
        }
        
        $validated = $request->validate([
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'other_names' => 'nullable|string|max:255',
            'nin' => $ninValidation,
            'tin_number' => 'nullable|string|max:255',
            'sex' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date',
            'marital_status' => 'required|in:single,married,divorced,widowed',
            'occupation' => 'required|string|max:255',
            'phone_number' => 'required|string|max:255',
            'village' => 'required|string|max:255',
            'county' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'services_category' => 'required|in:dental,optical,outpatient,inpatient,maternity,funeral',
            'payment_methods' => 'required|array|min:1',
            'payment_methods.*' => 'required|string|in:' . implode(',', $availablePaymentMethods),
            'payment_phone_number' => 'nullable|string|max:255',
            
            // Multi-vendor insurance support - only policy number is required, payment details come from verification
            'insurance_company_ids' => $isInsuranceSelected ? 'required|array|min:1' : 'nullable|array',
            'insurance_company_ids.*' => $isInsuranceSelected ? 'required|integer|exists:insurance_companies,id' : 'nullable|integer',
            'insurance_vendor_data' => $isInsuranceSelected ? 'required|array' : 'nullable|array',
            'insurance_vendor_data.*' => $isInsuranceSelected ? 'required|array' : 'nullable|array',
            'insurance_vendor_data.*.policy_number' => $isInsuranceSelected ? 'required|string|max:255' : 'nullable|string|max:255',
            // Deductible, copay, coinsurance are optional - they'll be fetched from API verification
            'insurance_vendor_data.*.deductible_amount' => 'nullable|numeric|min:0',
            'insurance_vendor_data.*.copay_amount' => 'nullable|numeric|min:0',
            'insurance_vendor_data.*.coinsurance_percentage' => 'nullable|numeric|min:0|max:100',
            // Physical insurance card verification required per vendor
            'insurance_vendor_data.*.physical_insurance_card_verified' => $isInsuranceSelected ? 'required|boolean' : 'nullable|boolean',
            
            // Legacy single-vendor support (for backward compatibility)
            'insurance_company_id' => 'nullable|integer|exists:insurance_companies,id',
            'policy_number' => 'nullable|string|max:255',
            'physical_id_verified' => 'nullable|boolean',
            'policy_verified' => 'nullable|boolean',
            
            // Next of Kin details
            'nok_surname' => 'required|string|max:255',
            'nok_first_name' => 'required|string|max:255',
            'nok_other_names' => 'nullable|string|max:255',
            'nok_sex' => 'required|in:male,female,other',
            'nok_marital_status' => 'required|in:single,married,divorced,widowed',
            'nok_occupation' => 'required|string|max:255',
            'nok_phone_number' => 'required|string|max:255',
            'nok_village' => 'required|string|max:255',
            'nok_county' => 'required|string|max:255',
            'is_credit_eligible' => 'nullable|boolean',
            'is_long_stay' => 'nullable|boolean',
            'max_credit' => 'nullable|numeric|min:0',
        ], [
            'insurance_company_ids.required' => 'Please select at least one insurance company.',
            'insurance_company_ids.*.required' => 'Please select an insurance company.',
            'insurance_vendor_data.*.policy_number.required' => 'Please enter policy number for each selected insurance company.',
        ]);

        // Initialize payment responsibility variable (will be set if insurance is selected)
        $paymentResponsibility = null;
        $verificationMethod = null;

        // Multi-vendor processing
        if ($isInsuranceSelected && !empty($selectedVendorIds)) {
            // Skip single-vendor verification flow for multi-vendor case
            // The MultiVendorClientService will handle vendor attachment and verification
            Log::info('FLOW: Multi-vendor insurance registration', [
                'selected_vendor_ids' => $selectedVendorIds,
                'vendor_count' => count($selectedVendorIds),
            ]);
        } else if ($isInsuranceSelected && !empty($validated['insurance_company_id'])) {
            // Legacy single-vendor flow (for backward compatibility)
            // Get the local insurance company to find the third-party business ID
            $insuranceCompany = InsuranceCompany::find($validated['insurance_company_id']);
            if (!$insuranceCompany) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'insurance_company_id' => 'Selected insurance company not found.',
                    ]);
            }
            
            // Get the third-party business ID for API calls
            $thirdPartyBusinessId = $insuranceCompany->third_party_business_id;
            if (!$thirdPartyBusinessId) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'insurance_company_id' => 'Selected insurance company is not connected to a third-party vendor.',
                    ]);
            }
            
            $apiService = new ThirdPartyApiService();
            $policyData = null;
            $verificationMethod = null;
            $verificationWarnings = [];
            $verificationResult = null;
            
            // Check if this is an open enrollment registration (from form submission)
            $isOpenEnrollmentForm = (bool) $request->input('data_open_enrollment', false);
            
            if ($isOpenEnrollmentForm) {
                // For open enrollment from form, skip API verification and set directly
                $verificationMethod = 'open_enrollment';
                $policyData = [
                    'policy_number' => $validated['policy_number'] ?? '__open_enrollment__',
                ];
                
                Log::info('Client registration via open enrollment (form-confirmed)', [
                    'insurance_company_id' => $validated['insurance_company_id'],
                    'policy_number' => $policyData['policy_number'],
                ]);
            } else {
                // Normal verification flow for standard registrations
                // Try policy number verification first if provided
                if (!empty($validated['policy_number'])) {
                // Build full name from available fields for tolerance-based verification
                $fullName = trim(($validated['surname'] ?? '') . ' ' . ($validated['first_name'] ?? '') . ' ' . ($validated['other_names'] ?? ''));
                $dateOfBirth = $validated['date_of_birth'] ?? null;
                $servicesCategory = $validated['services_category'] ?? null;
                
                $verificationResult = $apiService->verifyPolicyNumber(
                    (int)$thirdPartyBusinessId, 
                    $validated['policy_number'],
                    !empty($fullName) ? $fullName : null,
                    $dateOfBirth,
                    $servicesCategory
                );
                
                if ($verificationResult && isset($verificationResult['success']) && $verificationResult['success']) {
                    $policyData = $verificationResult['data'] ?? null;
                    $verificationMethod = $verificationResult['verification_method'] ?? 'policy_number';
                    $verificationWarnings = $verificationResult['warnings'] ?? [];
                    
                    // Extract payment responsibility information
                    if (isset($verificationResult['data']['payment_responsibility'])) {
                        $paymentResponsibility = $verificationResult['data']['payment_responsibility'];
                    }
                    
                    // If verification is flagged for review, log it but allow creation
                    if (isset($verificationResult['verification_status']) && $verificationResult['verification_status'] === 'flagged') {
                        Log::warning('Client verification flagged for review', [
                            'insurance_company_id' => $validated['insurance_company_id'],
                            'third_party_business_id' => $thirdPartyBusinessId,
                            'verification_method' => $verificationMethod,
                            'warnings' => $verificationWarnings,
                        ]);
                    }
                    
                    // If verification is rejected, return error
                    if (isset($verificationResult['verification_status']) && $verificationResult['verification_status'] === 'rejected') {
                        $errorMessage = $verificationResult['message'] ?? 'Policy number found, but name and date of birth do not match the registered client.';
                        if (!empty($verificationWarnings)) {
                            $errorMessage .= ' ' . implode(' ', $verificationWarnings);
                        }
                        
                        return redirect()->back()
                            ->withInput()
                            ->withErrors([
                                'policy_number' => $errorMessage,
                            ]);
                    }
                }
            }
            
            // If policy number verification failed, try alternative methods (name + DOB only)
            if (!$policyData) {
                // Prepare alternative verification data (only name and DOB)
                $alternativeData = [];
                
                // Build full name from available fields
                $fullName = trim(($validated['surname'] ?? '') . ' ' . ($validated['first_name'] ?? '') . ' ' . ($validated['other_names'] ?? ''));
                if (!empty($fullName)) {
                    $alternativeData['name'] = $fullName;
                }
                
                if (!empty($validated['date_of_birth'])) {
                    $alternativeData['date_of_birth'] = $validated['date_of_birth'];
                }
                
                // Try alternative verification only if we have both name and DOB
                if (!empty($alternativeData['name']) && !empty($alternativeData['date_of_birth'])) {
                    $verificationResult = $apiService->verifyAlternativeIdentity(
                        (int)$thirdPartyBusinessId, 
                        $alternativeData
                    );
                    
                    if ($verificationResult && isset($verificationResult['success']) && $verificationResult['success']) {
                        $policyData = $verificationResult['data'] ?? null;
                        $verificationMethod = $verificationResult['verification_method'] ?? 'alternative';
                        $verificationWarnings = $verificationResult['warnings'] ?? [];
                        
                        // Extract payment responsibility information
                        if (isset($verificationResult['data']['payment_responsibility'])) {
                            $paymentResponsibility = $verificationResult['data']['payment_responsibility'];
                        }
                        
                        // If verification is flagged for review, log it but allow creation
                        if (isset($verificationResult['verification_status']) && $verificationResult['verification_status'] === 'flagged') {
                            Log::warning('Client verification flagged for review', [
                                'insurance_company_id' => $validated['insurance_company_id'],
                                'third_party_business_id' => $thirdPartyBusinessId,
                                'verification_method' => $verificationMethod,
                                'warnings' => $verificationWarnings,
                            ]);
                        }
                    }
                }
            }
            
            // If still no verification, return error (only for non-open-enrollment)
            if (!$policyData) {
                $errorMessage = 'The policy number could not be verified.';
                if (!empty($validated['policy_number'])) {
                    $errorMessage .= ' Please ensure the policy number is correct and active, or provide alternative verification information (name, date of birth, ID/Passport, phone, or email).';
                } else {
                    $errorMessage .= ' Please provide a policy number or alternative verification information.';
                }
                
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'policy_number' => $errorMessage,
                    ]);
            }
            
            // Log successful verification
            if ($verificationMethod && $verificationMethod !== 'policy_number') {
                Log::info('Client verified using alternative method', [
                    'insurance_company_id' => $validated['insurance_company_id'],
                    'third_party_business_id' => $thirdPartyBusinessId,
                    'verification_method' => $verificationMethod,
                    'warnings' => $verificationWarnings,
                ]);
            }
            } // End of else block for normal verification
        }
        
        // Generate new client_id and visit_id for new client
        $clientId = Client::generateClientId(
            $business,
            $validated['surname'] ?? '',
            $validated['first_name'] ?? '',
            $validated['date_of_birth'] ?? null
        );
        $isCreditEligible = $validated['is_credit_eligible'] ?? false;
        $isLongStay = $validated['is_long_stay'] ?? false;
        $visitId = Client::generateVisitId($business, $currentBranch, $isCreditEligible, $isLongStay);
        
        // Set visit expiration: null for long-stay (never expires until discharged), otherwise tomorrow
        $visitExpiresAt = $isLongStay ? null : \Carbon\Carbon::tomorrow()->startOfDay();
        
        // Generate full name by concatenating the name fields
        $fullName = trim($validated['surname'] . ' ' . $validated['first_name'] . ' ' . ($validated['other_names'] ?? ''));
        
        // Prepare client data
        $clientData = [
            'uuid' => Str::uuid(),
            'business_id' => $business->id,
            'branch_id' => $currentBranch->id,
            'client_id' => $clientId,
            'visit_id' => $visitId,
            'visit_expires_at' => $visitExpiresAt,
            'name' => $fullName,
            'surname' => $validated['surname'],
            'first_name' => $validated['first_name'],
            'other_names' => $validated['other_names'],
            'nin' => $validated['nin'],
            'tin_number' => $validated['tin_number'],
            'sex' => $validated['sex'],
            'date_of_birth' => $validated['date_of_birth'],
            'marital_status' => $validated['marital_status'],
            'occupation' => $validated['occupation'],
            'phone_number' => $validated['phone_number'],
            'village' => $validated['village'],
            'county' => $validated['county'],
            'email' => $validated['email'],
            'services_category' => $validated['services_category'],
            'payment_methods' => $validated['payment_methods'] ?? [],
            'payment_phone_number' => $validated['payment_phone_number'],
            'nok_surname' => $validated['nok_surname'],
            'nok_first_name' => $validated['nok_first_name'],
            'nok_other_names' => $validated['nok_other_names'],
            'nok_sex' => $validated['nok_sex'],
            'nok_marital_status' => $validated['nok_marital_status'],
            'nok_occupation' => $validated['nok_occupation'],
            'nok_phone_number' => $validated['nok_phone_number'],
            'nok_village' => $validated['nok_village'],
            'nok_county' => $validated['nok_county'],
            'balance' => 0,
            'status' => 'active',
            'client_type' => 'individual',
            'is_credit_eligible' => $isCreditEligible,
            'is_long_stay' => $isLongStay,
            'max_credit' => $isCreditEligible ? ($validated['max_credit'] ?? $business->max_first_party_credit_limit) : null,
            // Insurance information - only for single-vendor legacy flow
            // Multi-vendor clients don't have direct insurance_company_id (use ClientVendor instead)
            'insurance_company_id' => (!$isInsuranceSelected || !empty($selectedVendorIds)) ? null : ($validated['insurance_company_id'] ?? null),
            'policy_number' => (!$isInsuranceSelected || !empty($selectedVendorIds)) ? null : ($validated['policy_number'] ?? null),
        ];
        
        // Add payment responsibility information if available (legacy single-vendor only)
        if ($paymentResponsibility && !empty($selectedVendorIds)) {
            // For multi-vendor, skip this - payment details are stored in ClientVendor
        } else if ($paymentResponsibility) {
            $clientData['has_deductible'] = $paymentResponsibility['has_deductible'] ?? null;
            $clientData['deductible_amount'] = $paymentResponsibility['deductible_amount'] ?? null;
            $clientData['copay_amount'] = $paymentResponsibility['copay_amount'] ?? null;
            $clientData['coinsurance_percentage'] = $paymentResponsibility['coinsurance_percentage'] ?? null;
            $clientData['copay_max_limit'] = $paymentResponsibility['copay_max_limit'] ?? null;
            $clientData['copay_contributes_to_deductible'] = $paymentResponsibility['copay_contributes_to_deductible'] ?? null;
            $clientData['coinsurance_contributes_to_deductible'] = $paymentResponsibility['coinsurance_contributes_to_deductible'] ?? null;
        }
        
        // Mark if client was registered via open enrollment
        $clientData['registered_via_open_enrollment'] = (!$isInsuranceSelected || empty($selectedVendorIds)) ? ($verificationMethod === 'open_enrollment') : false;
        
        Log::info('FLOW: About to create client', [
            'is_open_enrollment' => $clientData['registered_via_open_enrollment'],
            'verification_method' => $verificationMethod ?? null,
            'insurance_selected' => $isInsuranceSelected,
            'is_multi_vendor' => !empty($selectedVendorIds),
            'vendor_count' => count($selectedVendorIds),
        ]);
        
        // Create the client
        $client = Client::create($clientData);
        
        Log::info('FLOW: Client created successfully', [
            'kashtre_client_id' => $client->client_id,
            'registered_via_open_enrollment' => $client->registered_via_open_enrollment,
            'insurance_company_id' => $client->insurance_company_id,
            'visit_id' => $client->visit_id,
        ]);
        
        // Handle multi-vendor insurance attachment
        if ($isInsuranceSelected && !empty($selectedVendorIds)) {
            try {
                $multiVendorService = new MultiVendorClientService();
                
                // Prepare vendor data from form submission
                $vendorData = [];
                $insuranceVendorData = $request->input('insurance_vendor_data', []);
                $insurancePriority = $request->input('insurance_priority', []);
                
                Log::debug('MULTI-VENDOR: Attaching vendors to client', [
                    'kashtre_client_id' => $client->client_id,
                    'selectedVendorIds' => $selectedVendorIds,
                    'selectedVendorIds_count' => count($selectedVendorIds),
                    'insuranceVendorData_keys' => array_keys($insuranceVendorData),
                    'insuranceVendorData' => $insuranceVendorData,
                    'insurancePriority' => $insurancePriority,
                ]);
                
                foreach ($selectedVendorIds as $vendorId) {
                    if (isset($insuranceVendorData[$vendorId])) {
                        $vendorData[$vendorId] = $insuranceVendorData[$vendorId];
                        Log::debug('MULTI-VENDOR: Added vendor to vendorData', [
                            'vendorId' => $vendorId,
                            'vendorData' => $vendorData[$vendorId],
                        ]);
                    } else {
                        // Keep payload empty so service preserves existing policy_number (don't wipe mappings).
                        $vendorData[$vendorId] = [];
                        Log::warning('MULTI-VENDOR: Vendor data not found; preserving existing vendor mapping', [
                            'vendorId' => $vendorId,
                        ]);
                    }
                    
                    // Add priority if provided
                    if (isset($insurancePriority[$vendorId])) {
                        $vendorData[$vendorId]['priority'] = (int) $insurancePriority[$vendorId];
                        Log::debug('MULTI-VENDOR: Added priority to vendor', [
                            'vendorId' => $vendorId,
                            'priority' => $vendorData[$vendorId]['priority'],
                        ]);
                    }
                }
                
                Log::info('FLOW: Attaching multiple vendors to client', [
                    'kashtre_client_id' => $client->client_id,
                    'vendor_count' => count($vendorData),
                    'vendor_ids' => array_keys($vendorData),
                ]);
                
                $attachmentResult = $multiVendorService->attachMultipleVendors($client, $vendorData);
                
                if (!empty($attachmentResult['success'])) {
                    Log::info('FLOW: Successfully attached vendors to client', [
                        'kashtre_client_id' => $client->client_id,
                        'success_count' => count($attachmentResult['success']),
                        'failed_count' => count($attachmentResult['failed'] ?? []),
                        'attached_vendors' => array_keys($attachmentResult['success']),
                    ]);
                }
                
                if (!empty($attachmentResult['failed'])) {
                    Log::warning('FLOW: Some vendors failed to attach', [
                        'kashtre_client_id' => $client->client_id,
                        'failed_vendors' => $attachmentResult['failed'],
                    ]);
                }
                
                // Register authorized visits with each vendor
                Log::info('FLOW: Starting multi-vendor authorized visit registration', [
                    'kashtre_client_id' => $client->client_id,
                    'visit_id' => $client->visit_id,
                ]);
                
                $visitRegistrationResult = $multiVendorService->registerAuthorizedVisitsMultiVendor($client);
                
                if (!empty($visitRegistrationResult['registered'])) {
                    Log::info('FLOW: Successfully registered visits with vendors', [
                        'kashtre_client_id' => $client->client_id,
                        'registered_vendors' => array_keys($visitRegistrationResult['registered']),
                    ]);
                }
                
                if (!empty($visitRegistrationResult['failed'])) {
                    Log::warning('FLOW: Some visit registrations failed', [
                        'kashtre_client_id' => $client->client_id,
                        'failed_vendors' => $visitRegistrationResult['failed'],
                    ]);
                }
                
            } catch (\Exception $e) {
                Log::error('FLOW: Exception during multi-vendor attachment/registration', [
                    'kashtre_client_id' => $client->client_id,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Don't fail the registration if multi-vendor setup fails - client is already created locally
            }
        }
        // Handle single-vendor legacy flow
        else if ($isInsuranceSelected) {
            try {
                $apiService = new ThirdPartyApiService();
                
                // Check if the insurance vendor is suspended or blocked
                $insuranceCompany = \App\Models\InsuranceCompany::find($client->insurance_company_id);
                if ($insuranceCompany && $insuranceCompany->third_party_business_id) {
                    $vendor = \App\Models\ThirdPartyPayer::where('business_id', $insuranceCompany->third_party_business_id)->first();
                    if ($vendor && ($vendor->isSuspended() || $vendor->isBlocked())) {
                        Log::warning('storeIndividual: Insurance vendor is suspended/blocked', [
                            'kashtre_client_id' => $client->client_id,
                            'vendor_id' => $vendor->id,
                            'vendor_status' => $vendor->status,
                            'block_reason' => $vendor->block_reason,
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot register client: Insurance vendor is {$vendor->status}. Reason: {$vendor->block_reason}",
                            'vendor_status' => $vendor->status,
                            'vendor_block_reason' => $vendor->block_reason,
                        ], 403);
                    }
                }
                
                // Sync client data if open enrollment
                if ($verificationMethod === 'open_enrollment') {
                    Log::info('FLOW: Starting client sync to third-party vendor (single-vendor legacy)', [
                        'kashtre_client_id' => $client->client_id,
                        'insurance_company_id' => $client->insurance_company_id,
                    ]);
                    
                    $syncResult = $apiService->syncClientToVendor($client);
                    
                    if ($syncResult) {
                        Log::info('FLOW: Client synced to third-party vendor successfully', [
                            'kashtre_client_id' => $client->client_id,
                            'vendor_response' => $syncResult,
                        ]);
                    } else {
                        Log::warning('FLOW: Client sync returned null (possible API error)', [
                            'kashtre_client_id' => $client->client_id,
                        ]);
                    }
                } else {
                    Log::info('FLOW: Skipping client sync (not open enrollment)', [
                        'verification_method' => $verificationMethod,
                        'kashtre_client_id' => $client->client_id,
                    ]);
                }
                
                // Register the authorized visit for both open enrollment and normal registrations
                Log::info('FLOW: Starting authorized visit registration', [
                    'kashtre_client_id' => $client->client_id,
                    'visit_id' => $client->visit_id,
                    'visit_date' => now()->toDateString(),
                ]);
                
                $visitRegistrationResult = $apiService->registerAuthorizedVisit(
                    $client,
                    $client->visit_id,
                    now()->toDateString(),
                    $client->visit_expires_at ? $client->visit_expires_at->toDateTimeString() : null,
                    $client->services_category
                );
                
                if ($visitRegistrationResult) {
                    Log::info('FLOW: Authorized visit registered successfully', [
                        'kashtre_client_id' => $client->client_id,
                        'visit_id' => $client->visit_id,
                        'verification_method' => $verificationMethod,
                        'vendor_response' => $visitRegistrationResult,
                    ]);
                } else {
                    Log::warning('FLOW: Visit registration returned null (possible API error)', [
                        'kashtre_client_id' => $client->client_id,
                        'visit_id' => $client->visit_id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('FLOW: Exception during vendor sync/registration', [
                    'kashtre_client_id' => $client->client_id,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Don't fail the registration if sync fails - client is already created locally
            }
        }
        
        return redirect()->route('pos.item-selection', $client)
            ->with('success', 'Client registered successfully! Client ID: ' . $clientId);
    }
    
    /**
     * Store a company client
     */
    private function storeCompany(Request $request, $business, $currentBranch)
    {
        // Check if insurance payment method is selected
        $isInsuranceSelected = in_array('insurance', $request->input('payment_methods', []));
        
        // Validate fallback payment method if insurance is selected
        if ($isInsuranceSelected) {
            $paymentMethods = $request->input('payment_methods', []);
            $nonInsuranceMethods = array_filter($paymentMethods, function($method) {
                return $method !== 'insurance';
            });
            
            if (empty($nonInsuranceMethods)) {
                return redirect()->back()
                    ->withErrors(['payment_methods' => 'Please select at least one other payment method (cash, mobile money, or credit) in case insurance doesn\'t cover the full amount.'])
                    ->withInput();
            }
        }
        // Get available payment methods from maturation periods for this business
        $availablePaymentMethods = MaturationPeriod::where('business_id', $business->id)
            ->where('is_active', true)
            ->pluck('payment_method')
            ->unique()
            ->values()
            ->toArray();
        
        // Validate payment methods - check if business has any set up
        if (empty($availablePaymentMethods)) {
            return redirect()->route('clients.create')
                ->with('error', 'No payment methods have been set up for your business. Please contact the administrator to configure payment methods in Maturation Periods.')
                ->withInput();
        }
        
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_tin' => 'required|string|max:255|unique:clients,tin_number',
            'company_phone' => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_address' => 'required|string',
            'insurance_company_code' => 'required_if:register_type,client_and_payer|string|size:8|regex:/^[A-Z0-9]{8}$/',
            'register_type' => 'nullable|in:client_only,client_and_payer',
            'payment_methods' => 'required|array|min:1',
            'payment_methods.*' => 'required|string|in:' . implode(',', $availablePaymentMethods),
            'payment_phone_number' => 'nullable|string|max:255',
        ], [
            'insurance_company_code.required_if' => 'Third party vendor code is required when registering as third party payer.',
            'insurance_company_code.size' => 'Third party vendor code must be exactly 8 characters.',
            'insurance_company_code.regex' => 'Third party vendor code must contain only uppercase letters and numbers (8 characters).',
        ]);
        
        // Generate client_id for company (using company name)
        $clientId = Client::generateClientId(
            $business,
            $validated['company_name'],
            '',
            null
        );
        
        $visitId = Client::generateVisitId($business, $currentBranch, false, false);
        $visitExpiresAt = \Carbon\Carbon::tomorrow()->startOfDay();
        
        // Always register business and user in third-party system for company clients
        $thirdPartyData = null;
        $registerType = $validated['register_type'] ?? 'client_only';
        $generatedPassword = null;
        $generatedUsername = null;
        
        // Log the registration
        Log::info('Company client registration - API will be called', [
            'client_id' => $clientId,
            'company_name' => $validated['company_name'],
            'register_type' => $registerType,
        ]);
        
        // Always call the API for company client registrations
        $finalUsername = null; // Initialize for later use
        $linkedInsuranceCompany = null; // Track the insurance company linked by code
        $userWasExisting = false; // Track if user already existed in third-party system
        
        try {
            $apiService = new ThirdPartyApiService();
            
            // If registering as third party payer, validate and get insurance company by code
            if ($registerType === 'client_and_payer' && !empty($validated['insurance_company_code'])) {
                // Normalize code to uppercase for consistency
                $validated['insurance_company_code'] = strtoupper($validated['insurance_company_code']);
                $insuranceCompanyData = $apiService->getInsuranceCompanyByCode($validated['insurance_company_code']);
                
                if (!$insuranceCompanyData || !isset($insuranceCompanyData['business'])) {
                    return redirect()->back()
                        ->withInput()
                        ->withErrors([
                            'insurance_company_code' => 'Third party vendor with code ' . $validated['insurance_company_code'] . ' not found in the third-party system. Please verify the code and try again.',
                        ]);
                }
                
                $linkedInsuranceCompany = $insuranceCompanyData['business'];
                Log::info('Third party vendor found by code for client registration', [
                    'code' => $validated['insurance_company_code'],
                    'business_id' => $linkedInsuranceCompany['id'] ?? null,
                    'business_name' => $linkedInsuranceCompany['name'] ?? null,
                ]);
            }
            
            // If insurance company code was provided, create connection between current Kashtre business and insurance company
            if ($linkedInsuranceCompany && isset($linkedInsuranceCompany['id'])) {
                // Use current Kashtre business ID directly (we're already in this business)
                $kashtreBusinessId = $business->id;
                
                // Create connection: current Kashtre business -> insurance company (from code)
                $connectionResult = $apiService->createBusinessConnection(
                    $linkedInsuranceCompany['id'], // Insurance company to connect to (from code)
                    $kashtreBusinessId, // Current Kashtre business ID
                    $business->name // Business name to display in third-party system
                );
                
                if ($connectionResult) {
                    Log::info('Business connection created in third-party system', [
                        'insurance_company_id' => $linkedInsuranceCompany['id'],
                        'insurance_company_name' => $linkedInsuranceCompany['name'] ?? null,
                        'kashtre_business_id' => $kashtreBusinessId,
                        'kashtre_business_name' => $business->name,
                        'connection_id' => $connectionResult['connection_id'] ?? null,
                    ]);
                } else {
                    Log::warning('Failed to create business connection in third-party system', [
                        'insurance_company_id' => $linkedInsuranceCompany['id'],
                        'kashtre_business_id' => $kashtreBusinessId,
                    ]);
                }
                
                // Store connection info for tracking (use insurance company ID for connected_accounts)
                $thirdPartyData = [
                    'business' => [
                        'id' => $linkedInsuranceCompany['id'], // Insurance company ID for connected_accounts table
                        'name' => $linkedInsuranceCompany['name'] ?? null,
                    ],
                    'user' => null,
                ];
            } else {
                // No insurance company code provided - no connection needed
                $thirdPartyData = null;
            }
        } catch (\Exception $e) {
            // Check if it's a duplicate error that wasn't caught above
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'email') || str_contains($errorMessage, 'Email') || 
                str_contains($errorMessage, 'username') || str_contains($errorMessage, 'Username') ||
                str_contains($errorMessage, 'unique') || str_contains($errorMessage, 'already')) {
                
                Log::error('Duplicate detected - registration failed', [
                    'error' => $errorMessage,
                    'client_data' => [
                        'company_name' => $validated['company_name'],
                        'company_email' => $validated['company_email'],
                        'register_type' => $registerType,
                    ],
                ]);
                
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'company_email' => 'This information already exists in the third-party system. Please use different details or contact support.',
                    ]);
            }
            
            // For other errors, log and fail the client creation
            Log::error('Failed to register business in third-party system', [
                'error' => $e->getMessage(),
                'client_data' => [
                    'company_name' => $validated['company_name'],
                    'register_type' => $registerType,
                ],
            ]);
            
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'company_email' => 'Failed to register in third-party system: ' . $e->getMessage() . ' Please try again or contact support.',
                ]);
        }
        
        // Create the company client
        $client = Client::create([
            'uuid' => Str::uuid(),
            'business_id' => $business->id,
            'branch_id' => $currentBranch->id,
            'client_id' => $clientId,
            'visit_id' => $visitId,
            'visit_expires_at' => $visitExpiresAt,
            'name' => $validated['company_name'],
            'surname' => $validated['company_name'],
            'first_name' => '',
            'tin_number' => $validated['company_tin'],
            'phone_number' => $validated['company_phone'],
            'email' => $validated['company_email'],
            'occupation' => $validated['company_name'], // Use company name as occupation
            'payment_methods' => $validated['payment_methods'] ?? [],
            'payment_phone_number' => $validated['payment_phone_number'],
            'balance' => 0,
            'status' => 'active',
            'client_type' => 'company',
        ]);
        
        $message = 'Company client registered successfully! Client ID: ' . $clientId;
        
        // Prepare redirect
        $redirect = redirect()->route('clients.show', $client);
        
        // Register authorized visit if insurance is selected
        if ($isInsuranceSelected) {
            try {
                $apiService = new ThirdPartyApiService();
                
                // Check if the insurance vendor is suspended or blocked
                if ($linkedInsuranceCompany && isset($linkedInsuranceCompany['id'])) {
                    $vendor = \App\Models\ThirdPartyPayer::where('business_id', $linkedInsuranceCompany['id'])->first();
                    if ($vendor && ($vendor->isSuspended() || $vendor->isBlocked())) {
                        Log::warning('storeCompany: Insurance vendor is suspended/blocked', [
                            'kashtre_client_id' => $client->client_id,
                            'vendor_id' => $vendor->id,
                            'vendor_status' => $vendor->status,
                            'block_reason' => $vendor->block_reason,
                        ]);
                        return redirect()->back()
                            ->withInput()
                            ->withErrors([
                                'insurance_company_code' => "Cannot register: Insurance vendor is {$vendor->status}. Reason: {$vendor->block_reason}",
                            ]);
                    }
                }
                
                $visitRegistrationResult = $apiService->registerAuthorizedVisit(
                    $client,
                    $client->visit_id,
                    now()->toDateString(),
                    $client->visit_expires_at ? $client->visit_expires_at->toDateTimeString() : null,
                    $client->services_category
                );
                
                if ($visitRegistrationResult) {
                    Log::info('Authorized visit registered for company client', [
                        'kashtre_client_id' => $client->client_id,
                        'visit_id' => $client->visit_id,
                        'visit_registration_result' => $visitRegistrationResult,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to register authorized visit for company client', [
                    'kashtre_client_id' => $client->client_id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the registration if visit registration fails
            }
        }
        
        // Create connected account record if connection was made
        if ($thirdPartyData && isset($thirdPartyData['business']['id'])) {
            try {
                ConnectedAccount::create([
                    'client_id' => $client->id,
                    'third_party_business_id' => $thirdPartyData['business']['id'],
                    'third_party_user_id' => null, // We don't create users anymore
                    'third_party_username' => null,
                    'connection_type' => $registerType === 'client_and_payer' ? 'payer' : 'client',
                    'status' => 'active',
                    'notes' => 'Connected to third-party system during client registration',
                ]);
                
                Log::info('Connected account record created in Kashtre', [
                    'client_id' => $client->id,
                    'third_party_business_id' => $thirdPartyData['business']['id'],
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create connected account record', [
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the entire registration if connection record creation fails
            }
            
            // Auto-create ThirdPartyPayer record for this vendor
            if ($linkedInsuranceCompany && isset($linkedInsuranceCompany['id'])) {
                try {
                    // Find or create local InsuranceCompany record
                    $insuranceCompany = InsuranceCompany::where('code', $validated['insurance_company_code'])->first();
                    
                    if (!$insuranceCompany) {
                        // Create insurance company record if it doesn't exist
                        $insuranceCompany = InsuranceCompany::create([
                            'business_id' => $business->id,
                            'name' => $linkedInsuranceCompany['name'],
                            'code' => $validated['insurance_company_code'],
                            'email' => $linkedInsuranceCompany['email'] ?? null,
                            'phone' => $linkedInsuranceCompany['phone'] ?? null,
                            'third_party_business_id' => $linkedInsuranceCompany['id'],
                        ]);
                        
                        Log::info('Created insurance company record during client registration', [
                            'insurance_company_id' => $insuranceCompany->id,
                            'vendor_code' => $validated['insurance_company_code'],
                            'vendor_id' => $linkedInsuranceCompany['id'],
                        ]);
                    }
                    
                    // Create or update ThirdPartyPayer record
                    $payer = ThirdPartyPayer::firstOrCreate(
                        [
                            'business_id' => $business->id,
                            'insurance_company_id' => $insuranceCompany->id,
                            'type' => 'insurance_company',
                            'client_id' => null, // Business-level account
                        ],
                        [
                            'name' => $linkedInsuranceCompany['name'],
                            'email' => $linkedInsuranceCompany['email'] ?? null,
                            'phone_number' => $linkedInsuranceCompany['phone'] ?? null,
                            'status' => 'active',
                            'credit_limit' => $business->max_third_party_credit_limit ?? 10000.00,
                        ]
                    );
                    
                    Log::info('ThirdPartyPayer auto-created during company client registration', [
                        'third_party_payer_id' => $payer->id,
                        'vendor_code' => $validated['insurance_company_code'],
                        'vendor_name' => $linkedInsuranceCompany['name'],
                        'business_id' => $business->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create ThirdPartyPayer during company client registration', [
                        'vendor_code' => $validated['insurance_company_code'],
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the registration if ThirdPartyPayer creation fails
                }
            }
        }
        
        // Log connection for admin reference (no user-facing message)
        if ($thirdPartyData && isset($thirdPartyData['business']['id'])) {
            Log::info('Third-party connection created for client', [
                'client_id' => $clientId,
                'client_name' => $validated['company_name'],
                'third_party_username' => $thirdPartyData['user']['username'] ?? null,
                'third_party_business_id' => $thirdPartyData['business']['id'],
                'third_party_user_id' => $thirdPartyData['user']['id'] ?? null,
            ]);
        }
        
        return $redirect->with('success', $message);
    }
    
    /**
     * Store a walk-in client (minimal information)
     */
    private function storeWalkIn(Request $request, $business, $currentBranch)
    {
        // Check if insurance payment method is selected
        $isInsuranceSelected = in_array('insurance', $request->input('payment_methods', []));
        
        // Validate fallback payment method if insurance is selected
        if ($isInsuranceSelected) {
            $paymentMethods = $request->input('payment_methods', []);
            $nonInsuranceMethods = array_filter($paymentMethods, function($method) {
                return $method !== 'insurance';
            });
            
            if (empty($nonInsuranceMethods)) {
                return redirect()->back()
                    ->withErrors(['payment_methods' => 'Please select at least one other payment method (cash, mobile money, or credit) in case insurance doesn\'t cover the full amount.'])
                    ->withInput();
            }
        }
        // Get available payment methods from maturation periods for this business
        $availablePaymentMethods = MaturationPeriod::where('business_id', $business->id)
            ->where('is_active', true)
            ->pluck('payment_method')
            ->unique()
            ->values()
            ->toArray();
        
        // Validate payment methods - check if business has any set up
        if (empty($availablePaymentMethods)) {
            return redirect()->route('clients.create')
                ->with('error', 'No payment methods have been set up for your business. Please contact the administrator to configure payment methods in Maturation Periods.')
                ->withInput();
        }
        
        $validated = $request->validate([
            'payment_methods' => 'required|array|min:1',
            'payment_methods.*' => 'required|string|in:' . implode(',', $availablePaymentMethods),
            'payment_phone_number' => 'nullable|string|max:255',
        ]);
        
        // Generate client_id using the standard format (business prefix + 7-char code)
        // For walk-in, we use "WalkIn" as surname and "Client" as first name
        $clientId = Client::generateClientId(
            $business,
            'WalkIn',
            'Client',
            null
        );
        
        $visitId = Client::generateVisitId($business, $currentBranch, false, false);
        $visitExpiresAt = \Carbon\Carbon::tomorrow()->startOfDay();
        
        // Create minimal walk-in client
        $client = Client::create([
            'uuid' => Str::uuid(),
            'business_id' => $business->id,
            'branch_id' => $currentBranch->id,
            'client_id' => $clientId,
            'visit_id' => $visitId,
            'visit_expires_at' => $visitExpiresAt,
            'name' => 'Walk In Client',
            'surname' => 'Walk In',
            'first_name' => 'Client',
            'phone_number' => '0000000000', // Placeholder for walk-in clients
            'email' => 'walkin@example.com', // Placeholder email
            'payment_methods' => $validated['payment_methods'] ?? [],
            'payment_phone_number' => $validated['payment_phone_number'],
            'balance' => 0,
            'status' => 'active',
            'client_type' => 'walk_in',
        ]);
        
        // Register authorized visit if insurance is selected
        if ($isInsuranceSelected) {
            try {
                $apiService = new ThirdPartyApiService();
                $visitRegistrationResult = $apiService->registerAuthorizedVisit(
                    $client,
                    $client->visit_id,
                    now()->toDateString(),
                    $client->visit_expires_at ? $client->visit_expires_at->toDateTimeString() : null,
                    $client->services_category
                );
                
                if ($visitRegistrationResult) {
                    Log::info('Authorized visit registered for walk-in client', [
                        'kashtre_client_id' => $client->client_id,
                        'visit_id' => $client->visit_id,
                        'visit_registration_result' => $visitRegistrationResult,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to register authorized visit for walk-in client', [
                    'kashtre_client_id' => $client->client_id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the registration if visit registration fails
            }
        }
        
        return redirect()->route('pos.item-selection', $client)
            ->with('success', 'Walk-in client created! Client ID: ' . $clientId);
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        $user = Auth::user();
        $business = $user->business;
        
        // Check if user has access to this client
        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            abort(403, 'Unauthorized access to client.');
        }
        
        // Get items for this business (for exclusions management)
        $items = \App\Models\Item::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);
        
        return view('clients.show', compact('client', 'business', 'items'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client)
    {
        $user = Auth::user();
        $business = $user->business;
        
        // Check if user has access to this client
        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            abort(403, 'Unauthorized access to client.');
        }
        
        $countries = \App\Services\CountriesService::getCountriesForSelect();
        
        return view('clients.edit', compact('client', 'business', 'countries'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        $user = Auth::user();
        $business = $user->business;
        
        // Check if user has access to this client
        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            abort(403, 'Unauthorized access to client.');
        }
        
        $validated = $request->validate([
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'other_names' => 'nullable|string|max:255',
            'nin' => 'nullable|string|max:255',
            'id_passport_no' => 'nullable|string|max:255',
            'sex' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'occupation' => 'nullable|string|max:255',
            'phone_number' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'email' => 'nullable|email|max:255',
            'services_category' => 'nullable|in:dental,optical,outpatient,inpatient,maternity,funeral',
            'preferred_payment_method' => 'nullable|in:cash,bank_transfer,credit_card,insurance,postpaid,mobile_money',
            'status' => 'required|in:active,inactive,suspended',
            'is_credit_eligible' => 'nullable|boolean',
            'is_long_stay' => 'nullable|boolean',
            'max_credit' => 'nullable|numeric|min:0',
            
            // Next of Kin details
            'nok_surname' => 'nullable|string|max:255',
            'nok_first_name' => 'nullable|string|max:255',
            'nok_other_names' => 'nullable|string|max:255',
            'nok_marital_status' => 'nullable|in:single,married,divorced,widowed',
            'nok_occupation' => 'nullable|string|max:255',
            'nok_phone_number' => 'nullable|string|max:255',
            'nok_physical_address' => 'nullable|string|max:500',
        ]);
        
        // Check if credit or long-stay flags changed - if so, regenerate visit ID
        $needsVisitIdRegeneration = false;
        $isCreditEligible = isset($validated['is_credit_eligible']) ? (bool)$validated['is_credit_eligible'] : $client->is_credit_eligible;
        
        if (isset($validated['is_credit_eligible']) && $validated['is_credit_eligible'] != $client->is_credit_eligible) {
            $needsVisitIdRegeneration = true;
        }
        if (isset($validated['is_long_stay']) && $validated['is_long_stay'] != $client->is_long_stay) {
            $needsVisitIdRegeneration = true;
        }
        
        // Handle max_credit: only set if credit eligible, otherwise null
        if (isset($validated['max_credit'])) {
            $validated['max_credit'] = $isCreditEligible ? ($validated['max_credit'] ?? $business->max_first_party_credit_limit) : null;
        } elseif (!$isCreditEligible) {
            $validated['max_credit'] = null;
        }
        
        $client->update($validated);
        
        // Regenerate visit ID if flags changed
        if ($needsVisitIdRegeneration) {
            $business = $client->business ?: Business::find($client->business_id);
            $branch = $client->branch ?: Branch::find($client->branch_id);
            if ($business && $branch) {
                $newVisitId = Client::generateVisitId(
                    $business, 
                    $branch, 
                    $client->is_credit_eligible ?? false, 
                    $client->is_long_stay ?? false
                );
                $client->visit_id = $newVisitId;
                
                // If long-stay, set expiration to null (never expires until discharged)
                // Otherwise, set to tomorrow
                if ($client->is_long_stay) {
                    $client->visit_expires_at = null;
                } else {
                    $client->visit_expires_at = \Carbon\Carbon::tomorrow()->startOfDay();
                }
                $client->save();
            }
        }
        
        return redirect()->route('clients.index')
            ->with('success', 'Client updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $user = Auth::user();
        $business = $user->business;
        
        // Check if user has access to this client
        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            abort(403, 'Unauthorized access to client.');
        }
        
        $client->delete();
        
        return redirect()->route('clients.index')
            ->with('success', 'Client deleted successfully!');
    }

    /**
     * Update payment methods for a client
     */
    public function updatePaymentMethods(Request $request, Client $client)
    {
        $user = Auth::user();
        $business = $user->business;
        
        // Check if user has access to this client
        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to client.'
            ], 403);
        }
        
        $validated = $request->validate([
            'payment_methods' => 'required|array|min:1',
            'payment_methods.*' => 'string|in:packages,insurance,credit_arrangement_institutions,deposits_account_balance,mobile_money,v_card,p_card,bank_transfer,cash'
        ]);
        
        $client->update([
            'payment_methods' => $validated['payment_methods']
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment methods updated successfully!',
            'payment_methods' => $client->payment_methods
        ]);
    }

    /**
     * Update payment phone number for a client
     */
    public function updatePaymentPhone(Request $request, Client $client)
    {
        $user = Auth::user();
        $business = $user->business;
        
        // Check if user has access to this client
        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to client.'
            ], 403);
        }
        
        $validated = $request->validate([
            'payment_phone_number' => 'nullable|string|max:255'
        ]);
        
        $client->update([
            'payment_phone_number' => $validated['payment_phone_number']
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment phone number updated successfully!',
            'payment_phone_number' => $client->payment_phone_number
        ]);
    }

    public function updateServicesCategory(Request $request, Client $client)
    {
        $user = Auth::user();
        $business = $user->business;

        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to client.'
            ], 403);
        }

        $validated = $request->validate([
            'services_category' => 'required|in:dental,optical,outpatient,inpatient,maternity,funeral'
        ]);

        $client->update([
            'services_category' => $validated['services_category']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Services category updated to ' . ucfirst($validated['services_category']) . '.',
            'services_category' => $client->services_category
        ]);
    }

    /**
     * Update excluded items for a credit client.
     */
    public function updateExcludedItems(Request $request, Client $client)
    {
        $user = Auth::user();
        $business = $user->business;
        
        // Check if user has access to this client
        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            abort(403, 'Unauthorized access to client.');
        }

        // Only allow for credit-eligible clients
        if (!$client->is_credit_eligible) {
            return redirect()->route('clients.show', $client)
                ->with('error', 'Excluded items can only be set for credit-eligible clients.');
        }

        $validated = $request->validate([
            'excluded_items' => 'nullable|array',
            'excluded_items.*' => 'integer|exists:items,id',
        ]);

        $client->update([
            'excluded_items' => $validated['excluded_items'] ?? [],
        ]);

        return redirect()->route('clients.show', $client)
            ->with('success', 'Excluded items updated successfully.');
    }

    /**
     * Admit a client - enable credit and/or long-stay
     */
    public function admit(Request $request, Client $client)
    {
        \Illuminate\Support\Facades\Log::info("🚀 ========== ADMISSION PROCESS STARTED ==========", [
            'timestamp' => now()->toDateTimeString(),
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_client_id' => $client->client_id,
            'client_visit_id' => $client->visit_id,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name ?? 'Unknown',
            'request_data' => $request->all()
        ]);
        
        $user = Auth::user();
        $business = $user->business;
        
        \Illuminate\Support\Facades\Log::info("📋 ADMISSION: User and Business Information", [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_permissions' => $user->permissions ?? [],
            'business_id' => $business->id ?? null,
            'business_name' => $business->name ?? null,
            'client_business_id' => $client->business_id
        ]);
        
        // Check permission
        if (!in_array('Admit Clients', $user->permissions ?? [])) {
            \Illuminate\Support\Facades\Log::warning("❌ ADMISSION: Permission Denied", [
                'user_id' => $user->id,
                'user_permissions' => $user->permissions ?? [],
                'required_permission' => 'Admit Clients'
            ]);
            $redirectTo = $request->get('redirect_to', route('clients.show', $client));
            return redirect($redirectTo)
                ->with('error', 'You do not have permission to admit clients.');
        }
        
        // Check if user has access to this client
        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            \Illuminate\Support\Facades\Log::warning("❌ ADMISSION: Unauthorized Access Attempt", [
                'user_business_id' => $user->business_id,
                'client_business_id' => $client->business_id,
                'user_id' => $user->id,
                'client_id' => $client->id
            ]);
            $redirectTo = $request->get('redirect_to', route('clients.show', $client));
            return redirect($redirectTo)
                ->with('error', 'Unauthorized access to client.');
        }

        // Check if client already has /M suffix (long-stay)
        if ($client->is_long_stay || preg_match('/\/M$/', $client->visit_id)) {
            \Illuminate\Support\Facades\Log::warning("❌ ADMISSION: Client Already Admitted", [
                'client_id' => $client->id,
                'client_visit_id' => $client->visit_id,
                'is_long_stay' => $client->is_long_stay,
                'visit_id_has_m_suffix' => preg_match('/\/M$/', $client->visit_id)
            ]);
            $redirectTo = $request->get('redirect_to', route('clients.show', $client));
            return redirect($redirectTo)
                ->with('error', 'Client is already admitted. Please discharge first.');
        }

        \Illuminate\Support\Facades\Log::info("✅ ADMISSION: Permission and Access Checks Passed", [
            'client_id' => $client->id,
            'user_id' => $user->id
        ]);

        $validated = $request->validate([
            'enable_credit' => 'boolean',
            'enable_long_stay' => 'boolean',
            'max_credit' => 'nullable|numeric|min:0',
            'queue_item_id' => 'nullable|integer|exists:service_delivery_queues,id',
        ]);

        \Illuminate\Support\Facades\Log::info("✅ ADMISSION: Request Validation Passed", [
            'validated_data' => $validated,
            'request_has_enable_credit' => $request->has('enable_credit'),
            'request_has_enable_long_stay' => $request->has('enable_long_stay'),
            'request_max_credit' => $request->get('max_credit')
        ]);

        // Use explicit values from request if provided, otherwise use business settings
        $enableCredit = $request->has('enable_credit') 
            ? (bool)$validated['enable_credit'] 
            : ($business->admit_enable_credit ?? false);
        
        $enableLongStay = $request->has('enable_long_stay') 
            ? (bool)$validated['enable_long_stay'] 
            : ($business->admit_enable_long_stay ?? false);

        \Illuminate\Support\Facades\Log::info("⚙️ ADMISSION: Business Settings Retrieved", [
            'business_id' => $business->id,
            'business_admit_enable_credit' => $business->admit_enable_credit ?? false,
            'business_admit_enable_long_stay' => $business->admit_enable_long_stay ?? false,
            'business_max_first_party_credit_limit' => $business->max_first_party_credit_limit ?? 0,
            'final_enable_credit' => $enableCredit,
            'final_enable_long_stay' => $enableLongStay
        ]);

        if (!$enableCredit && !$enableLongStay) {
            \Illuminate\Support\Facades\Log::warning("❌ ADMISSION: No Options Selected", [
                'enable_credit' => $enableCredit,
                'enable_long_stay' => $enableLongStay
            ]);
            $redirectTo = $request->get('redirect_to', route('clients.show', $client));
            return redirect($redirectTo)
                ->with('error', 'Please select at least one option: Credit or Long-Stay.');
        }

        \Illuminate\Support\Facades\Log::info("📝 ADMISSION: Client Status Before Update", [
            'client_id' => $client->id,
            'current_is_credit_eligible' => $client->is_credit_eligible,
            'current_is_long_stay' => $client->is_long_stay,
            'current_max_credit' => $client->max_credit,
            'current_visit_id' => $client->visit_id,
            'current_visit_expires_at' => $client->visit_expires_at
        ]);

        // Update client flags based on what was explicitly selected during admission
        // This ensures the visit ID format matches the selected options
        $client->is_credit_eligible = $enableCredit;
        $client->is_long_stay = $enableLongStay;

        // Set max_credit if credit is enabled
        if ($enableCredit) {
            // Use provided max_credit or default to business first party credit limit
            $client->max_credit = $validated['max_credit'] ?? $business->max_first_party_credit_limit;
            \Illuminate\Support\Facades\Log::info("💳 ADMISSION: Credit Limit Set", [
                'max_credit' => $client->max_credit,
                'source' => $request->has('max_credit') ? 'request' : 'business_default'
            ]);
        } else {
            // Clear max_credit if credit is not enabled
            $client->max_credit = null;
            \Illuminate\Support\Facades\Log::info("💳 ADMISSION: Credit Limit Cleared (Credit Not Enabled)");
        }

        // Update visit ID by preserving base ID and appending suffixes
        $branch = $client->branch ?: Branch::find($client->branch_id);
        if ($business && $branch) {
            $oldVisitId = $client->visit_id;
            
            // Extract base visit ID (remove any existing suffixes like /C, /M, /C/M)
            $baseVisitId = preg_replace('/\/(C\/M|C|M)$/', '', $client->visit_id ?? '');
            
            // If no base visit ID exists, generate a new one
            if (empty($baseVisitId)) {
                $baseVisitId = preg_replace('/\/(C\/M|C|M)$/', '', Client::generateVisitId($business, $branch, false, false));
            }
            
            // Build suffix based on admission flags
            $suffix = '';
            if ($client->is_long_stay && $client->is_credit_eligible) {
                $suffix = '/C/M';
            } elseif ($client->is_long_stay) {
                $suffix = '/M';
            } elseif ($client->is_credit_eligible) {
                $suffix = '/C';
            }
            
            // Combine base visit ID with new suffix
            $client->visit_id = $baseVisitId . $suffix;
            
            \Illuminate\Support\Facades\Log::info("🆔 ADMISSION: Visit ID Updated (Base Preserved)", [
                'old_visit_id' => $oldVisitId,
                'base_visit_id' => $baseVisitId,
                'new_visit_id' => $client->visit_id,
                'suffix' => $suffix,
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'is_credit_eligible' => $client->is_credit_eligible,
                'is_long_stay' => $client->is_long_stay
            ]);
            
            // Set visit_expires_at to null for long-stay clients
            if ($client->is_long_stay) {
                $oldVisitExpiresAt = $client->visit_expires_at;
                $client->visit_expires_at = null;
                \Illuminate\Support\Facades\Log::info("📅 ADMISSION: Visit Expiry Cleared (Long-Stay)", [
                    'old_visit_expires_at' => $oldVisitExpiresAt
                ]);
            }
        } else {
            \Illuminate\Support\Facades\Log::warning("⚠️ ADMISSION: Could Not Update Visit ID", [
                'business_exists' => !is_null($business),
                'branch_exists' => !is_null($branch),
                'branch_id' => $client->branch_id
            ]);
        }

        $client->save();
        
        \Illuminate\Support\Facades\Log::info("💾 ADMISSION: Client Record Saved", [
            'client_id' => $client->id,
            'updated_is_credit_eligible' => $client->is_credit_eligible,
            'updated_is_long_stay' => $client->is_long_stay,
            'updated_max_credit' => $client->max_credit,
            'updated_visit_id' => $client->visit_id,
            'updated_visit_expires_at' => $client->visit_expires_at
        ]);
        
        // If this is an AJAX request (for per-item admission), return JSON and skip item processing
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Visit ID updated successfully.',
                'visit_id' => $client->visit_id,
                'is_credit_eligible' => $client->is_credit_eligible,
                'is_long_stay' => $client->is_long_stay
            ]);
        }

        // Process admission items - mark them as completed and move money
        // This applies whether credit is enabled or not - admission always marks items as completed
        try {
            $moneyTrackingService = new \App\Services\MoneyTrackingService();
            
            // Try to extract service point ID from redirect URL (e.g., /service-points/68/client/25/details)
            $redirectTo = $request->get('redirect_to', '');
            $servicePointIdFromUrl = null;
            if (preg_match('/service-points\/(\d+)\//', $redirectTo, $matches)) {
                $servicePointIdFromUrl = (int)$matches[1];
                \Illuminate\Support\Facades\Log::info("🔍 ADMISSION: Extracted Service Point ID from URL", [
                    'redirect_url' => $redirectTo,
                    'service_point_id' => $servicePointIdFromUrl
                ]);
            }
            
            // Find service points to process
            // Priority: 1) Service point from URL, 2) Service points named "admission"
            $admissionServicePointIds = [];
            
            if ($servicePointIdFromUrl) {
                // Use the service point from the URL
                $servicePointFromUrl = \App\Models\ServicePoint::find($servicePointIdFromUrl);
                if ($servicePointFromUrl) {
                    $admissionServicePointIds[] = $servicePointFromUrl->id;
                    \Illuminate\Support\Facades\Log::info("📍 ADMISSION: Using Service Point from URL", [
                        'service_point_id' => $servicePointFromUrl->id,
                        'service_point_name' => $servicePointFromUrl->name
                    ]);
                }
            }
            
            // Also find ALL admission service points (case-insensitive match)
            // This handles cases where there might be multiple service points with "admission" in the name
            $admissionServicePoints = \App\Models\ServicePoint::whereRaw('LOWER(TRIM(name)) = ?', ['admission'])->get();
            
            // Add admission service points to the list (avoid duplicates)
            foreach ($admissionServicePoints as $sp) {
                if (!in_array($sp->id, $admissionServicePointIds)) {
                    $admissionServicePointIds[] = $sp->id;
                }
            }
            
            if (empty($admissionServicePointIds)) {
                \Illuminate\Support\Facades\Log::warning("⚠️ ADMISSION: No Admission Service Points Found", [
                    'client_id' => $client->id,
                    'service_point_id_from_url' => $servicePointIdFromUrl
                ]);
            } else {
                \Illuminate\Support\Facades\Log::info("📍 ADMISSION: Service Points to Process", [
                    'service_point_ids' => $admissionServicePointIds,
                    'service_point_id_from_url' => $servicePointIdFromUrl,
                    'count' => count($admissionServicePointIds)
                ]);
            }
            
            // Find queued items to process
            // If queue_item_id is provided, process only that specific item
            // Otherwise, process all items at the identified service points
            $queueItemId = $request->get('queue_item_id');
            
            if ($queueItemId) {
                // Process only the specific queue item
                $queuedItemsAtAdmission = \App\Models\ServiceDeliveryQueue::where('id', $queueItemId)
                    ->where('client_id', $client->id)
                    ->whereIn('status', ['pending', 'partially_done'])
                    ->with(['invoice', 'item', 'servicePoint'])
                    ->get();
                
                \Illuminate\Support\Facades\Log::info("🎯 ADMISSION: Processing Specific Queue Item", [
                    'queue_item_id' => $queueItemId,
                    'client_id' => $client->id,
                    'found' => $queuedItemsAtAdmission->count() > 0
                ]);
            } else {
                // Find all queued items at the identified service points for this client
                $queuedItemsAtAdmission = \App\Models\ServiceDeliveryQueue::where('client_id', $client->id)
                    ->whereIn('status', ['pending', 'partially_done'])
                    ->when(!empty($admissionServicePointIds), function($query) use ($admissionServicePointIds) {
                        $query->whereIn('service_point_id', $admissionServicePointIds);
                    })
                    ->with(['invoice', 'item', 'servicePoint'])
                    ->get();
            }
            
            \Illuminate\Support\Facades\Log::info("🎯 ADMISSION: Found Queued Items at Service Points", [
                'client_id' => $client->id,
                'service_point_ids' => $admissionServicePointIds,
                'queued_items_count' => $queuedItemsAtAdmission->count(),
                'items' => $queuedItemsAtAdmission->map(function($item) {
                    return [
                        'queue_id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_name' => $item->item->name ?? 'Unknown',
                        'status' => $item->status,
                        'invoice_id' => $item->invoice_id,
                        'service_point_id' => $item->service_point_id,
                        'service_point_name' => $item->servicePoint->name ?? 'Unknown'
                    ];
                })->toArray()
            ]);
            
            // Get unique invoice IDs
            $invoiceIds = $queuedItemsAtAdmission->pluck('invoice_id')->unique()->filter();
            
            // Get invoices
            $pendingInvoices = \App\Models\Invoice::whereIn('id', $invoiceIds)
                ->where('status', '!=', 'cancelled')
                ->with('items')
                ->get();
            
            // Process money movements for all admission items (no credit check - money movements are the same for everyone)
            \Illuminate\Support\Facades\Log::info("=== ADMISSION: PROCESSING SUSPENSE MOVEMENTS FOR PENDING INVOICES ===", [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'pending_invoices_count' => $pendingInvoices->count()
            ]);
            
            foreach ($pendingInvoices as $invoice) {
                    \Illuminate\Support\Facades\Log::info("📄 ADMISSION: Processing Invoice", [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'invoice_status' => $invoice->status,
                        'invoice_total_amount' => $invoice->total_amount,
                        'invoice_service_charge' => $invoice->service_charge ?? 0,
                        'invoice_items_count' => $invoice->items ? count($invoice->items) : 0
                    ]);
                    
                    // Get items from service delivery queue (pending items at admission service point)
                    // IMPORTANT: Only process items at the admission service point for this invoice
                    $queuedItems = $queuedItemsAtAdmission->where('invoice_id', $invoice->id);
                    
                    \Illuminate\Support\Facades\Log::info("🎯 ADMISSION: Filtering Items for Service Points", [
                        'invoice_id' => $invoice->id,
                        'service_point_ids' => $admissionServicePointIds,
                        'queued_items_count' => $queuedItems->count(),
                        'queued_items_details' => $queuedItems->map(function($item) {
                            return [
                                'queue_id' => $item->id,
                                'item_id' => $item->item_id,
                                'item_name' => $item->item->name ?? 'Unknown',
                                'service_point_id' => $item->service_point_id,
                                'service_point_name' => $item->servicePoint->name ?? 'Unknown',
                                'status' => $item->status
                            ];
                        })->toArray()
                    ]);
                    
                    \Illuminate\Support\Facades\Log::info("📦 ADMISSION: Found Queued Items", [
                        'invoice_id' => $invoice->id,
                        'queued_items_count' => $queuedItems->count(),
                        'queued_items_details' => $queuedItems->map(function($item) {
                            return [
                                'queue_id' => $item->id,
                                'item_id' => $item->item_id,
                                'item_name' => $item->item->name ?? 'Unknown',
                                'quantity' => $item->quantity,
                                'price' => $item->price,
                                'total' => $item->price * $item->quantity,
                                'status' => $item->status,
                                'service_point_id' => $item->service_point_id
                            ];
                        })->toArray()
                    ]);
                    
                    if ($queuedItems->isEmpty()) {
                        \Illuminate\Support\Facades\Log::info("⏭️ ADMISSION: Skipping Invoice - No Queued Items", [
                            'invoice_id' => $invoice->id
                        ]);
                        continue;
                    }
                    
                    // Prepare item data for processSaveAndExit (same format as when marking items as completed)
                    $itemDataArray = $queuedItems->map(function($queuedItem) {
                        return [
                            'item_id' => $queuedItem->item_id,
                            'id' => $queuedItem->item_id,
                            'quantity' => $queuedItem->quantity,
                            'price' => $queuedItem->price,
                            'total_amount' => $queuedItem->price * $queuedItem->quantity
                        ];
                    })->toArray();
                    
                    \Illuminate\Support\Facades\Log::info("📊 ADMISSION: Prepared Item Data for Money Movement", [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'queued_items_count' => $queuedItems->count(),
                        'item_data_array' => $itemDataArray,
                        'total_items_amount' => array_sum(array_column($itemDataArray, 'total_amount'))
                    ]);
                    
                    // First, ensure suspense account movements are processed (money TO suspense accounts)
                    // This is critical - suspense movements must exist before we can move money from suspense to final
                    $invoiceItems = collect($invoice->items ?? [])->map(function($item) {
                        return [
                            'item_id' => $item['id'] ?? $item['item_id'] ?? null,
                            'id' => $item['id'] ?? $item['item_id'] ?? null,
                            'name' => $item['name'] ?? $item['item_name'] ?? null,
                            'displayName' => $item['displayName'] ?? $item['name'] ?? $item['item_name'] ?? null,
                            'quantity' => $item['quantity'] ?? 1,
                            'price' => $item['price'] ?? $item['default_price'] ?? 0,
                            'total_amount' => ($item['price'] ?? $item['default_price'] ?? 0) * ($item['quantity'] ?? 1)
                        ];
                    })->filter(function($item) {
                        // Exclude deposit items
                        $name = strtolower(trim($item['name'] ?? $item['displayName'] ?? ''));
                        return $name !== 'deposit';
                    })->values()->toArray();
                    
                    if (!empty($invoiceItems)) {
                        // Check if suspense movements already processed for this invoice
                        // We need transfers with the correct transfer_type for credit clients
                        $hasSuspenseMovements = \App\Models\MoneyTransfer::where('invoice_id', $invoice->id)
                            ->whereIn('transfer_type', ['suspense_movement', 'credit_suspense_movement', 'service_charge', 'credit_service_charge'])
                            ->exists();
                        
                        if (!$hasSuspenseMovements) {
                            \Illuminate\Support\Facades\Log::info("=== ADMISSION: PROCESSING SUSPENSE MOVEMENTS FOR INVOICE ===", [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'items_count' => count($invoiceItems),
                                'client_is_credit_eligible' => $client->is_credit_eligible
                            ]);
                            
                            // Process suspense account movements for this invoice
                            // This will create transfers with credit_suspense_movement type for credit clients
                            $suspenseMovements = $moneyTrackingService->processSuspenseAccountMovements($invoice, $invoiceItems);
                            
                            \Illuminate\Support\Facades\Log::info("=== ADMISSION: SUSPENSE MOVEMENTS PROCESSED ===", [
                                'invoice_id' => $invoice->id,
                                'movements_count' => count($suspenseMovements),
                                'movements' => $suspenseMovements
                            ]);
                        } else {
                            \Illuminate\Support\Facades\Log::info("=== ADMISSION: SUSPENSE MOVEMENTS ALREADY EXIST ===", [
                                'invoice_id' => $invoice->id,
                                'note' => 'Suspense movements already processed, proceeding to suspense-to-final movement'
                            ]);
                        }
                        
                        // Refresh suspense account balances to ensure they're up to date
                        $generalSuspense = $moneyTrackingService->getOrCreateGeneralSuspenseAccount($invoice->business, $client->id);
                        $packageSuspense = $moneyTrackingService->getOrCreatePackageSuspenseAccount($invoice->business, $client->id);
                        $kashtreSuspense = $moneyTrackingService->getOrCreateKashtreSuspenseAccount($invoice->business, $client->id);
                        
                        \Illuminate\Support\Facades\Log::info("=== ADMISSION: SUSPENSE ACCOUNT BALANCES BEFORE FINAL MOVEMENT ===", [
                            'invoice_id' => $invoice->id,
                            'general_suspense_balance' => $generalSuspense->fresh()->balance,
                            'package_suspense_balance' => $packageSuspense->fresh()->balance,
                            'kashtre_suspense_balance' => $kashtreSuspense->fresh()->balance,
                            'total_suspense_balance' => $generalSuspense->fresh()->balance + $packageSuspense->fresh()->balance + $kashtreSuspense->fresh()->balance
                        ]);
                    }
                    
                    // Then, process suspense to final money movement (same as when marking items as completed)
                    // This moves money FROM suspense accounts TO final accounts
                    \Illuminate\Support\Facades\Log::info("=== ADMISSION: PROCESSING SUSPENSE TO FINAL MONEY MOVEMENT ===", [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'items_count' => count($itemDataArray),
                        'item_status' => 'completed' // Treat admission as completed for money movement
                    ]);
                    
                    $transferRecords = $moneyTrackingService->processSaveAndExit($invoice, $itemDataArray, 'completed');
                    
                    \Illuminate\Support\Facades\Log::info("✅ ADMISSION: Money Movements Completed", [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'transfer_records_count' => count($transferRecords['transfer_records'] ?? []),
                        'transfer_records' => $transferRecords['transfer_records'] ?? []
                    ]);
                    
                    // Mark all queued items as completed (same as clicking "Completed" button)
                    $itemsMarkedCount = 0;
                    foreach ($queuedItems as $queuedItem) {
                        if ($queuedItem->status !== 'completed') {
                            \Illuminate\Support\Facades\Log::info("✅ ADMISSION: Marking Item as Completed", [
                                'queue_id' => $queuedItem->id,
                                'item_id' => $queuedItem->item_id,
                                'item_name' => $queuedItem->item->name ?? 'Unknown',
                                'invoice_id' => $invoice->id,
                                'previous_status' => $queuedItem->status,
                                'marked_by_user_id' => $user->id
                            ]);
                            
                            $queuedItem->markAsCompleted($user->id);
                            $itemsMarkedCount++;
                        } else {
                            \Illuminate\Support\Facades\Log::info("ℹ️ ADMISSION: Item Already Completed", [
                                'queue_id' => $queuedItem->id,
                                'item_id' => $queuedItem->item_id,
                                'invoice_id' => $invoice->id
                            ]);
                        }
                    }
                    
                    \Illuminate\Support\Facades\Log::info("✅ ADMISSION: All Items Processed for Invoice", [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'total_queued_items' => $queuedItems->count(),
                        'items_marked_as_completed' => $itemsMarkedCount,
                        'items_already_completed' => $queuedItems->count() - $itemsMarkedCount
                    ]);
                }
                
            \Illuminate\Support\Facades\Log::info("✅ ADMISSION: All Invoices Processed Successfully", [
                'client_id' => $client->id,
                'total_invoices_processed' => $pendingInvoices->count()
            ]);
            
            // IMPORTANT: Mark all admission items as completed
            // This ensures items disappear from the queue after admission
            $itemsMarkedCount = 0;
            foreach ($queuedItemsAtAdmission as $queuedItem) {
                if ($queuedItem->status !== 'completed') {
                    \Illuminate\Support\Facades\Log::info("✅ ADMISSION: Marking Admission Item as Completed", [
                        'queue_id' => $queuedItem->id,
                        'item_id' => $queuedItem->item_id,
                        'item_name' => $queuedItem->item->name ?? 'Unknown',
                        'previous_status' => $queuedItem->status,
                        'marked_by_user_id' => $user->id
                    ]);
                    
                    $queuedItem->markAsCompleted($user->id);
                    $itemsMarkedCount++;
                }
            }
            
            \Illuminate\Support\Facades\Log::info("✅ ADMISSION: All Admission Items Marked as Completed", [
                'client_id' => $client->id,
                'total_admission_items' => $queuedItemsAtAdmission->count(),
                'items_marked_as_completed' => $itemsMarkedCount,
                'items_already_completed' => $queuedItemsAtAdmission->count() - $itemsMarkedCount
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("❌ ADMISSION: Error Processing Admission", [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);
            // Don't fail admission if processing fails
        }

        $message = 'Client admitted successfully.';
        if ($enableCredit && $enableLongStay) {
            $message .= ' Credit and Long-Stay enabled.';
        } elseif ($enableCredit) {
            $message .= ' Credit enabled.';
        } elseif ($enableLongStay) {
            $message .= ' Long-Stay enabled.';
        }

        \Illuminate\Support\Facades\Log::info("🎉 ========== ADMISSION PROCESS COMPLETED SUCCESSFULLY ==========", [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_client_id' => $client->client_id,
            'client_visit_id' => $client->visit_id,
            'is_credit_eligible' => $client->is_credit_eligible,
            'is_long_stay' => $client->is_long_stay,
            'max_credit' => $client->max_credit,
            'success_message' => $message,
            'redirect_to' => $request->get('redirect_to', route('clients.show', $client))
        ]);

        // Redirect back to the page they came from, or default to client show page
        $redirectTo = $request->get('redirect_to', route('clients.show', $client));
        return redirect($redirectTo)
            ->with('success', $message);
    }

    /**
     * Discharge a client - remove long-stay status
     */
    public function discharge(Request $request, Client $client)
    {
        $user = Auth::user();
        $business = $user->business;
        
        // Check permission
        if (!in_array('Discharge Clients', $user->permissions ?? [])) {
            $redirectTo = $request->get('redirect_to', route('clients.show', $client));
            return redirect($redirectTo)
                ->with('error', 'You do not have permission to discharge clients.');
        }
        
        // Check if user has access to this client
        if ($user->business_id !== 1 && $client->business_id !== $business->id) {
            $redirectTo = $request->get('redirect_to', route('clients.show', $client));
            return redirect($redirectTo)
                ->with('error', 'Unauthorized access to client.');
        }

        // Check if client has /M suffix (long-stay)
        if (!$client->is_long_stay && !preg_match('/\/M$/', $client->visit_id)) {
            $redirectTo = $request->get('redirect_to', route('clients.show', $client));
            return redirect($redirectTo)
                ->with('error', 'Client is not admitted (no long-stay status).');
        }

        // Determine what to remove based on business settings
        $removeLongStay = $business->discharge_remove_long_stay ?? true; // Always true by default
        $removeCredit = $business->discharge_remove_credit ?? false;
        
        // Remove long-stay flag if configured
        if ($removeLongStay) {
            $client->is_long_stay = false;
        }
        
        // Remove credit eligibility if configured
        if ($removeCredit) {
            $client->is_credit_eligible = false;
            $client->max_credit = null;
        }
        
        // Determine final credit and long-stay states for visit ID generation
        $finalCreditEligible = $removeCredit ? false : ($client->is_credit_eligible ?? false);
        $finalLongStay = $removeLongStay ? false : ($client->is_long_stay ?? false);

        // Regenerate visit ID based on final states
        $branch = $client->branch ?: Branch::find($client->branch_id);
        if ($business && $branch) {
            $client->visit_id = Client::generateVisitId($business, $branch, $finalCreditEligible, $finalLongStay);
            
            // Set visit_expires_at to tomorrow for non-long-stay clients
            if ($finalLongStay) {
                $client->visit_expires_at = null;
            } else {
                $client->visit_expires_at = \Carbon\Carbon::tomorrow()->startOfDay();
            }
        }

        $client->save();

        // Build success message
        $message = 'Client discharged successfully.';
        $changes = [];
        if ($removeLongStay) {
            $changes[] = 'Long-stay removed';
        }
        if ($removeCredit) {
            $changes[] = 'Credit services removed';
        }
        if (!empty($changes)) {
            $message .= ' ' . implode(', ', $changes) . '.';
        }
        $message .= ' Visit ID is now available for reissuance.';

        // Redirect back to the page they came from, or default to client show page
        $redirectTo = $request->get('redirect_to', route('clients.show', $client));
        return redirect($redirectTo)
            ->with('success', $message);
    }

    /**
     * Get insurance company details by code (API endpoint)
     */
    public function getInsuranceCompanyByCode(string $code)
    {
        try {
            // Validate code format (8-character alphanumeric)
            if (!preg_match('/^[A-Z0-9]{8}$/', strtoupper($code))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid code format. Code must be exactly 8 characters (uppercase letters and numbers).',
                ], 422);
            }
            
            // Convert to uppercase for consistency
            $code = strtoupper($code);

            $apiService = new ThirdPartyApiService();
            $insuranceCompanyData = $apiService->getInsuranceCompanyByCode($code);

            if (!$insuranceCompanyData || !isset($insuranceCompanyData['business'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Third party vendor not found with code: ' . $code,
                ], 404);
            }

            $business = $insuranceCompanyData['business'];

            // Also look up the local Kashtre record to get TIN
            $localCompany = \App\Models\InsuranceCompany::where('code', $code)->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'name' => $business['name'] ?? '',
                    'email' => $business['email'] ?? '',
                    'phone' => $business['phone'] ?? '',
                    'head_office_address' => $business['head_office_address'] ?? '',
                    'postal_address' => $business['postal_address'] ?? '',
                    'website' => $business['website'] ?? '',
                    'tin' => $localCompany?->tin ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get third party vendor by code', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching third party vendor details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get insurance company settings from third-party (API endpoint for frontend use)
     */
    public function getInsuranceCompanySettingsApi($insuranceCompanyId)
    {
        try {
            $insuranceCompany = \App\Models\InsuranceCompany::findOrFail($insuranceCompanyId);
            $apiService = new ThirdPartyApiService();
            $settings = $apiService->getInsuranceCompanySettings((int) $insuranceCompany->third_party_business_id);

            return response()->json($settings);
        } catch (\Exception $e) {
            Log::error('Failed to fetch insurance company settings', [
                'insurance_company_id' => $insuranceCompanyId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['open_enrollment' => ['enabled' => false]], 200);
        }
    }

    /**
     * Verify policy number exists (API endpoint)
     * Supports both GET (policy number only) and POST (with alternative verification data)
     */
    public function verifyPolicyNumber(Request $request, $insuranceCompanyId, $policyNumber = null)
    {
        try {
            Log::info('=== Kashtre Controller: verifyPolicyNumber START ===', [
                'insurance_company_id' => $insuranceCompanyId,
                'route_policy_number' => $policyNumber,
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'request_all' => $request->all(),
                'query_params' => $request->query(),
            ]);
            
            $apiService = new ThirdPartyApiService();
            // Resolve Kashtre-local insurance company ID -> third-party business ID once
            // and reuse it for both primary and alternative verification paths.
            $localInsuranceCompany = \App\Models\InsuranceCompany::find($insuranceCompanyId);
            $thirdPartyInsuranceId = (int)($localInsuranceCompany?->third_party_business_id ?? $insuranceCompanyId);
            
            // Get policy number from route parameter or request
            $policyNumber = $policyNumber ?? $request->input('policy_number');
            $servicesCategory = $request->input('services_category');
            
            Log::info('Kashtre Controller: Policy number extracted', [
                'policy_number' => $policyNumber,
                'has_policy_number' => !empty($policyNumber),
            ]);
            
            // Try policy number verification first if provided
            if ($policyNumber) {
                // Get name and DOB from request for tolerance-based verification
                $name = $request->input('name');
                $dateOfBirth = $request->input('date_of_birth');

                // Extra params forwarded to third-party for open enrollment criteria evaluation
                // and service category exclusion checks per connected company
                $kashtreBusiness = \App\Models\Business::first();
                $extraParams = array_filter([
                    'gender'               => $request->input('gender'),
                    'nationality'          => $request->input('nationality'),
                    'marital_status'       => $request->input('marital_status'),
                    'client_type'          => $request->input('client_type'),
                    'invoice_amount'       => $request->input('invoice_amount'),
                    'connected_business_id'=> $kashtreBusiness?->id,
                ], fn($v) => $v !== null && $v !== '');

                Log::info('Kashtre Controller: Calling API service for policy verification', [
                    'kashtre_insurance_company_id' => $insuranceCompanyId,
                    'third_party_insurance_id'     => $thirdPartyInsuranceId,
                    'policy_number' => $policyNumber,
                    'name' => $name,
                    'date_of_birth' => $dateOfBirth,
                    'services_category' => $servicesCategory,
                    'extra_params' => $extraParams,
                ]);

                $verificationResult = $apiService->verifyPolicyNumber(
                    $thirdPartyInsuranceId,
                    $policyNumber,
                    $name,
                    $dateOfBirth,
                    $servicesCategory,
                    $extraParams
                );
                
                Log::info('Kashtre Controller: Received verification result from API service', [
                    'has_result' => !empty($verificationResult),
                    'result' => $verificationResult,
                ]);
                
                if ($verificationResult && isset($verificationResult['success']) && $verificationResult['success']) {
                    $responseData = [
                        'success' => true,
                        'message' => $verificationResult['message'] ?? 'Policy number verified',
                        'exists' => true,
                        'verification_method' => $verificationResult['verification_method'] ?? 'policy_number',
                        'verification_status' => $verificationResult['verification_status'] ?? 'verified',
                        'data' => $verificationResult['data'] ?? null,
                        'warnings' => $verificationResult['warnings'] ?? [],
                    ];

                    Log::info('Kashtre Controller: Returning SUCCESS response', [
                        'response_data' => $responseData,
                    ]);

                    return response()->json($responseData, 200);
                } elseif ($verificationResult && isset($verificationResult['success']) && !$verificationResult['success']) {
                    // Third-party returned an error — surface it directly (covers open enrollment
                    // criteria-not-met, policy rejected, not found, etc.)
                    $errorResponse = [
                        'success' => false,
                        'message' => $verificationResult['message'] ?? 'Verification failed.',
                        'exists' => $verificationResult['exists'] ?? false,
                        'verification_status' => $verificationResult['verification_status'] ?? 'rejected',
                        'mismatches' => $verificationResult['mismatches'] ?? [],
                        'details' => $verificationResult['details'] ?? [],
                    ];

                    Log::warning('Kashtre Controller: Verification error from third-party', [
                        'response_data' => $errorResponse,
                    ]);

                    // Always return 200 OK - the route exists and responded properly.
                    // Check the 'success' and 'exists' fields in the response to determine actual status.
                    return response()->json($errorResponse, 200);
                }
            }
            
            // If policy number verification failed, try alternative methods (name + DOB only, with optional services_category)
            $alternativeData = $request->only([
                'name', 'date_of_birth', 'services_category'
            ]);
            
            // Remove empty values
            $alternativeData = array_filter($alternativeData);
            
            Log::info('Kashtre Controller: Attempting alternative verification', [
                'has_alternative_data' => !empty($alternativeData),
                'alternative_data' => $alternativeData,
            ]);
            
            // Only try alternative verification if we have both name and DOB
            if (!empty($alternativeData['name']) && !empty($alternativeData['date_of_birth'])) {
                $verificationResult = $apiService->verifyAlternativeIdentity($thirdPartyInsuranceId, $alternativeData);
                
                Log::info('Kashtre Controller: Received alternative verification result', [
                    'has_result' => !empty($verificationResult),
                    'result' => $verificationResult,
                ]);
                
                if ($verificationResult && isset($verificationResult['success']) && $verificationResult['success']) {
                    $responseData = [
                        'success' => true,
                        'message' => $verificationResult['message'] ?? 'Client verified using alternative method',
                        'exists' => true,
                        'verification_method' => $verificationResult['verification_method'] ?? 'alternative',
                        'verification_status' => $verificationResult['verification_status'] ?? 'verified',
                        'data' => $verificationResult['data'] ?? null,
                        'warnings' => $verificationResult['warnings'] ?? [],
                    ];
                    
                    Log::info('Kashtre Controller: Alternative verification SUCCESS', [
                        'response_data' => $responseData,
                    ]);
                    
                    return response()->json($responseData, 200);
                } else {
                    // Alternative verification failed
                    $message = $verificationResult['message'] ?? 'Policy number not found and alternative verification failed';
                    $status = $verificationResult['verification_status'] ?? 'not_found';
                    
                    $errorResponse = [
                        'success' => false,
                        'message' => $message,
                        'exists' => false,
                        'verification_status' => $status,
                        'mismatches' => $verificationResult['mismatches'] ?? [],
                        'requires_alternative_verification' => empty($policyNumber),
                    ];
                    
                    Log::warning('Kashtre Controller: Alternative verification FAILED', [
                        'response_data' => $errorResponse,
                    ]);
                    
                    return response()->json($errorResponse, 200);
                }
            }
            
            // No alternative data provided
            $errorResponse = [
                'success' => false,
                'message' => $policyNumber 
                    ? 'Policy number not found or inactive. Please provide alternative verification information.'
                    : 'Please provide a policy number or alternative verification information.',
                'exists' => false,
                'requires_alternative_verification' => true,
            ];
            
            Log::warning('Kashtre Controller: No verification data provided', [
                'response_data' => $errorResponse,
            ]);
            
            return response()->json($errorResponse, 200);
            
        } catch (\Exception $e) {
            $errorResponse = [
                'success' => false,
                'message' => 'An error occurred while verifying policy number.',
                'error' => $e->getMessage(),
            ];
            
            Log::error('Kashtre Controller: Exception in verifyPolicyNumber', [
                'insurance_company_id' => $insuranceCompanyId,
                'policy_number' => $policyNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'response_data' => $errorResponse,
            ]);

            return response()->json($errorResponse, 500);
        } finally {
            Log::info('=== Kashtre Controller: verifyPolicyNumber END ===');
        }
    }
}
