<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ClinicalObservationsController extends Controller
{
    public function show(Request $request, string $clientId)
    {
        return view('clinical.observations.show', [
            'clientId' => $clientId,
            'visitId' => $request->query('visit_id'),
        ]);
    }
}
