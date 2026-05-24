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

        $calendarDays = collect();
        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $calendarDays->push($day->copy());
        }

        $clientSpaceRows = $entries
            ->groupBy(function ($entry): string {
                $clientSpaceId = $entry->dutyRoster?->organizationalUnit?->id;

                return $clientSpaceId ? 'id:'.$clientSpaceId : 'name:'.($entry->dutyRoster?->organizationalUnit?->name ?: 'Unassigned Client Space');
            })
            ->map(function ($clientSpaceEntries, string $key) use ($calendarDays): array {
                $firstEntry = $clientSpaceEntries->first();
                $cells = $clientSpaceEntries
                    ->groupBy(fn ($entry) => $entry->roster_date->toDateString())
                    ->map(fn ($dayEntries) => $dayEntries
                        ->map(fn ($entry): array => [
                            'code' => $entry->shiftType?->code ?: $entry->shiftType?->name ?: 'Shift',
                            'name' => $entry->shiftType?->name ?: 'Scheduled Shift',
                        ])
                        ->values()
                        ->all()
                    );

                return [
                    'key' => $key,
                    'client_space_name' => $firstEntry->dutyRoster?->organizationalUnit?->name ?: 'Unassigned Client Space',
                    'days' => $calendarDays->mapWithKeys(
                        fn (Carbon $day): array => [$day->toDateString() => $cells->get($day->toDateString(), [])]
                    )->all(),
                ];
            })
            ->sortBy('client_space_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('hr.my-roster.index', [
            'monthStart' => $monthStart,
            'calendarDays' => $calendarDays,
            'clientSpaceRows' => $clientSpaceRows,
        ]);
    }
}
