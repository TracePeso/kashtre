<?php

namespace App\Http\Controllers;

use App\Models\KashtreHrModuleSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrModuleSettingsController extends Controller
{
    public function edit()
    {
        $this->authorizeAccess();

        return view('settings.hr-module.edit', [
            'settings' => KashtreHrModuleSetting::resolved(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'url' => ['nullable', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'sync_enabled' => ['sometimes', 'boolean'],
        ]);

        $settings = KashtreHrModuleSetting::resolved();

        $payload = [
            'url' => isset($validated['url']) ? rtrim((string) $validated['url'], '/') : null,
            'sync_enabled' => $request->boolean('sync_enabled'),
        ];

        // Keep existing key when the field is left blank (so we don't wipe secrets on save).
        if (array_key_exists('api_key', $validated) && filled($validated['api_key'])) {
            $payload['api_key'] = $validated['api_key'];
        }

        $settings->update($payload);
        KashtreHrModuleSetting::forgetCache();

        return redirect()
            ->route('settings.hr-module.edit')
            ->with('success', 'HR Module settings saved.');
    }

    private function authorizeAccess(): void
    {
        abort_unless((int) (Auth::user()?->business_id ?? 0) === 1, 403);
    }
}
