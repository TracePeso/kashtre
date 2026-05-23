<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Models\HrOrganizationalUnit;
use App\Notifications\RepeatedLateClockInNotification;
use App\Services\DutyRosterService;
use App\Services\HrRealDataSyncService;
use App\Services\KashApiService;
use App\Services\OrphanedStaffFlagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(
        KashApiService $api,
        DutyRosterService $dutyRosterService,
        HrRealDataSyncService $syncService
    )
    {
        $user = Auth::user();
        $org = Organization::current($user);

        if (
            $user instanceof User
            && $user->canSyncHrData()
            && (! $org || ! StaffAssignment::where('organization_id', $org->id)->exists())
        ) {
            $stats = $syncService->sync($user, $org);

            if ($stats['current_organization_id']) {
                session(['current_organization_id' => $stats['current_organization_id']]);
            }

            $org = Organization::current($user);
        }

        $stuckCutoff = Carbon::now()->subHours(OrphanedStaffFlagService::DEFAULT_STUCK_HOURS);
        $clientSpaceUnits = $org
            ? HrOrganizationalUnit::where('organization_id', $org->id)
                ->clientSpaces()
                ->with(['parent', 'routingParents'])
                ->get()
            : collect();

        $stats = [
            'assigned_staff' => $org ? StaffAssignment::where('organization_id', $org->id)->where('status', 'active')->count() : 0,
            'pending_routing' => $org ? StaffAssignment::where('organization_id', $org->id)->where('status', 'pending_routing')->count() : 0,
            'client_spaces' => $clientSpaceUnits->count(),
            'unattached_client_spaces' => $clientSpaceUnits->reject(fn (HrOrganizationalUnit $unit): bool => $unit->hasClientSpacePlacement())->count(),
            'orphaned_staff' => $org ? StaffAssignment::where('organization_id', $org->id)->withoutActiveClientSpace()->count() : 0,
            'stuck_in_routing' => $org ? StaffAssignment::where('organization_id', $org->id)->stuckInRouting($stuckCutoff)->count() : 0,
        ];

        $stats['total_staff'] = 0;

        if ($org) {
            $localTotalStaff = $org->staffAssignments()->count();
            $stats['total_staff'] = $localTotalStaff;

            // Try to get total staff from API for the current organization.
            try {
                $staffData = $api->getStaff([
                    'business_id' => $this->businessIdForApi($api, $org),
                    'per_page' => 1,
                ]);
                $apiTotal = $staffData['total'] ?? null;

                if (is_numeric($apiTotal)) {
                    $stats['total_staff'] = max((int) $apiTotal, $localTotalStaff);
                }
            } catch (\Throwable $e) {
            }
        }

        $upcomingRosterEntries = collect();
        $nextRosterEntry = null;
        $todayRosterEntries = collect();
        $lateAttendanceNotifications = collect();

        if ($org && $user instanceof User && $user->staff_uuid) {
            $today = Carbon::today();
            $upcomingRosterEntries = $dutyRosterService->visibleEntriesForStaff(
                $user,
                $org,
                $today,
                $today->copy()->addDays(13)->endOfDay()
            );
            $nextRosterEntry = $upcomingRosterEntries->first();
            $todayRosterEntries = $upcomingRosterEntries
                ->filter(fn ($entry) => $entry->roster_date?->isSameDay($today))
                ->values();
        }

        if ($user instanceof User) {
            $lateAttendanceNotifications = $user->notifications()
                ->where('type', RepeatedLateClockInNotification::class)
                ->latest()
                ->limit(5)
                ->get();
        }

        return view('hr.dashboard', compact('stats', 'org', 'upcomingRosterEntries', 'nextRosterEntry', 'todayRosterEntries', 'lateAttendanceNotifications'));
    }

    private function businessIdForApi(KashApiService $api, Organization $org): string
    {
        $organizationReference = (string) $org->external_business_uuid;

        foreach ($api->getBusinesses() as $business) {
            if (!is_array($business)) {
                continue;
            }

            $references = collect(['id', 'uuid', 'external_business_uuid'])
                ->map(fn (string $key): ?string => isset($business[$key]) ? (string) $business[$key] : null)
                ->filter()
                ->values();

            if ($references->contains($organizationReference)) {
                return isset($business['id']) ? (string) $business['id'] : $organizationReference;
            }
        }

        return $organizationReference;
    }

    public function sync(HrRealDataSyncService $syncService): RedirectResponse
    {
        $stats = $syncService->sync(Auth::user(), Organization::current(Auth::user()));

        if ($stats['current_organization_id']) {
            session(['current_organization_id' => $stats['current_organization_id']]);
        }

        return back()->with(
            'status',
            "Synced {$stats['organizations']} organization(s), {$stats['client_spaces']} client space(s), {$stats['staff_assignments']} staff assignment(s), {$stats['users']} linked user(s), and {$stats['approval_workflows']} approval workflow config(s) for this Kashtre business. {$stats['client_spaces_unattached']} client space(s) need routing placement."
        );
    }
}
