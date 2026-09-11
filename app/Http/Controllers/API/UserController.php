<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users in the authenticated user's business scope.
     * Kashtre admins (business_id = 1) can pass ?business_id= to filter.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $query = User::query()
            ->with(['business:id,name', 'branch:id,name', 'defaultStore:id,name'])
            ->orderBy('name');

        if ((int) $authUser->business_id === 1) {
            if ($request->filled('business_id')) {
                $query->where('business_id', (int) $request->input('business_id'));
            }
        } else {
            $query->where('business_id', $authUser->business_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
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
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Show a single user by uuid within business scope.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $query = User::query()
            ->with(['business:id,name', 'branch:id,name', 'defaultStore:id,name'])
            ->where('uuid', $uuid);

        if ((int) $authUser->business_id !== 1) {
            $query->where('business_id', $authUser->business_id);
        }

        $user = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }
}
