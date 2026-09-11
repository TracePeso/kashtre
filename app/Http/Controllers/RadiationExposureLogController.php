<?php

namespace App\Http\Controllers;

use App\Models\ImagingStudy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RadiationExposureLogController extends Controller
{
    public function store(Request $request, ImagingStudy $imagingStudy)
    {
        abort_unless(in_array('Progress Imaging Studies', Auth::user()->permissions ?? []), 403);

        $validated = $request->validate([
            'dose_area_product_gy' => 'nullable|numeric|min:0',
            'exposure_time_ms' => 'nullable|integer|min:0',
            'kvp_metrics' => 'nullable|string|max:255',
        ]);

        $imagingStudy->radiationExposureLogs()->create([
            ...$validated,
            'client_id' => $imagingStudy->client_id,
        ]);

        return redirect()
            ->route('imaging-studies.show', $imagingStudy)
            ->with('success', 'Radiation exposure logged.');
    }
}
