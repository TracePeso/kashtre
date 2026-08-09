<?php

namespace App\Services\Clinical;

use App\Models\Business;
use App\Models\Item;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SRD §6.1-6.2 Clinical Translator Engine. The real Item model has no
 * alternative_names JSON array, strength descriptor, or is_offer_item
 * flag the SRD assumes — `other_names` is a plain string (confirmed via
 * migration history: it was converted FROM json TO string), and there's
 * no offer/pharmacy-availability flag at all. This matches against the
 * fields that actually exist (generic_name/name/other_names via LIKE,
 * strength via a substring check against name/description) and treats
 * any active (non-soft-deleted) match as available — the closest honest
 * equivalent to is_offer_item until Inventory grows that field.
 */
class ClinicalTranslatorEngine
{
    public function resolveDrug(int $businessId, string $searchTerm, ?string $strength = null): ?Item
    {
        $query = Item::where('business_id', $businessId)
            ->where(function ($query) use ($searchTerm) {
                $query->where('generic_name', 'like', "%{$searchTerm}%")
                    ->orWhere('name', 'like', "%{$searchTerm}%")
                    ->orWhere('other_names', 'like', "%{$searchTerm}%");
            });

        if ($strength) {
            $query->where(function ($query) use ($strength) {
                $query->where('name', 'like', "%{$strength}%")
                    ->orWhere('description', 'like', "%{$strength}%");
            });
        }

        return $query->orderBy('name')->first();
    }

    /**
     * SRD §6.2: no internal SKU match — generate an audited external
     * fulfillment document instead of blocking the clinician.
     *
     * @param array{client_id: string, drug_search: string, strength: ?string, dose_amount: float, route_code: string, frequency_code: string, clinical_indication: ?string, ordering_clinician_name: string} $data
     * @return string storage path of the generated PDF
     */
    public function generateExternalReferral(int $businessId, array $data): string
    {
        $business = Business::find($businessId);

        $pdf = Pdf::loadView('clinical.pdf.external-referral', [
            'business' => $business,
            'data' => $data,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $path = "clinical/external-referrals/{$businessId}/".Str::uuid().'.pdf';

        Storage::put($path, $pdf->output());

        return $path;
    }
}
