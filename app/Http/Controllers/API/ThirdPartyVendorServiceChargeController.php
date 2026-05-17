<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\ThirdPartyVendorServiceChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThirdPartyVendorServiceChargeController extends Controller
{
    public function __construct(
        protected ThirdPartyVendorServiceChargeService $serviceCharges
    ) {}

    /**
     * List saved tiers + effective schedule + recommended defaults for a clinic.
     * Query: insurance_company_id (Kashtre) or third_party_vendor_id (insurer portal id).
     */
    public function index(Request $request, int $businessId): JsonResponse
    {
        if (! $this->businessExists($businessId)) {
            return $this->notFoundBusiness();
        }

        $insuranceCompanyId = $this->resolveInsuranceCompanyIdFromRequest($request, $businessId);

        $clinicSaved = $this->serviceCharges->serializeTierCollection(
            $this->serviceCharges->savedTiers($businessId, null)
        );
        $vendorSaved = $insuranceCompanyId !== null
            ? $this->serviceCharges->serializeTierCollection(
                $this->serviceCharges->savedTiers($businessId, $insuranceCompanyId)
            )
            : [];

        return response()->json([
            'success' => true,
            'data' => [
                'business_id' => $businessId,
                'insurance_company_id' => $insuranceCompanyId,
                'recommended_defaults' => $this->serviceCharges->recommendedDefaults(),
                'saved_tiers' => [
                    'clinic_wide' => $clinicSaved,
                    'vendor_specific' => $vendorSaved,
                ],
                'effective_schedule' => $this->serviceCharges->effectiveSchedule($businessId, $insuranceCompanyId),
            ],
        ]);
    }

    /**
     * Recommended default tier template (from Kashtre config).
     */
    public function recommendedDefaults(int $businessId): JsonResponse
    {
        if (! $this->businessExists($businessId)) {
            return $this->notFoundBusiness();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'business_id' => $businessId,
                'recommended_defaults' => $this->serviceCharges->recommendedDefaults(),
            ],
        ]);
    }

    /**
     * Effective service-charge schedule for one third-party vendor (insurer portal id).
     */
    public function forVendor(int $businessId, int $thirdPartyVendorId): JsonResponse
    {
        if (! $this->businessExists($businessId)) {
            return $this->notFoundBusiness();
        }

        $insuranceCompanyId = $this->serviceCharges->resolveLocalInsuranceCompanyId($businessId, $thirdPartyVendorId);

        return response()->json([
            'success' => true,
            'data' => [
                'business_id' => $businessId,
                'third_party_vendor_id' => $thirdPartyVendorId,
                'insurance_company_id' => $insuranceCompanyId,
                'recommended_defaults' => $this->serviceCharges->recommendedDefaults(),
                'effective_schedule' => $this->serviceCharges->effectiveSchedule($businessId, $insuranceCompanyId),
            ],
        ]);
    }

    /**
     * Calculate service charge for a subtotal using vendor/clinic/default tiers.
     */
    public function calculate(Request $request, int $businessId): JsonResponse
    {
        if (! $this->businessExists($businessId)) {
            return $this->notFoundBusiness();
        }

        $validated = $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'insurance_company_id' => 'nullable|integer|exists:insurance_companies,id',
            'third_party_vendor_id' => 'nullable|integer|min:1',
        ]);

        $insuranceCompanyId = isset($validated['insurance_company_id'])
            ? (int) $validated['insurance_company_id']
            : null;

        if ($insuranceCompanyId === null && ! empty($validated['third_party_vendor_id'])) {
            $insuranceCompanyId = $this->serviceCharges->resolveLocalInsuranceCompanyId(
                $businessId,
                (int) $validated['third_party_vendor_id']
            );
        }

        $result = $this->serviceCharges->calculate(
            $businessId,
            (float) $validated['subtotal'],
            $insuranceCompanyId
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($result, [
                'business_id' => $businessId,
                'insurance_company_id' => $insuranceCompanyId,
                'formatted_service_charge' => 'UGX '.number_format((float) $result['service_charge'], 2),
            ]),
        ]);
    }

    protected function businessExists(int $businessId): bool
    {
        return Business::whereKey($businessId)->exists();
    }

    protected function notFoundBusiness(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Business not found.',
        ], 404);
    }

    protected function resolveInsuranceCompanyIdFromRequest(Request $request, int $businessId): ?int
    {
        if ($request->filled('insurance_company_id')) {
            return (int) $request->input('insurance_company_id');
        }

        if ($request->filled('third_party_vendor_id')) {
            return $this->serviceCharges->resolveLocalInsuranceCompanyId(
                $businessId,
                (int) $request->input('third_party_vendor_id')
            );
        }

        return null;
    }
}
