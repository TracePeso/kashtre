<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BranchResource;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * List branches visible to the authenticated user.
     * Non-admins only see branches of their own business.
     * Optional ?business_id= filter (admins, or matching own business).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $query = Branch::query()
            ->with(['business:id,uuid,name'])
            ->orderBy('name');

        if ((int) $authUser->business_id === 1) {
            if ($request->filled('business_id')) {
                $query->where('business_id', (int) $request->input('business_id'));
            }
        } else {
            $query->where('business_id', $authUser->business_id);
        }

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
            ],
        ]);
    }

    /**
     * Show a single branch by uuid.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $query = Branch::query()
            ->with(['business:id,uuid,name'])
            ->where('uuid', $uuid);

        if ((int) $authUser->business_id !== 1) {
            $query->where('business_id', $authUser->business_id);
        }

        $branch = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new BranchResource($branch),
        ]);
    }
}
