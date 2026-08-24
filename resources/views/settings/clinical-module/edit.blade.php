<x-app-layout>
    <div class="min-h-screen bg-gray-50 py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <p class="text-sm text-gray-500">Master settings</p>
                <h2 class="mt-1 text-2xl font-bold text-gray-900">Clinical Module Settings</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Configure catalogue/client/queue APIs for Clinical and outbound encounter notifications.
                </p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('settings.clinical-module.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-4 py-5 sm:p-6 space-y-5">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Encounter webhook</h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Notify Clinical when a visit/encounter is opened so pending lab/imaging follow the patient.
                                </p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="encounter_webhook_enabled" value="1"
                                       @checked(old('encounter_webhook_enabled', $settings->encounter_webhook_enabled))
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Enabled
                            </label>
                        </div>

                        <div class="border-t border-gray-100 pt-5 space-y-4">
                            <div>
                                <label for="url" class="block text-sm font-medium text-gray-700">Clinical Module URL</label>
                                <input type="url" name="url" id="url"
                                       value="{{ old('url', $settings->url) }}"
                                       placeholder="https://clinical.example.com"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="service_key" class="block text-sm font-medium text-gray-700">Outbound service key</label>
                                <input type="password" name="service_key" id="service_key" value="" autocomplete="new-password"
                                       placeholder="{{ filled($settings->service_key) ? '••••••••  (leave blank to keep)' : 'Key Clinical issued to Main' }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Sent as <code class="text-xs">X-Service-Key</code> on outbound calls to Clinical.</p>
                                @error('service_key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="inbound_api_key" class="block text-sm font-medium text-gray-700">Inbound API key</label>
                                <input type="password" name="inbound_api_key" id="inbound_api_key" value="" autocomplete="new-password"
                                       placeholder="{{ filled($settings->inbound_api_key) ? '••••••••  (leave blank to keep)' : 'Key Clinical must send to Main' }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Required on <code class="text-xs">/api/catalogue/*</code>, <code class="text-xs">/api/clients/*</code>, <code class="text-xs">/api/queues</code>, <code class="text-xs">/api/events</code>, <code class="text-xs">/api/pharmacy/totes/*</code>.</p>
                                @error('inbound_api_key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-5 text-sm text-gray-600">
                            <p class="font-medium text-gray-900 mb-2">Exposed Main endpoints</p>
                            <ul class="list-disc list-inside space-y-1 text-xs">
                                <li><code>GET /api/catalogue/items?q=&amp;tenant_id=</code></li>
                                <li><code>GET /api/clients/{uuid|client_id}?tenant_id=</code></li>
                                <li><code>GET /api/queues?tenant_id=&amp;ward_code=</code></li>
                                <li><code>POST /api/events</code> (infant registration)</li>
                                <li><code>GET /api/pharmacy/totes/{handoff_ref}</code> (staged tote checklist)</li>
                            </ul>
                        </div>

                        <div class="border-t border-gray-100 pt-5 text-sm text-gray-600">
                            <p class="font-medium text-gray-900 mb-2">Outbound calls Main makes to Clinical</p>
                            <ul class="list-disc list-inside space-y-1 text-xs">
                                <li><code>POST {CLINICAL}/api/v1/clinical/encounters/created</code></li>
                                <li><code>POST {CLINICAL}/api/v1/clinical/pharmacy/totes/staged</code> (ward ready alert + checklist)</li>
                                <li><code>POST {CLINICAL}/api/v1/clinical/pharmacy/handoff/validate</code> (nurse 5-digit code)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-end">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            Save Clinical Module settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
