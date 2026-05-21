<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Qualification;
use App\Models\ClientSpace;
use Illuminate\Http\Request;

class HrIntegrationController extends Controller
{
    private const HR_PERMISSIONS = [
        'View HR Staff',
        'Add HR Staff',
        'Edit HR Staff',
        'View HR Setup',
        'Add HR Setup',
        'Edit HR Setup',
        'View HR Approvals',
        'Edit HR Approvals',
    ];

    public function staff(Request $request)
    {
        $query = User::query()
            ->where('business_id', '!=', 1)
            ->select('id', 'uuid', 'name', 'email', 'phone', 'gender', 'business_id', 'branch_id', 'qualification_id', 'department_id', 'title_id', 'section_id', 'status', 'permissions', 'created_at');

        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('email')) {
            $query->where('email', $request->email);
        }

        $staff = $query->with(['qualification:id,name', 'department:id,name', 'title:id,name', 'branch:id,name'])
            ->latest()
            ->paginate($request->get('per_page', 50));

        $staff->getCollection()->transform(fn (User $user): User => $this->withHrPermissions($user));

        return response()->json($staff);
    }

    public function staffShow(string $uuid)
    {
        $user = User::where('uuid', $uuid)
            ->where('business_id', '!=', 1)
            ->with(['qualification:id,name', 'department:id,name', 'title:id,name', 'branch:id,name', 'business:id,name,uuid'])
            ->firstOrFail();

        return response()->json($this->withHrPermissions($user));
    }

    public function businesses()
    {
        $businesses = Business::where('id', '!=', 1)
            ->select('id', 'uuid', 'name', 'email', 'phone', 'account_number')
            ->get();

        return response()->json($businesses);
    }

    public function branches(Request $request)
    {
        $query = Branch::query()->select('id', 'uuid', 'name', 'business_id', 'email', 'phone');

        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        return response()->json($query->get());
    }

    public function departments(Request $request)
    {
        $query = Department::query()->select('id', 'uuid', 'name', 'business_id');

        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        return response()->json($query->get());
    }

    public function clientSpaces(Request $request)
    {
        $query = ClientSpace::query()
            ->where('business_id', '!=', 1)
            ->select('id', 'uuid', 'name', 'description', 'business_id', 'branch_id')
            ->with('branch:id,name');

        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        return response()->json($query->get());
    }

    public function qualifications(Request $request)
    {
        $query = Qualification::query()->select('id', 'uuid', 'name', 'business_id');

        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        return response()->json($query->get());
    }

    private function withHrPermissions(User $user): User
    {
        $user->setAttribute('hr_permissions', array_values(array_intersect($user->permissions ?? [], self::HR_PERMISSIONS)));
        $user->makeHidden('permissions');

        return $user;
    }
}
