<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientVendor;
use App\Models\InsuranceCompany;
use App\Models\ThirdPartyPayer;
use Illuminate\Support\Facades\Log;

class MultiVendorClientService
{
    private $apiService;

    public function __construct()
    {
        $this->apiService = new ThirdPartyApiService();
    }

    /**
     * Attach multiple vendors to a client with vendor-specific settings.
     *
     * $vendorData keys are Kashtre insurance_companies.id values.
     * We resolve the correct ThirdPartyPayer and third-party business ID here.
     */
    public function attachMultipleVendors(Client $client, array $vendorData): array
    {
        Log::info('SERVICE: attachMultipleVendors called', [
            'client_id' => $client->id,
            'client_client_id' => $client->client_id,
            'vendorData_count' => count($vendorData),
            'vendorData_keys' => array_keys($vendorData),
            'vendorData' => $vendorData,
        ]);

        $results = [
            'success' => [],
            'failed' => [],
        ];

        // Assign cascade priority: 
        // 1. If priority is explicitly provided in data, use that (form-specified)
        // 2. Otherwise: non-OE vendors get lower numbers (go first),
        //    OE vendors go last. Within each group, preserve submission order.
        $nonOE = [];
        $oe    = [];
        foreach ($vendorData as $id => $data) {
            if (!empty($data['is_open_enrollment'])) {
                $oe[$id] = $data;
            } else {
                $nonOE[$id] = $data;
            }
        }
        $orderedVendorData = $nonOE + $oe;
        $priorityCounter   = 1;

        Log::debug('SERVICE: Vendor ordering', [
            'nonOE_count' => count($nonOE),
            'oe_count' => count($oe),
            'orderedVendorData_keys' => array_keys($orderedVendorData),
        ]);

        foreach ($orderedVendorData as $insuranceCompanyId => $data) {
            // Use priority from form if provided, otherwise auto-assign
            $assignedPriority = isset($data['priority']) ? (int) $data['priority'] : $priorityCounter++;
            
            Log::debug('SERVICE: Processing vendor', [
                'iteration' => $assignedPriority,
                'insuranceCompanyId' => $insuranceCompanyId,
                'data' => $data,
                'priorityFromForm' => isset($data['priority']),
            ]);
            
            try {
                // Resolve the local InsuranceCompany (keyed by insurance_companies.id)
                $insuranceCompany = InsuranceCompany::find($insuranceCompanyId);
                if (!$insuranceCompany) {
                    $results['failed'][$insuranceCompanyId] = 'Insurance company not found (id: ' . $insuranceCompanyId . ')';
                    continue;
                }

                // Resolve or create the ThirdPartyPayer for this insurance company
                $thirdPartyPayer = ThirdPartyPayer::firstOrCreate(
                    [
                        'insurance_company_id' => $insuranceCompanyId,
                        'business_id'          => $client->business_id,
                    ],
                    [
                        'name'   => $insuranceCompany->name,
                        'type'   => 'insurance_company',
                        'status' => 'active',
                    ]
                );

                if ($thirdPartyPayer->isSuspended() || $thirdPartyPayer->isBlocked()) {
                    $results['failed'][$insuranceCompanyId] = "Vendor is {$thirdPartyPayer->status}. Cannot register client with this vendor.";
                    continue;
                }

                // The third-party system's own ID for this insurance company
                $thirdPartyBusinessId = (int) ($insuranceCompany->third_party_business_id ?? 0);
                if (!$thirdPartyBusinessId) {
                    $results['failed'][$insuranceCompanyId] = 'Insurance company has no third_party_business_id configured';
                    continue;
                }

                // Existing mapping (if any) so we never wipe policy data when form payload is partial.
                $existingClientVendor = ClientVendor::where('client_id', $client->id)
                    ->where('third_party_payer_id', $thirdPartyPayer->id)
                    ->first();

                $isOpenEnrollment = !empty($data['is_open_enrollment']);

                // Verify policy with API and extract payment responsibility
                $policyNumber = isset($data['policy_number']) ? trim((string) $data['policy_number']) : null;
                if (($policyNumber === null || $policyNumber === '') && $existingClientVendor && !empty($existingClientVendor->policy_number)) {
                    $policyNumber = trim((string) $existingClientVendor->policy_number);
                }

                // In multi-insurance, non-open-enrollment vendors must always have a policy number.
                // Never create/update a regular vendor mapping with a blank policy.
                if (!$isOpenEnrollment && empty($policyNumber)) {
                    $results['failed'][$insuranceCompanyId] = 'Policy number is required for this vendor.';
                    Log::warning('SERVICE: Missing policy number for non-open-enrollment vendor', [
                        'client_id' => $client->id,
                        'insurance_company_id' => $insuranceCompanyId,
                        'third_party_payer_id' => $thirdPartyPayer->id,
                        'existing_client_vendor_id' => $existingClientVendor?->id,
                    ]);
                    continue;
                }

                $paymentResponsibility = null;
                $policyVerified = $existingClientVendor ? (bool) $existingClientVendor->policy_verified : false;

                if (!empty($policyNumber)) {
                    try {
                        $fullName = trim($client->surname . ' ' . $client->first_name . ' ' . ($client->other_names ?? ''));

                        $verificationResult = $this->apiService->verifyPolicyNumber(
                            $thirdPartyBusinessId,
                            $policyNumber,
                            !empty($fullName) ? $fullName : null,
                            $client->date_of_birth,
                            $client->services_category
                        );

                        if ($verificationResult && isset($verificationResult['success']) && $verificationResult['success']) {
                            $policyVerified = true;

                            if (isset($verificationResult['data']['payment_responsibility'])) {
                                $paymentResponsibility = $verificationResult['data']['payment_responsibility'];
                            }

                            Log::info('Policy verified for vendor', [
                                'client_id'           => $client->id,
                                'insurance_company_id' => $insuranceCompanyId,
                                'third_party_business_id' => $thirdPartyBusinessId,
                                'policy_number'       => $policyNumber,
                                'verification_method' => $verificationResult['verification_method'] ?? 'unknown',
                            ]);
                        } else {
                            Log::warning('Policy verification failed for vendor', [
                                'client_id'           => $client->id,
                                'insurance_company_id' => $insuranceCompanyId,
                                'policy_number'       => $policyNumber,
                                'error'               => $verificationResult['message'] ?? 'Unknown error',
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Exception during policy verification', [
                            'client_id'           => $client->id,
                            'insurance_company_id' => $insuranceCompanyId,
                            'policy_number'       => $policyNumber,
                            'error'               => $e->getMessage(),
                        ]);
                    }
                }

                // Create or update ClientVendor — keyed by the ThirdPartyPayer.id (not insurance_companies.id)
                $clientVendorData = [
                    'policy_number'                    => !empty($policyNumber) ? $policyNumber : ($existingClientVendor->policy_number ?? null),
                    'policy_verified'                  => $policyVerified,
                    'physical_insurance_card_verified' => $data['physical_insurance_card_verified'] ?? false,
                    'is_open_enrollment'               => $isOpenEnrollment,
                    'priority'                         => $assignedPriority,
                    'status'                           => 'active',
                ];

                if ($paymentResponsibility) {
                    $clientVendorData['deductible_amount']                      = $paymentResponsibility['deductible_amount'] ?? null;
                    $clientVendorData['copay_amount']                           = $paymentResponsibility['copay_amount'] ?? null;
                    $clientVendorData['coinsurance_percentage']                 = $paymentResponsibility['coinsurance_percentage'] ?? null;
                    $clientVendorData['copay_max_limit']                        = $paymentResponsibility['copay_max_limit'] ?? null;
                    $clientVendorData['copay_contributes_to_deductible']        = $paymentResponsibility['copay_contributes_to_deductible'] ?? false;
                    $clientVendorData['coinsurance_contributes_to_deductible']  = $paymentResponsibility['coinsurance_contributes_to_deductible'] ?? false;
                }

                Log::debug('SERVICE: Creating ClientVendor record', [
                    'client_id' => $client->id,
                    'third_party_payer_id' => $thirdPartyPayer->id,
                    'clientVendorData' => $clientVendorData,
                ]);

                $clientVendor = ClientVendor::updateOrCreate(
                    [
                        'client_id'           => $client->id,
                        'third_party_payer_id' => $thirdPartyPayer->id,
                    ],
                    $clientVendorData
                );
                
                Log::info('SERVICE: ClientVendor record created/updated', [
                    'client_vendor_id' => $clientVendor->id,
                    'client_id' => $client->id,
                    'third_party_payer_id' => $thirdPartyPayer->id,
                    'insuranceCompanyId' => $insuranceCompanyId,
                ]);

                $results['success'][$insuranceCompanyId] = [
                    'insurance_company_id'    => $insuranceCompanyId,
                    'third_party_payer_id'    => $thirdPartyPayer->id,
                    'vendor_name'             => $insuranceCompany->name,
                    'policy_number'           => $clientVendorData['policy_number'] ?? $policyNumber,
                    'policy_verified'         => $policyVerified,
                    'has_payment_responsibility' => $paymentResponsibility !== null,
                ];

                Log::info('Client attached to vendor with payment responsibility', [
                    'client_id'               => $client->id,
                    'insurance_company_id'    => $insuranceCompanyId,
                    'third_party_business_id' => $thirdPartyBusinessId,
                    'third_party_payer_id'    => $thirdPartyPayer->id,
                    'vendor_name'             => $insuranceCompany->name,
                    'policy_verified'         => $policyVerified,
                    'payment_responsibility'  => $paymentResponsibility ? 'extracted from API' : 'not available',
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to attach vendor to client', [
                    'client_id'           => $client->id,
                    'insurance_company_id' => $insuranceCompanyId,
                    'error'               => $e->getMessage(),
                ]);

                $results['failed'][$insuranceCompanyId] = "Error: {$e->getMessage()}";
            }
        }

        Log::info('SERVICE: attachMultipleVendors finished', [
            'client_id' => $client->id,
            'success_count' => count($results['success']),
            'failed_count' => count($results['failed']),
            'success_vendor_ids' => array_keys($results['success']),
            'failed_vendor_ids' => array_keys($results['failed']),
        ]);

        return $results;
    }

    /**
     * Register authorized visits for a client with multiple vendors.
     */
    public function registerAuthorizedVisitsMultiVendor(Client $client): array
    {
        $results = [
            'registered' => [],
            'failed'     => [],
        ];

        $vendors = $client->vendors()->where('status', 'active')->get();

        foreach ($vendors as $clientVendor) {
            try {
                if (!$clientVendor->policy_number) {
                    $results['failed'][$clientVendor->third_party_payer_id] = 'No policy number set for this vendor';
                    continue;
                }

                if (!$client->visit_id) {
                    $results['failed'][$clientVendor->third_party_payer_id] = 'Client has no visit_id — assign or generate a visit before registering with the insurer';
                    continue;
                }

                // Resolve the ThirdPartyPayer, then its InsuranceCompany, then the third-party system ID
                $thirdPartyPayer = ThirdPartyPayer::find($clientVendor->third_party_payer_id);
                if (!$thirdPartyPayer) {
                    $results['failed'][$clientVendor->third_party_payer_id] = 'ThirdPartyPayer record not found';
                    continue;
                }

                $insuranceCompany = $thirdPartyPayer->insuranceCompany;
                if (!$insuranceCompany || !$insuranceCompany->third_party_business_id) {
                    $results['failed'][$clientVendor->third_party_payer_id] = 'Insurance company has no third_party_business_id configured';
                    continue;
                }

                $thirdPartyBusinessId = (int) $insuranceCompany->third_party_business_id;

                // The client must exist on the third-party system before we can register an authorized visit.
                // Sync the client now for all vendors (both open enrollment and regular policy verification).
                $syncResult = $this->apiService->syncClientToVendor($client, $thirdPartyBusinessId);
                if (!$syncResult) {
                    Log::warning('Multi-vendor: client sync returned null — visit registration may fail', [
                        'client_id'               => $client->id,
                        'third_party_business_id' => $thirdPartyBusinessId,
                    ]);
                }

                $visitRegistrationResult = $this->apiService->registerAuthorizedVisit(
                    $client,
                    $client->visit_id,
                    now()->toDateString(),
                    $client->visit_expires_at ? $client->visit_expires_at->toDateTimeString() : null,
                    $client->services_category,
                    $thirdPartyBusinessId
                );

                if ($visitRegistrationResult) {
                    $results['registered'][$clientVendor->third_party_payer_id] = $visitRegistrationResult;

                    Log::info('Authorized visit registered for vendor', [
                        'client_id'               => $client->id,
                        'third_party_payer_id'    => $clientVendor->third_party_payer_id,
                        'third_party_business_id' => $thirdPartyBusinessId,
                        'visit_id'                => $client->visit_id,
                    ]);
                } else {
                    $results['failed'][$clientVendor->third_party_payer_id] = 'Visit registration failed';
                }

            } catch (\Exception $e) {
                Log::warning('Failed to register authorized visit for vendor', [
                    'client_id'           => $client->id,
                    'third_party_payer_id' => $clientVendor->third_party_payer_id,
                    'error'               => $e->getMessage(),
                ]);

                $results['failed'][$clientVendor->third_party_payer_id] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Handle item clearing/charges distribution across vendors.
     */
    public function configureItemClearingStrategy(Client $client, array $itemClearingConfig): array
    {
        $results = [
            'configured' => [],
            'failed'     => [],
        ];

        foreach ($itemClearingConfig as $vendorId => $itemsToClean) {
            try {
                $clientVendor = ClientVendor::where('client_id', $client->id)
                    ->where('third_party_payer_id', $vendorId)
                    ->firstOrFail();

                $allItems = $client->excluded_items ?? [];
                $itemsToExclude = array_diff($allItems, $itemsToClean);

                $clientVendor->update([
                    'excluded_items' => $itemsToExclude,
                ]);

                $results['configured'][$vendorId] = [
                    'clears_items'  => $itemsToClean,
                    'excludes_items' => $itemsToExclude,
                ];

                Log::info('Item clearing strategy configured for vendor', [
                    'client_id'     => $client->id,
                    'vendor_id'     => $vendorId,
                    'cleared_count' => count($itemsToClean),
                    'excluded_count' => count($itemsToExclude),
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to configure item clearing for vendor', [
                    'client_id' => $client->id,
                    'vendor_id' => $vendorId,
                    'error'     => $e->getMessage(),
                ]);

                $results['failed'][$vendorId] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get all active vendors for a client.
     */
    public function getActiveVendors(Client $client)
    {
        return $client->vendors()
            ->where('status', 'active')
            ->with('vendor')
            ->get();
    }

    /**
     * Suspend a client's relationship with a specific vendor.
     */
    public function suspendVendor(Client $client, int $vendorId, string $reason = null): bool
    {
        try {
            ClientVendor::where('client_id', $client->id)
                ->where('third_party_payer_id', $vendorId)
                ->update([
                    'status' => 'suspended',
                    'notes'  => $reason,
                ]);

            Log::info('Client vendor relationship suspended', [
                'client_id' => $client->id,
                'vendor_id' => $vendorId,
                'reason'    => $reason,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to suspend vendor for client', [
                'client_id' => $client->id,
                'vendor_id' => $vendorId,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Reactivate a client's relationship with a vendor.
     */
    public function reactivateVendor(Client $client, int $vendorId): bool
    {
        try {
            ClientVendor::where('client_id', $client->id)
                ->where('third_party_payer_id', $vendorId)
                ->update([
                    'status' => 'active',
                    'notes'  => null,
                ]);

            Log::info('Client vendor relationship reactivated', [
                'client_id' => $client->id,
                'vendor_id' => $vendorId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reactivate vendor for client', [
                'client_id' => $client->id,
                'vendor_id' => $vendorId,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }
}
