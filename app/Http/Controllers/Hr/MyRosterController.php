<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Models\HrDutyRoster;
use App\Models\HrDutyRosterEntry;
use App\Models\ShiftType;
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

        $weekendDays = collect(is_array($organization?->weekend_days) ? $organization->weekend_days : [0, 6])
            ->map(fn ($day): int => (int) $day)
            ->all();

        $entriesByDate = $entries
            ->groupBy(fn (HrDutyRosterEntry $entry): string => $entry->roster_date->toDateString());

        $scheduleCells = $calendarDays
            ->map(function (Carbon $day) use ($entriesByDate, $weekendDays): array {
                $assignments = collect($entriesByDate->get($day->toDateString(), []))
                    ->map(fn (HrDutyRosterEntry $entry): array => $this->formatRosterAssignment($entry))
                    ->sortBy([
                        ['client_space_name', 'asc'],
                        ['shift_name', 'asc'],
                    ], SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();

                return [
                    'date' => $day->copy(),
                    'is_today' => $day->isSameDay(now()),
                    'is_weekend' => in_array($day->dayOfWeek, $weekendDays, true),
                    'assignments' => $assignments,
                ];
            })
            ->values();

        $clientSpaceLegend = $entries
            ->groupBy(fn (HrDutyRosterEntry $entry): string => $this->clientSpaceKeyForEntry($entry))
            ->map(function ($clientSpaceEntries, string $key): array {
                /** @var HrDutyRosterEntry $firstEntry */
                $firstEntry = $clientSpaceEntries->first();

                return [
                    'key' => $key,
                    'name' => $this->clientSpaceNameForEntry($firstEntry),
                    'tone' => $this->toneForSeed($key),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $shiftLegend = $entries
            ->groupBy(fn (HrDutyRosterEntry $entry): string => $this->shiftKeyForEntry($entry))
            ->map(function ($shiftEntries, string $key): array {
                /** @var HrDutyRosterEntry $firstEntry */
                $firstEntry = $shiftEntries->first();
                $shiftType = $firstEntry->shiftType;

                return [
                    'key' => $key,
                    'name' => $shiftType?->name ?: 'Scheduled Shift',
                    'code' => $shiftType?->code ?: 'Shift',
                    'time' => $this->shiftTimeLabel($shiftType),
                    'tone' => $this->toneForSeed($key, $shiftType?->color),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('hr.my-roster.index', [
            'monthStart' => $monthStart,
            'calendarDays' => $calendarDays,
            'scheduleCells' => $scheduleCells,
            'clientSpaceLegend' => $clientSpaceLegend,
            'shiftLegend' => $shiftLegend,
        ]);
    }

    private function formatRosterAssignment(HrDutyRosterEntry $entry): array
    {
        $clientSpaceKey = $this->clientSpaceKeyForEntry($entry);
        $shiftKey = $this->shiftKeyForEntry($entry);
        $shiftType = $entry->shiftType;
        $rosterStatus = $entry->dutyRoster?->status === HrDutyRoster::STATUS_DRAFT ? 'Draft' : 'Published';

        return [
            'client_space_name' => $this->clientSpaceNameForEntry($entry),
            'client_space_tone' => $this->toneForSeed($clientSpaceKey),
            'shift_name' => $shiftType?->name ?: 'Scheduled Shift',
            'shift_time' => $this->shiftTimeLabel($shiftType),
            'roster_status' => $rosterStatus,
            'cell_background' => $this->diagonalSplitBackground(
                $this->toneForSeed($shiftKey, $shiftType?->color)['background'],
                $this->toneForSeed($clientSpaceKey)['background']
            ),
        ];
    }

    private function clientSpaceKeyForEntry(HrDutyRosterEntry $entry): string
    {
        $clientSpaceId = $entry->dutyRoster?->organizationalUnit?->id;

        return $clientSpaceId
            ? 'client-space:'.$clientSpaceId
            : 'client-space:'.($entry->dutyRoster?->organizationalUnit?->name ?: 'unassigned');
    }

    private function clientSpaceNameForEntry(HrDutyRosterEntry $entry): string
    {
        return $entry->dutyRoster?->organizationalUnit?->name ?: 'Unassigned Client Space';
    }

    private function shiftKeyForEntry(HrDutyRosterEntry $entry): string
    {
        $shiftTypeId = $entry->shiftType?->id;

        return $shiftTypeId
            ? 'shift:'.$shiftTypeId
            : 'shift:'.($entry->shiftType?->name ?: 'scheduled');
    }

    private function shiftTimeLabel(?ShiftType $shiftType): string
    {
        if (! $shiftType?->start_time || ! $shiftType?->end_time) {
            return 'Time not configured';
        }

        return Carbon::parse($shiftType->start_time)->format('H:i')
            .' to '
            .Carbon::parse($shiftType->end_time)->format('H:i');
    }

    /**
     * @return array{background: string, border: string, text: string}
     */
    private function toneForSeed(string $seed, ?string $preferredHex = null): array
    {
        $hex = $this->normalizeHexColor($preferredHex)
            ?? $this->paletteColorForSeed($seed);

        return [
            'background' => $this->rgbaFromHex($hex, 0.14),
            'border' => $this->rgbaFromHex($hex, 0.34),
            'text' => $this->darkenHex($hex, 0.18),
        ];
    }

    private function diagonalSplitBackground(string $shiftBackground, string $clientSpaceBackground): string
    {
        return "linear-gradient(135deg, {$shiftBackground} 0 49.5%, {$clientSpaceBackground} 50.5% 100%)";
    }

    private function paletteColorForSeed(string $seed): string
    {
        $palette = [
            '#2563eb',
            '#0f766e',
            '#d97706',
            '#db2777',
            '#7c3aed',
            '#16a34a',
            '#dc2626',
            '#0891b2',
            '#4f46e5',
            '#9333ea',
        ];

        return $palette[abs(crc32($seed)) % count($palette)];
    }

    private function normalizeHexColor(?string $value): ?string
    {
        $candidate = ltrim(trim((string) $value), '#');

        if ($candidate === '') {
            return null;
        }

        if (strlen($candidate) === 3) {
            $candidate = preg_replace('/(.)/', '$1$1', $candidate);
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $candidate)) {
            return null;
        }

        return '#'.strtoupper($candidate);
    }

    private function rgbaFromHex(string $hex, float $alpha): string
    {
        [$red, $green, $blue] = $this->hexToRgb($hex);

        return sprintf('rgba(%d, %d, %d, %.2F)', $red, $green, $blue, $alpha);
    }

    private function darkenHex(string $hex, float $ratio): string
    {
        [$red, $green, $blue] = $this->hexToRgb($hex);

        $ratio = max(0, min(1, $ratio));

        return sprintf(
            '#%02X%02X%02X',
            (int) round($red * (1 - $ratio)),
            (int) round($green * (1 - $ratio)),
            (int) round($blue * (1 - $ratio))
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
