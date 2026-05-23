<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Models\StaffAssignment;
use Illuminate\Support\Facades\Auth;

class TierStaffAssignmentController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        abort_unless(
            $user?->canViewHrStaff()
                || (
                    $user?->staff_uuid
                    && StaffAssignment::query()
                        ->where('staff_uuid', $user->staff_uuid)
                        ->whereNotIn('status', ['inactive', 'orphaned'])
                        ->whereHas('organizationalUnit', fn ($query) => $query->routingNodes())
                        ->exists()
                ),
            403
        );

        return view('hr.tier-staff-assignments.index');
    }
}
