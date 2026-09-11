<x-app-layout>
    <div class="min-h-screen bg-gray-50 py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <p class="text-sm text-gray-500">Master settings</p>
                <h2 class="mt-1 text-2xl font-bold text-gray-900">HR Module Settings</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Configure how Kashtre connects to the HR Module for staff sync and SSO.
                </p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('settings.hr-module.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-4 py-5 sm:p-6 space-y-5">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Real-time user sync</h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    When enabled, user create/update events are pushed to the HR Module.
                                </p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="sync_enabled" value="1"
                                       @checked(old('sync_enabled', $settings->sync_enabled))
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Sync enabled
                            </label>
                        </div>

                        <div class="border-t border-gray-100 pt-5 space-y-4">
                            <div>
                                <label for="url" class="block text-sm font-medium text-gray-700">HR Module URL</label>
                                <input type="url" name="url" id="url"
                                       value="{{ old('url', $settings->url) }}"
                                       placeholder="https://hr.example.com"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-gray-500">
                                    Base URL used for sync (<code class="text-xs">/api/sync/users</code>) and SSO (<code class="text-xs">/auth/sso</code>).
                                </p>
                                @error('url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="api_key" class="block text-sm font-medium text-gray-700">Shared API key</label>
                                <input type="password" name="api_key" id="api_key"
                                       value=""
                                       autocomplete="new-password"
                                       placeholder="{{ filled($settings->api_key) ? '••••••••  (leave blank to keep current key)' : 'Enter shared secret' }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-gray-500">
                                    Sent as <code class="text-xs">X-API-Key</code> on outbound sync and required on inbound HR API calls.
                                    @if(filled($settings->api_key))
                                        A key is already saved.
                                    @endif
                                </p>
                                @error('api_key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-5">
                            <h3 class="text-sm font-semibold text-gray-900 mb-2">Status</h3>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <div class="rounded-md border border-gray-200 px-3 py-2">
                                    <dt class="text-xs text-gray-500">Connection</dt>
                                    <dd class="mt-0.5 font-medium {{ $settings->isConfigured() ? 'text-green-700' : 'text-amber-700' }}">
                                        {{ $settings->isConfigured() ? 'Configured' : 'Incomplete (URL and API key required)' }}
                                    </dd>
                                </div>
                                <div class="rounded-md border border-gray-200 px-3 py-2">
                                    <dt class="text-xs text-gray-500">SSO entry</dt>
                                    <dd class="mt-0.5 font-medium text-gray-800">
                                        <code class="text-xs">/hr-module/open</code>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            Save HR Module settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
