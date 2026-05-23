<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Models\Organization;
use App\Models\User;
use App\Services\DutyRosterService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MyRosterController extends Controller
{
    public function index(Request $request, DutyRosterService $dutyRosterService)
    {
        $organization = Organization::current();
        $user = $request->user();

        $month = (string) $request->query('month', now()->format('Y-m'));

        try {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $monthStart = now()->startOfMonth();
        }

        $monthEnd = $monthStart->copy()->endOfMonth();

        $entries = $organization && $user instanceof User
            ? $dutyRosterService->visibleEntriesForStaff($user, $organization, $monthStart, $monthEnd)
            : collect();

        $futureEntries = $organization && $user instanceof User
            ? $dutyRosterService->visibleEntriesForStaff($user, $organization, now()->startOfDay(), now()->copy()->addDays(90)->endOfDay())
            : collect();

        return view('hr.my-roster.index', [
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'entries' => $entries,
            'entriesByDate' => $entries->groupBy(fn ($entry) => $entry->roster_date->toDateString()),
            'nextEntry' => $futureEntries->first(),
            'clientSpaceCount' => $entries
                ->pluck('dutyRoster.organizationalUnit.id')
                ->filter()
                ->unique()
                ->count(),
        ]);
    }
}
