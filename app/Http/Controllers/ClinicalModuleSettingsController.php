<?php

namespace App\Http\Controllers;

use App\Models\KashtreClinicalModuleSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClinicalModuleSettingsController extends Controller
{
    public function edit()
    {
        $this->authorizeAccess();

        return view('settings.clinical-module.edit', [
            'settings' => KashtreClinicalModuleSetting::resolved(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'url' => ['nullable', 'url', 'max:500'],
            'service_key' => ['nullable', 'string', 'max:500'],
            'inbound_api_key' => ['nullable', 'string', 'max:500'],
            'encounter_webhook_enabled' => ['sometimes', 'boolean'],
        ]);

        $settings = KashtreClinicalModuleSetting::resolved();

        $payload = [
            'url' => isset($validated['url']) ? rtrim((string) $validated['url'], '/') : null,
            'encounter_webhook_enabled' => $request->boolean('encounter_webhook_enabled'),
        ];

        if (array_key_exists('service_key', $validated) && filled($validated['service_key'])) {
            $payload['service_key'] = $validated['service_key'];
        }

        if (array_key_exists('inbound_api_key', $validated) && filled($validated['inbound_api_key'])) {
            $payload['inbound_api_key'] = $validated['inbound_api_key'];
        }

        $settings->update($payload);
        KashtreClinicalModuleSetting::forgetCache();

        return redirect()
            ->route('settings.clinical-module.edit')
            ->with('success', 'Clinical Module settings saved.');
    }

    private function authorizeAccess(): void
    {
        abort_unless((int) (Auth::user()?->business_id ?? 0) === 1, 403);
    }
}
