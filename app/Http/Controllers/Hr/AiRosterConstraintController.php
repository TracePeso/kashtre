<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrDutyRoster;
use App\Models\Organization;
use App\Models\ShiftType;
use App\Services\DutyRosterService;
use App\Services\GeminiRosterDraftGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AiRosterConstraintController extends Controller
{
    public function index(
        Request $request,
        DutyRosterService $dutyRosterService,
        GeminiRosterDraftGenerator $geminiRosterDraftGenerator
    )
    {
        $user = Auth::user();
        $organization = $user ? Organization::current($user) : null;
        $clientSpaces = collect();
        $rosters = collect();
        $selectedClientSpaceId = null;
        $selectedRoster = null;
        $preview = null;
        $previewError = null;

        if ($organization && $user) {
            $clientSpaces = $dutyRosterService->accessibleClientSpaces($user, $organization);
            $selectedClientSpaceId = $this->selectedClientSpaceId($request, $clientSpaces);

            $rosterQuery = HrDutyRoster::query()
                ->where('organization_id', $organization->id)
                ->whereIn('organizational_unit_id', $clientSpaces->pluck('id'))
                ->with(['organizationalUnit', 'entries.shiftType'])
                ->orderByDesc('start_date')
                ->orderByDesc('id');

            if ($selectedClientSpaceId) {
                $rosterQuery->where('organizational_unit_id', $selectedClientSpaceId);
            }

            $rosters = $rosterQuery->get();
            $selectedRoster = $this->selectedRoster($request, $rosters);

            if ($selectedRoster) {
                $selectedClientSpaceId = (int) $selectedRoster->organizational_unit_id;

                try {
                    $eligibleAssignments = $dutyRosterService->eligibleAssignments(
                        $selectedRoster->organizationalUnit,
                        $selectedRoster->disciplineTitles()
                    );
                    $shiftTypes = ShiftType::query()
                        ->where('organization_id', $organization->id)
                        ->where('is_active', true)
                        ->orderBy('start_time')
                        ->orderBy('name')
                        ->get();

                    $preview = $geminiRosterDraftGenerator->promptPreview(
                        $selectedRoster,
                        $eligibleAssignments,
                        $shiftTypes,
                        $this->entrySelections($selectedRoster)
                    );
                } catch (ValidationException $exception) {
                    $previewError = collect($exception->errors())
                        ->flatten()
                        ->implode(' ');
                }
            }
        }

        return view('hr.ai-roster-constraints.index', compact(
            'organization',
            'clientSpaces',
            'rosters',
            'selectedClientSpaceId',
            'selectedRoster',
            'preview',
            'previewError'
        ));
    }

    private function selectedClientSpaceId(Request $request, Collection $clientSpaces): ?int
    {
        $requested = (int) $request->integer('client_space');

        if ($requested > 0 && $clientSpaces->contains('id', $requested)) {
            return $requested;
        }

        return $clientSpaces->first()?->id;
    }

    private function selectedRoster(Request $request, Collection $rosters): ?HrDutyRoster
    {
        $requested = (int) $request->integer('roster');

        if ($requested > 0) {
            return $rosters->firstWhere('id', $requested);
        }

        return $rosters->first();
    }

    private function entrySelections(HrDutyRoster $roster): array
    {
        return $roster->entries
            ->groupBy('staff_assignment_id')
            ->map(function (Collection $entries): array {
                return $entries
                    ->sortBy('roster_date')
                    ->mapWithKeys(fn ($entry): array => [
                        $entry->roster_date->toDateString() => (string) $entry->shift_type_id,
                    ])
                    ->all();
            })
            ->all();
    }
}
