<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\InsurerPortalVendorSummaryService;
use Illuminate\Http\Request;

class InsurerPortalVendorController extends Controller
{
    /**
     * Financial summary + recent ledger rows + invoices + exclusions for the insurer portal (third-party app).
     *
     * thirdPartyVendorId is the third-party insurance company id (same id used on Kashtre third-party-vendors/{id}).
     */
    public function summary(int $businessId, int $thirdPartyVendorId, InsurerPortalVendorSummaryService $service)
    {
        if (!Business::whereKey($businessId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found.',
            ], 404);
        }

        $payload = $service->buildSummaryPayload($businessId, $thirdPartyVendorId);

        if (isset($payload['success']) && $payload['success'] === false) {
            return response()->json([
                'success' => false,
                'message' => $payload['message'] ?? 'Unable to load summary.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function balanceHistory(Request $request, int $businessId, int $thirdPartyVendorId, InsurerPortalVendorSummaryService $service)
    {
        if (!Business::whereKey($businessId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found.',
            ], 404);
        }

        $perPage = (int) $request->query('per_page', 50);
        $paginator = $service->paginatedBalanceHistory($businessId, $thirdPartyVendorId, $perPage);

        return response()->json([
            'success' => true,
            'data' => $service->formatBalanceHistoryPage($paginator),
        ]);
    }
}
