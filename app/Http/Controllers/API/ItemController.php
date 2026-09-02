<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ItemResource;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ItemController extends Controller
{
    /**
     * Return items for a given business (for insurer portal).
     */
    public function index(Request $request, int $businessId)
    {
        Log::info('API/ItemController@index: fetching items for business', [
            'business_id' => $businessId,
        ]);

        $query = Item::where('business_id', $businessId);

        // Optional filter by codes (if needed later)
        $codes = (array) $request->input('codes', []);
        if (! empty($codes)) {
            $query->whereIn('code', $codes);
        }

        $items = $query
            ->orderBy('name')
            ->get(['id', 'name', 'generic_name', 'category', 'code', 'type']);

        Log::info('API/ItemController@index: items fetched', [
            'business_id' => $businessId,
            'count' => $items->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * List items for the authenticated user's business.
     * Kashtre admins (business_id = 1) can pass ?business_id= to filter.
     */
    public function list(Request $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $query = Item::query()->orderBy('name');

        if ((int) $authUser->business_id === 1) {
            if ($request->filled('business_id')) {
                $query->where('business_id', (int) $request->input('business_id'));
            }
        } else {
            $query->where('business_id', $authUser->business_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $codes = array_filter((array) $request->input('codes', []));
        if ($codes !== []) {
            $query->whereIn('code', $codes);
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ItemResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Show a single item by uuid within business scope.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $query = Item::query()->where('uuid', $uuid);

        if ((int) $authUser->business_id !== 1) {
            $query->where('business_id', $authUser->business_id);
        }

        $item = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new ItemResource($item),
        ]);
    }
}
