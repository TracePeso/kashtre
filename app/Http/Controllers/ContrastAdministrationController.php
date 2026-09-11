<?php

namespace App\Http\Controllers;

use App\Models\ContrastVial;
use App\Models\ImagingStudy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContrastAdministrationController extends Controller
{
    public function store(Request $request, ImagingStudy $imagingStudy)
    {
        abort_unless(in_array('Progress Imaging Studies', Auth::user()->permissions ?? []), 403);

        $validated = $request->validate([
            'contrast_vial_id' => 'nullable|integer|exists:imaging_contrast_vials,id',
            'contrast_agent_name' => 'required|string|max:255',
            'volume_ml' => 'required|numeric|min:0.01',
            'injection_time' => 'nullable|date',
            'adverse_reactions' => 'nullable|string|max:1000',
        ]);

        try {
            DB::transaction(function () use ($imagingStudy, $validated) {
                // Optional — deduct from the picked vial's real remaining
                // volume, enforcing its own state/expiry guards. When no
                // vial is picked, this is unchanged from the original
                // free-text-only flow.
                if (! empty($validated['contrast_vial_id'])) {
                    ContrastVial::where('business_id', $imagingStudy->business_id)
                        ->findOrFail($validated['contrast_vial_id'])
                        ->deduct((float) $validated['volume_ml']);
                }

                $imagingStudy->contrastAdministrations()->create([
                    ...$validated,
                    'administered_by_user_id' => Auth::id(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('imaging-studies.show', $imagingStudy)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Contrast administration recorded.');
    }
}
