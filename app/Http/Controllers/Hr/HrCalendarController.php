<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Models\HrCalendarEvent;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrCalendarController extends Controller
{
    public function index()
    {
        return view('hr.calendar.index');
    }

    public function events(Request $request): JsonResponse
    {
        $org = Organization::current();
        if (! $org) {
            return response()->json([]);
        }

        $rangeStart = CarbonImmutable::parse($request->query('start', now()->startOfMonth()))->startOfDay();
        $rangeEnd = CarbonImmutable::parse($request->query('end', now()->endOfMonth()))->endOfDay();

        $events = HrCalendarEvent::forOrganization($org)
            ->active()
            ->approved()
            ->orderBy('starts_on')
            ->get()
            ->flatMap(fn ($event) => $this->toFullCalendarItems($event, $rangeStart, $rangeEnd))
            ->values();

        return response()->json($events);
    }

    private function toFullCalendarItems(HrCalendarEvent $event, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        $color = match ($event->event_type) {
            'public_holiday' => '#ef4444',
            'observance' => '#0ea5e9',
            'organization_event' => '#10b981',
            'training_day' => '#f59e0b',
            'payroll_cutoff' => '#6366f1',
            'leave_blackout' => '#6b7280',
            default => '#374151',
        };

        $startsOn = CarbonImmutable::parse($event->starts_on->toDateString());
        $endsOn = CarbonImmutable::parse(($event->ends_on ?? $event->starts_on)->toDateString());
        $durationDays = (int) $startsOn->diffInDays($endsOn);

        if (! $event->repeats_yearly) {
            if ($startsOn->gt($rangeEnd) || $endsOn->lt($rangeStart)) {
                return [];
            }

            return [$this->buildItem("custom-{$event->id}", $event, $startsOn, $durationDays, $color, false)];
        }

        $items = [];
        for ($year = $rangeStart->year - 1; $year <= $rangeEnd->year + 1; $year++) {
            try {
                $occurrenceStart = $startsOn->setYear($year);
                $occurrenceEnd = $occurrenceStart->addDays($durationDays);

                if ($occurrenceStart->gt($rangeEnd)) {
                    break;
                }

                if ($occurrenceEnd->lt($rangeStart)) {
                    continue;
                }

                $items[] = $this->buildItem("custom-{$event->id}-{$year}", $event, $occurrenceStart, $durationDays, $color, true);
            } catch (\Throwable) {
                continue;
            }
        }

        return $items;
    }

    private function buildItem(string $id, HrCalendarEvent $event, CarbonImmutable $start, int $durationDays, string $color, bool $repeatsYearly): array
    {
        return [
            'id' => $id,
            'title' => $event->title,
            'start' => $start->toDateString(),
            'end' => $start->addDays($durationDays + 1)->toDateString(),
            'allDay' => true,
            'color' => $event->blocks_rosters ? '#be123c' : $color,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'type' => $event->event_type,
                'repeatsYearly' => $repeatsYearly,
                'rewardType' => $event->reward_type,
                'blocksRosters' => $event->blocks_rosters,
            ],
        ];
    }
}
