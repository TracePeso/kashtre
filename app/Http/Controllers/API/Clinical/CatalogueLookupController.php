<?php

namespace App\Http\Controllers\API\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The catalogue lookup the Clinical Module is blocked on.
 *
 * API Integration Guide §14 lists this as "contract proposed, not built by
 * Main" and marks it as blocking **all ordering** — nothing can be prescribed
 * or requested without it, because Clinical resolves a clinician's generic
 * term ("Ceftriaxone") into one of our SKUs before it will accept an order.
 *
 * The resolution logic is the same as ClinicalTranslatorEngine::resolveDrug(),
 * which is what the local driver still uses. Keeping one behaviour behind both
 * is deliberate: a drug that resolves under CLINICAL_DRIVER=local and fails
 * under `api` would be a migration that quietly changes what clinicians can
 * prescribe.
 *
 * Two honest gaps carried over from that engine, worth stating in the contract
 * rather than papering over:
 *
 *  - `Item` has no `is_offer_item` flag, so "available" here means "an active,
 *    non-deleted catalogue row". If Clinical needs a real pharmacy-availability
 *    signal, Inventory has to grow that column first.
 *  - `other_names` is a plain string, not the JSON array of alternative names
 *    the SRD assumes, so alternative-name matching is a substring search.
 */
class CatalogueLookupController extends Controller
{
    /**
     * POST /api/v1/catalogue/resolve
     *
     * Clinical sends the term a clinician typed; we answer with the candidate
     * SKUs. An empty `matches` array is a legitimate answer — it is what makes
     * Clinical raise EXTERNAL_FULFILMENT_REQUIRED rather than inventing a SKU.
     */
    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string', 'max:100'],
            'requested_term' => ['required', 'string', 'max:255'],
            'strength_descriptor' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $businessId = $this->resolveBusinessId($validated['tenant_id']);

        if (! $businessId) {
            // A tenant we cannot map is not "no matches" — answering with an
            // empty list would let Clinical conclude the drug is unavailable
            // and quietly route the patient to an external referral.
            return response()->json([
                'message' => "Unknown tenant [{$validated['tenant_id']}].",
                'errors' => ['error_code' => 'UNKNOWN_TENANT'],
            ], 422);
        }

        $matches = $this->search(
            $businessId,
            $validated['requested_term'],
            $validated['strength_descriptor'] ?? null,
            $validated['limit'] ?? 10,
        );

        return response()->json([
            'data' => [
                'requested_term' => $validated['requested_term'],
                'matches' => $matches,
            ],
            'meta' => ['count' => count($matches)],
        ]);
    }

    /**
     * GET /api/v1/catalogue/items/{code}
     *
     * Single-SKU fetch, for when Clinical already holds a code — re-validating
     * an order placed days ago, or confirming a SKU still exists before it
     * dispatches a fulfilment request.
     */
    public function show(Request $request, string $code): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string', 'max:100'],
        ]);

        $businessId = $this->resolveBusinessId($validated['tenant_id']);

        $item = $businessId
            ? Item::where('business_id', $businessId)->where('code', $code)->first()
            : null;

        if (! $item) {
            return response()->json([
                'message' => "No catalogue item [{$code}].",
                'errors' => ['error_code' => 'ITEM_NOT_FOUND'],
            ], 404);
        }

        return response()->json(['data' => $this->present($item)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function search(int $businessId, string $term, ?string $strength, int $limit): array
    {
        return Item::query()
            ->where('business_id', $businessId)
            ->where(function (Builder $query) use ($term) {
                $query->where('generic_name', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('other_names', 'like', "%{$term}%");
            })
            ->when($strength, function (Builder $query, string $strength) {
                // Strength lives inside the name/description rather than its
                // own column, so this narrows rather than filters exactly.
                $query->where(function (Builder $query) use ($strength) {
                    $query->where('name', 'like', "%{$strength}%")
                        ->orWhere('description', 'like', "%{$strength}%");
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Item $item) => $this->present($item))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Item $item): array
    {
        return [
            'inventory_sku' => $item->code,
            'display_name' => $item->name,
            'generic_name' => $item->generic_name,
            'alternative_names' => $item->other_names,
            'description' => $item->description,
            'type' => $item->type,
            'base_uom_id' => $item->uom_id,
            // See the class docblock: there is no is_offer_item flag on Item,
            // so this reports the closest fact we actually hold rather than
            // implying a pharmacy-availability check we do not perform.
            'is_available' => true,
        ];
    }

    private function resolveBusinessId(string $tenantId): ?int
    {
        if (preg_match('/^TENANT-(\d+)$/', $tenantId, $matches)) {
            return (int) $matches[1];
        }

        return Business::whereRaw('UPPER(entity_code) = ?', [strtoupper($tenantId)])->value('id');
    }
}
