<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BranchResource;
use App\Http\Resources\Api\BusinessResource;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    /**
     * List businesses visible to the authenticated user.
     * Non-admins only see their own business.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $query = Business::query()->orderBy('name');

        if ((int) $authUser->business_id !== 1) {
            $query->where('id', $authUser->business_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('entity_code', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('with_branches')) {
            $query->with(['branches' => fn ($q) => $q->orderBy('name')]);
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $businesses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => BusinessResource::collection($businesses->items()),
            'meta' => [
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'per_page' => $businesses->perPage(),
                'total' => $businesses->total(),
            ],
        ]);
    }

    /**
     * Show a single business by uuid.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $query = Business::query()
            ->with(['branches' => fn ($q) => $q->orderBy('name')])
            ->where('uuid', $uuid);

        if ((int) $authUser->business_id !== 1) {
            $query->where('id', $authUser->business_id);
        }

        $business = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new BusinessResource($business),
        ]);
    }

    /**
     * List branches for a specific business (by id or uuid).
     */
    public function branches(Request $request, string $business): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $businessModel = Business::query()
            ->where(function ($q) use ($business) {
                $q->where('uuid', $business);
                if (ctype_digit($business)) {
                    $q->orWhere('id', (int) $business);
                }
            })
            ->firstOrFail();

        if ((int) $authUser->business_id !== 1 && (int) $authUser->business_id !== (int) $businessModel->id) {
            abort(403, __('You are not allowed to view branches for this business.'));
        }

        $query = $businessModel->branches()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $branches = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => BranchResource::collection($branches->items()),
            'meta' => [
                'current_page' => $branches->currentPage(),
                'last_page' => $branches->lastPage(),
                'per_page' => $branches->perPage(),
                'total' => $branches->total(),
                'business' => [
                    'id' => $businessModel->id,
                    'uuid' => $businessModel->uuid,
                    'name' => $businessModel->name,
                ],
            ],
        ]);
    }
}
