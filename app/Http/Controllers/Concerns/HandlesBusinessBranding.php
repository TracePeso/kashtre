<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Business;
use App\Support\BusinessBranding;
use Illuminate\Http\Request;

trait HandlesBusinessBranding
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function applyBrandingFromRequest(Request $request, Business $business, array $validated, bool $logoRequired = false): array
    {
        if ($request->hasFile('logo')) {
            $branding = new BusinessBranding($business);

            if ($business->exists) {
                $branding->deleteStoredLogo();
                $validated['logo'] = $branding->storeLogo($request->file('logo'));
            } else {
                $validated['logo'] = BusinessBranding::storeLogoForNewBusiness($request->file('logo'));
            }
        } elseif ($logoRequired && ! $business->logo) {
            $validated['logo'] = null;
        }

        return $validated;
    }

    protected function moveIncomingLogoToBusinessDirectory(Business $business): void
    {
        $logo = $business->logo;

        if (! $logo || ! str_starts_with($logo, 'business-logos/incoming/')) {
            return;
        }

        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            return;
        }

        $filename = basename($logo);
        $destination = BusinessBranding::logoDirectoryFor($business).'/'.$filename;

        \Illuminate\Support\Facades\Storage::disk('public')->move($logo, $destination);
        $business->update(['logo' => $destination]);
    }
}
