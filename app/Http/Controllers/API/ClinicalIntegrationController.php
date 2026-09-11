<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryHandoffToken;
use App\Services\ClinicalModuleIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicalIntegrationController extends Controller
{
    public function __construct(
        private readonly ClinicalModuleIntegrationService $clinical,
    ) {
    }

    /**
     * GET /api/catalogue/items?q=&tenant_id=
     */
    public function catalogueItems(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', $request->query('search', '')));
        if ($q === '') {
            return response()->json([
                'message' => 'Query parameter q is required.',
            ], 422);
        }

        $businessId = $this->clinical->resolveBusinessId($this->clinical->tenantIdFromRequest($request));

        return response()->json([
            'data' => $this->clinical->searchCatalogue($q, $businessId),
        ]);
    }

    /**
     * GET /api/clients/{id}?tenant_id=
     */
    public function clientShow(Request $request, string $id): JsonResponse
    {
        $businessId = $this->clinical->resolveBusinessId($this->clinical->tenantIdFromRequest($request));
        $client = $this->clinical->findClient($id, $businessId);

        if (! $client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        return response()->json([
            'data' => $this->clinical->clientPayload($client),
        ]);
    }

    /**
     * GET /api/queues?tenant_id=&ward_code=
     */
    public function queues(Request $request): JsonResponse
    {
        $businessId = $this->clinical->resolveBusinessId($this->clinical->tenantIdFromRequest($request));
        $wardCode = $request->query('ward_code');

        return response()->json([
            'data' => $this->clinical->queuePayload($businessId, $wardCode ? (string) $wardCode : null),
        ]);
    }

    /**
     * POST /api/events — infant registration and other clinical facts.
     */
    public function events(Request $request): JsonResponse
    {
        $payload = $request->all();
        $businessId = $this->clinical->resolveBusinessId($this->clinical->tenantIdFromRequest($request));

        $response = $this->clinical->handleInboundEvent($payload, $businessId);

        return response()->json([
            'data' => $response,
        ], 201);
    }

    /**
     * GET /api/pharmacy/totes/{ref} — staged tote checklist for Clinical ward Collect Medications.
     */
    public function toteShow(Request $request, string $ref): JsonResponse
    {
        $token = InventoryHandoffToken::query()
            ->where('uuid', $ref)
            ->first();

        if (! $token) {
            return response()->json(['message' => 'Tote / handoff session not found.'], 404);
        }

        $businessId = $this->clinical->resolveBusinessId($this->clinical->tenantIdFromRequest($request));
        if ($businessId !== null && (int) $token->business_id !== (int) $businessId) {
            return response()->json(['message' => 'Tote / handoff session not found.'], 404);
        }

        return response()->json([
            'data' => $this->clinical->toteChecklistPayload($token),
        ]);
    }
}
