<?php

namespace App\Http\Controllers;

use App\Contracts\Clinical\FhirExportGateway;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClinicalFhirExportController extends Controller
{
    public function show(Request $request, string $clientId, FhirExportGateway $fhir)
    {
        abort_unless(in_array('Export FHIR Bundle', Auth::user()->permissions ?? []), 403);

        return response()->json(
            $fhir->patientEverything(ClinicalActor::fromUser(Auth::user()), $clientId)
        );
    }
}
