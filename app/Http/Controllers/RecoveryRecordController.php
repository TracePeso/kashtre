<?php

namespace App\Http\Controllers;

use App\Models\ImagingStudy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecoveryRecordController extends Controller
{
    public function updateMonitoring(Request $request, ImagingStudy $imagingStudy)
    {
        $this->authorizeProgress();

        $validated = $request->validate([
            'vital_signs_notes' => 'nullable|string|max:2000',
            'discharge_criteria_met' => 'nullable|boolean',
        ]);

        $record = $imagingStudy->recoveryRecord ?? $imagingStudy->recoveryRecord()->make();

        $record->fill([
            'vital_signs_notes' => $validated['vital_signs_notes'] ?? $record->vital_signs_notes,
            'discharge_criteria_met' => (bool) ($validated['discharge_criteria_met'] ?? false),
        ]);

        if (! $record->monitoring_started_at) {
            $record->monitoring_started_at = now();
        }

        $imagingStudy->recoveryRecord()->save($record);

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Recovery monitoring updated.');
    }

    public function dischargeStore(Request $request, ImagingStudy $imagingStudy)
    {
        $this->authorizeProgress();

        $validated = $request->validate([
            'discharge_notes' => 'nullable|string|max:1000',
        ]);

        $record = $imagingStudy->recoveryRecord;

        if (! $record) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', 'Start recovery monitoring before clearing for discharge.');
        }

        try {
            $record->clearForDischarge(Auth::id(), $validated['discharge_notes'] ?? null);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Cleared for discharge.');
    }

    private function authorizeProgress(): void
    {
        abort_unless(in_array('Progress Imaging Studies', Auth::user()->permissions ?? []), 403);
    }
}
