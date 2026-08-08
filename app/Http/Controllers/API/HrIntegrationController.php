<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Business;
use App\Models\ClientSpace;
use App\Models\Department;
use App\Models\Qualification;
use App\Models\StaffCategory;
use App\Models\User;
use App\Services\HrModuleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrIntegrationController extends Controller
{
    public function __construct(
        private readonly HrModuleSyncService $hrSync,
    ) {
    }

    /**
     * GET /api/businesses
     */
    public function businesses(): JsonResponse
    {
        $businesses = Business::query()
            ->kashtreEntities()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'email', 'phone', 'account_number']);

        return response()->json($businesses);
    }

    /**
     * GET /api/branches?business_id=X
     */
    public function branches(Request $request): JsonResponse
    {
        $query = Branch::query()
            ->select('id', 'uuid', 'name', 'business_id', 'email', 'phone')
            ->orderBy('name');

        $this->applyBusinessFilter($query, $request);

        return response()->json($query->get());
    }

    /**
     * GET /api/departments?business_id=X
     */
    public function departments(Request $request): JsonResponse
    {
        $query = Department::query()
            ->select('id', 'uuid', 'name', 'business_id')
            ->orderBy('name');

        $this->applyBusinessFilter($query, $request);

        return response()->json($query->get());
    }

    /**
     * GET /api/qualifications?business_id=X
     */
    public function qualifications(Request $request): JsonResponse
    {
        $query = Qualification::query()
            ->select('id', 'uuid', 'name', 'business_id')
            ->orderBy('name');

        $this->applyBusinessFilter($query, $request);

        return response()->json($query->get());
    }

    /**
     * GET /api/staff-categories?business_id=X
     */
    public function staffCategories(Request $request): JsonResponse
    {
        $query = StaffCategory::query()
            ->select('id', 'uuid', 'name', 'description', 'business_id')
            ->orderBy('name');

        $this->applyBusinessFilter($query, $request);

        return response()->json($query->get());
    }

    /**
     * GET /api/client-spaces?business_id=X
     */
    public function clientSpaces(Request $request): JsonResponse
    {
        $query = ClientSpace::query()
            ->where('business_id', '!=', 1)
            ->select('id', 'uuid', 'name', 'description', 'business_id', 'branch_id')
            ->with(['branch:id,name'])
            ->orderBy('name');

        $this->applyBusinessFilter($query, $request);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->input('branch_id'));
        }

        $spaces = $query->get()->map(fn (ClientSpace $space) => [
            'id' => $space->id,
            'uuid' => $space->uuid,
            'name' => $space->name,
            'description' => $space->description,
            'business_id' => $space->business_id,
            'branch_id' => $space->branch_id,
            'branch' => $space->branch ? [
                'id' => $space->branch->id,
                'name' => $space->branch->name,
            ] : null,
        ]);

        return response()->json($spaces);
    }

    /**
     * GET /api/users?business_id=X&per_page=100
     * Also available as GET /api/hr/staff for backwards compatibility.
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('business_id', '!=', 1)
            ->orderBy('name');

        $this->applyBusinessFilter($query, $request);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('email')) {
            $query->where('email', $request->string('email')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $perPage = min(max((int) $request->input('per_page', 100), 1), 200);

        $paginator = $query->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (User $user) => $this->hrSync->userPayload($user))
        );

        return response()->json($paginator);
    }

    /**
     * GET /api/users/{uuid}
     */
    public function userShow(string $uuid): JsonResponse
    {
        $user = User::query()
            ->where('uuid', $uuid)
            ->where('business_id', '!=', 1)
            ->firstOrFail();

        return response()->json($this->hrSync->userPayload($user));
    }

    /**
     * Backwards-compatible alias for GET /api/hr/staff.
     */
    public function staff(Request $request): JsonResponse
    {
        return $this->users($request);
    }

    /**
     * Backwards-compatible alias for GET /api/hr/staff/{uuid}.
     */
    public function staffShow(string $uuid): JsonResponse
    {
        return $this->userShow($uuid);
    }

    private function applyBusinessFilter($query, Request $request): void
    {
        if ($request->filled('business_id')) {
            $query->where('business_id', (int) $request->input('business_id'));
        }
    }
}
