<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\InsurerPortalVendorPaymentService;
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

    public function previewPayment(Request $request, int $businessId, int $thirdPartyVendorId, InsurerPortalVendorPaymentService $payments)
    {
        if (! Business::whereKey($businessId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Business not found.'], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'history_ids' => 'nullable|array',
            'history_ids.*' => 'integer',
            'entry_ids' => 'nullable|array',
            'entry_ids.*' => 'integer',
        ]);

        $amount = (float) $validated['amount'];
        $historyIds = $validated['history_ids'] ?? $validated['entry_ids'] ?? [];
        $preview = $payments->previewCharge($businessId, $thirdPartyVendorId, $amount);
        $allocation = $payments->previewPaymentAllocation($businessId, $thirdPartyVendorId, $amount);
        $chronological = app(\App\Services\ThirdPartyPayerChronologicalPaymentService::class);
        $payer = app(InsurerPortalVendorSummaryService::class)->resolvePayer($businessId, $thirdPartyVendorId);
        $chronologicalCheck = $payer
            ? $chronological->validateSelectionAndAmount($payer, $historyIds, $amount)
            : ['valid' => true];

        $payload = array_merge($preview, [
            'formatted_amount' => 'UGX '.number_format($preview['amount'], 2),
            'formatted_service_charge' => 'UGX '.number_format($preview['service_charge'], 2),
            'formatted_total' => 'UGX '.number_format($preview['total'], 2),
            'allocation' => $allocation,
            'selection_valid' => (bool) ($chronologicalCheck['valid'] ?? true),
            'selection_message' => $chronologicalCheck['message'] ?? null,
        ]);

        if (! ($chronologicalCheck['valid'] ?? true)) {
            return response()->json([
                'success' => false,
                'message' => $chronologicalCheck['message'] ?? 'Check the items you selected and the payment amount.',
                'data' => $payload,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function recordPayment(Request $request, int $businessId, int $thirdPartyVendorId, InsurerPortalVendorPaymentService $payments)
    {
        if (! Business::whereKey($businessId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Business not found.'], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'history_ids' => 'nullable|array',
            'history_ids.*' => 'integer',
            'entry_ids' => 'nullable|array',
            'entry_ids.*' => 'integer',
        ]);

        $result = $payments->recordPayment($businessId, $thirdPartyVendorId, $validated);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Payment could not be recorded.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
