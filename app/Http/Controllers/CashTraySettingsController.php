<?php

namespace App\Http\Controllers;

use App\Models\KashtreCashTraySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashTraySettingsController extends Controller
{
    public function edit()
    {
        $this->authorizeAccess();

        return view('settings.cash-tray.edit', [
            'settings' => KashtreCashTraySetting::resolved(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_address' => 'nullable|string|max:1000',
            'tagline' => 'nullable|string|max:255',
            'powered_by_line' => 'nullable|string|max:255',
            'copyright_line' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'website_links' => 'nullable|array',
            'website_links.*.label' => 'required_with:website_links.*.url|nullable|string|max:100',
            'website_links.*.url' => 'nullable|url|max:500',
        ]);

        $links = collect($validated['website_links'] ?? [])
            ->map(fn (array $link): array => [
                'label' => trim((string) ($link['label'] ?? '')),
                'url' => trim((string) ($link['url'] ?? '')),
            ])
            ->filter(fn (array $link): bool => $link['label'] !== '' && $link['url'] !== '')
            ->values()
            ->all();

        $settings = KashtreCashTraySetting::resolved();
        $settings->update([
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_address' => $validated['contact_address'] ?? null,
            'tagline' => $validated['tagline'] ?? null,
            'powered_by_line' => $validated['powered_by_line'] ?? null,
            'copyright_line' => $validated['copyright_line'] ?? null,
            'website_links' => $links,
            'is_active' => $request->boolean('is_active'),
        ]);

        KashtreCashTraySetting::forgetCache();

        return redirect()
            ->route('settings.kashtre.edit')
            ->with('success', 'Kashtre settings saved.');
    }

    private function authorizeAccess(): void
    {
        abort_unless((int) (Auth::user()?->business_id ?? 0) === 1, 403);
    }
}
