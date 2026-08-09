<?php

namespace App\Http\Controllers;

use App\Services\Clinical\FhirExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClinicalFhirExportController extends Controller
{
    public function show(Request $request, string $clientId, FhirExportService $fhir)
    {
        abort_unless(in_array('Export FHIR Bundle', Auth::user()->permissions ?? []), 403);

        return response()->json(
            $fhir->exportPatientBundle(Auth::user()->business_id, $clientId)
        );
    }
}
