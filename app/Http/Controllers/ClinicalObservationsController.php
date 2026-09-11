<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClinicalObservationsController extends Controller
{
    public function show(Request $request, string $clientId)
    {
        // Scoped to the acting user's business — clientId alone is not
        // globally unique (it's a business-scoped code, see
        // Client::generateClientId()).
        $client = Client::where('business_id', Auth::user()->business_id)
            ->where('client_id', $clientId)
            ->first();

        // A near-miss on this string (a typo, or an id copied from
        // somewhere that only had a shortened form) resolves zero rows, not
        // a fuzzy match. Rendering the panels below anyway used to mean 14
        // separate Clinical/Main components each failing in their own,
        // differently-worded way (ReBAC denials, "client could not be
        // resolved" on billing, empty history, ...) with nothing anywhere
        // saying plainly that the id itself was wrong — the actual cause
        // was lost in a pile of unrelated-looking symptoms. Stopping here,
        // once, with the id spelled back, is the honest version of that.
        if (! $client) {
            return view('clinical.observations.not-found', [
                'clientId' => $clientId,
            ]);
        }

        return view('clinical.observations.show', [
            'clientId' => $clientId,
            'visitId' => $request->query('visit_id'),
            'client' => $client,
        ]);
    }
}
