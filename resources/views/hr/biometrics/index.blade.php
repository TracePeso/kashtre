<x-hr-layout>
    <x-slot name="header">Biometrics</x-slot>

    @php
        $activeBiometricPage = $activeBiometricPage ?? 'enrollment';
        $networkEntryRows = old('biometric_allowed_networks', $networkEntries ?? []);
        $networkEntryRows = is_array($networkEntryRows) ? array_values($networkEntryRows) : [];
        $networkEntryRows = $networkEntryRows === [] ? [['name' => '', 'service_provider' => '', 'network' => '']] : $networkEntryRows;
        $geofenceLocationRows = old('biometric_geofence_locations', $geofenceAccess['locations'] ?? []);
        $geofenceLocationRows = is_array($geofenceLocationRows) ? array_values($geofenceLocationRows) : [];
        $geofenceLocationRows = $geofenceLocationRows === [] ? [['name' => '', 'latitude' => '', 'longitude' => '', 'radius_meters' => $organization->biometric_geofence_radius_meters ?? 100, 'max_accuracy_meters' => $organization->biometric_geofence_max_accuracy_meters ?? 150]] : $geofenceLocationRows;
        $activeEnrollmentSession = $activeEnrollmentSession ?? null;
        $authorizationStaffAssignmentId = old('authorization_staff_assignment_id', $activeEnrollmentSession?->staff_assignment_id);
        $enrollmentWindowDeadline = $activeEnrollmentSession?->capture_deadline_at?->toIso8601String();
        $activeEnrollmentPurpose = $activeEnrollmentSession?->purpose === 're-enrollment' ? 're-enrollment' : 'enrollment';
        $activeProfiles = $profiles->where('status', 'active');
        $fingerprintCount = $activeProfiles->where('modality', 'fingerprint')->count();
        $faceCount = $activeProfiles->where('modality', 'face')->count();
        $successfulToday = $verifications
            ->filter(fn ($verification) => $verification->result === 'success' && $verification->verified_at?->isToday())
            ->count();
        $networkBlocked = ($networkAccess['enabled'] ?? false) && ! ($networkAccess['allowed'] ?? true);
        $networkStatus = ! ($networkAccess['enabled'] ?? false)
            ? 'Off'
            : (($networkAccess['allowed'] ?? false) ? 'Allowed' : 'Blocked');
        $networkStatusClasses = ! ($networkAccess['enabled'] ?? false)
            ? 'text-gray-900'
            : (($networkAccess['allowed'] ?? false) ? 'text-green-700' : 'text-red-700');
        $geofenceSetupBlocked = ($geofenceAccess['enabled'] ?? false) && ! ($geofenceAccess['configured'] ?? false);
        $geofenceStatus = ! ($geofenceAccess['enabled'] ?? false)
            ? 'Off'
            : (($geofenceAccess['configured'] ?? false) ? 'Ready' : 'Setup');
        $geofenceStatusClasses = ! ($geofenceAccess['enabled'] ?? false)
            ? 'text-gray-900'
            : (($geofenceAccess['configured'] ?? false) ? 'text-green-700' : 'text-red-700');
        $flaggedLateCount = $flaggedLateStaff->count();
        $lateFlagTriggerCount = max(1, (int) ($lateFlagTriggerCount ?? $organization->biometric_late_clock_in_repeat_count ?? 3));
    @endphp

    @if (session('status'))
        <div class="mb-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('biometric_verification'))
        @php($result = session('biometric_verification'))
        <div class="mb-6 rounded-md border p-4 text-sm font-medium {{ $result['passed'] ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' }}">
            {{ $result['message'] }}
        </div>
    @endif

    @if (! $organization)
        <div class="rounded-md border border-yellow-200 bg-yellow-50 p-5 text-sm text-yellow-900">
            No active organization is available for biometric enrollment.
        </div>
    @else
        @if ($activeBiometricPage === 'attendance')
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Fingerprint Profiles</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($fingerprintCount) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Face Profiles</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($faceCount) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Verified Today</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($successfulToday) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Office Network</p>
                    <p class="mt-1 text-2xl font-bold {{ $networkStatusClasses }}">{{ $networkStatus }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $networkAccess['ip'] ?? 'IP unavailable' }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Office Geofence</p>
                    <p class="mt-1 text-2xl font-bold {{ $geofenceStatusClasses }}">{{ $geofenceStatus }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ number_format($geofenceAccess['location_count'] ?? 0) }} configured location(s)</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Repeated Late Flags</p>
                    <p class="mt-1 text-2xl font-bold {{ $flaggedLateCount > 0 ? 'text-amber-700' : 'text-gray-900' }}">{{ number_format($flaggedLateCount) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Clock-in arrivals beyond the configured late threshold at least {{ $lateFlagTriggerCount }} time(s).</p>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Please review the biometric form.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($networkBlocked)
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">
                {{ $networkAccess['message'] }}
            </div>
        @endif

        @if ($geofenceSetupBlocked)
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">
                {{ $geofenceAccess['message'] }}
            </div>
        @endif

        @if ($canManageBiometrics && $activeBiometricPage === 'settings')
            <form method="POST" action="{{ route('hr.biometrics.network-policy') }}" class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                @csrf
                @method('PATCH')
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Office Network</h2>
                        <p class="mt-1 text-sm text-gray-500">Biometric enrollment and verification are checked against these networks.</p>
                        <p class="mt-3 text-xs font-medium {{ $networkStatusClasses }}">{{ $networkAccess['message'] }}</p>
                    </div>

                    <div class="lg:col-span-2">
                        <input type="hidden" name="biometric_network_restriction_enabled" value="{{ old('biometric_network_restriction_enabled', $organization->biometric_network_restriction_enabled) ? 1 : 0 }}">

                        <div class="mt-4 space-y-3" data-biometric-network-entries>
                            <div class="grid grid-cols-12 gap-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <span class="col-span-12 sm:col-span-3">Name</span>
                                <span class="col-span-12 sm:col-span-3">Service Provider</span>
                                <span class="col-span-12 sm:col-span-6">IP Address / CIDR Range</span>
                            </div>
                            @foreach ($networkEntryRows as $index => $networkEntry)
                                <div class="grid grid-cols-12 gap-3">
                                    <div class="col-span-12 sm:col-span-3">
                                        <label class="sr-only" for="biometric_allowed_network_name_{{ $index }}">Name</label>
                                        <input id="biometric_allowed_network_name_{{ $index }}" name="biometric_allowed_networks[{{ $index }}][name]" value="{{ $networkEntry['name'] ?? '' }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Main office">
                                    </div>
                                    <div class="col-span-12 sm:col-span-3">
                                        <label class="sr-only" for="biometric_allowed_network_provider_{{ $index }}">Service Provider</label>
                                        <input id="biometric_allowed_network_provider_{{ $index }}" name="biometric_allowed_networks[{{ $index }}][service_provider]" value="{{ $networkEntry['service_provider'] ?? '' }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="MTN">
                                    </div>
                                    <div class="col-span-12 sm:col-span-6">
                                        <label class="sr-only" for="biometric_allowed_network_{{ $index }}">IP Address / CIDR Range</label>
                                        <input id="biometric_allowed_network_{{ $index }}" name="biometric_allowed_networks[{{ $index }}][network]" value="{{ $networkEntry['network'] ?? '' }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="196.43.22.10 or 192.168.10.0/24">
                                    </div>
                                </div>
                            @endforeach
                            @for ($index = count($networkEntryRows); $index < count($networkEntryRows) + 3; $index++)
                                <div class="grid grid-cols-12 gap-3">
                                    <div class="col-span-12 sm:col-span-3">
                                        <label class="sr-only" for="biometric_allowed_network_name_{{ $index }}">Name</label>
                                        <input id="biometric_allowed_network_name_{{ $index }}" name="biometric_allowed_networks[{{ $index }}][name]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Branch name">
                                    </div>
                                    <div class="col-span-12 sm:col-span-3">
                                        <label class="sr-only" for="biometric_allowed_network_provider_{{ $index }}">Service Provider</label>
                                        <input id="biometric_allowed_network_provider_{{ $index }}" name="biometric_allowed_networks[{{ $index }}][service_provider]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Provider">
                                    </div>
                                    <div class="col-span-12 sm:col-span-6">
                                        <label class="sr-only" for="biometric_allowed_network_{{ $index }}">IP Address / CIDR Range</label>
                                        <input id="biometric_allowed_network_{{ $index }}" name="biometric_allowed_networks[{{ $index }}][network]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="IP address or CIDR range">
                                    </div>
                                </div>
                            @endfor
                        </div>

                        <button type="button" data-add-biometric-network class="mt-3 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Add IP Address
                        </button>

                        <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                            Save Office Network
                        </button>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('hr.biometrics.geofence-policy') }}" class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                @csrf
                @method('PATCH')
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Office Geofence</h2>
                        <p class="mt-1 text-sm text-gray-500">Biometric enrollment and verification require one of these office locations when enabled.</p>
                        <p data-geofence-settings-status class="mt-3 text-xs font-medium {{ $geofenceStatusClasses }}">{{ $geofenceAccess['message'] }}</p>
                    </div>

                    <div class="lg:col-span-2">
                        <input type="hidden" name="biometric_geofence_enabled" value="{{ old('biometric_geofence_enabled', $organization->biometric_geofence_enabled) ? 1 : 0 }}">

                        <div class="mt-4 space-y-3" data-biometric-geofence-locations>
                            @foreach ($geofenceLocationRows as $index => $locationEntry)
                                <div class="rounded-lg border border-gray-200 p-4" data-geofence-location-row>
                                    <div class="grid grid-cols-12 gap-3">
                                        <div class="col-span-12 lg:col-span-3">
                                            <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_name_{{ $index }}">Location Name</label>
                                            <input id="biometric_geofence_location_name_{{ $index }}" name="biometric_geofence_locations[{{ $index }}][name]" value="{{ $locationEntry['name'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Main office">
                                        </div>
                                        <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_latitude_{{ $index }}">Latitude</label>
                                            <input id="biometric_geofence_location_latitude_{{ $index }}" name="biometric_geofence_locations[{{ $index }}][latitude]" type="number" step="0.0000001" min="-90" max="90" value="{{ $locationEntry['latitude'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                        </div>
                                        <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_longitude_{{ $index }}">Longitude</label>
                                            <input id="biometric_geofence_location_longitude_{{ $index }}" name="biometric_geofence_locations[{{ $index }}][longitude]" type="number" step="0.0000001" min="-180" max="180" value="{{ $locationEntry['longitude'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                        </div>
                                        <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_radius_{{ $index }}">Radius (m)</label>
                                            <input id="biometric_geofence_location_radius_{{ $index }}" name="biometric_geofence_locations[{{ $index }}][radius_meters]" type="number" min="25" max="50000" value="{{ $locationEntry['radius_meters'] ?? 100 }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                        </div>
                                        <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_accuracy_{{ $index }}">Max GPS Accuracy (m)</label>
                                            <input id="biometric_geofence_location_accuracy_{{ $index }}" name="biometric_geofence_locations[{{ $index }}][max_accuracy_meters]" type="number" min="5" max="5000" value="{{ $locationEntry['max_accuracy_meters'] ?? 150 }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                        </div>
                                        <div class="col-span-12 lg:col-span-1">
                                            <label class="block text-sm font-medium text-transparent" aria-hidden="true">Action</label>
                                            <button type="button" data-geofence-current-location class="mt-1 inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                                Use Current
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" data-add-biometric-geofence-location class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Add Geofence Location
                            </button>
                            <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                                Save Geofence Locations
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('hr.biometrics.clock-settings') }}" class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                @csrf
                @method('PATCH')
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Clock-In Settings</h2>
                        <p class="mt-1 text-sm text-gray-500">Flag staff who clock in more than the configured minutes late after the configured number of repeat late arrivals.</p>
                    </div>

                    <div class="lg:col-span-2">
                        <input type="hidden" name="biometric_late_clock_in_enabled" value="0">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="biometric_late_clock_in_enabled" value="1" class="rounded border-gray-300 text-brand-blue shadow-sm focus:ring-brand-blue" @checked(old('biometric_late_clock_in_enabled', $organization->biometric_late_clock_in_enabled))>
                            Enable repeated late clock-in flag
                        </label>

                        <div class="mt-4 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="biometric_late_clock_in_threshold_minutes">Late threshold (minutes)</label>
                                <input id="biometric_late_clock_in_threshold_minutes" name="biometric_late_clock_in_threshold_minutes" type="number" min="1" max="720" value="{{ old('biometric_late_clock_in_threshold_minutes', $organization->biometric_late_clock_in_threshold_minutes ?? 10) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                <p class="mt-2 text-xs text-gray-500">Example: with 10 minutes configured, an 8:00 shift is counted as late from 8:11 onward.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="biometric_late_clock_in_repeat_count">Repeat late count</label>
                                <input id="biometric_late_clock_in_repeat_count" name="biometric_late_clock_in_repeat_count" type="number" min="1" max="365" value="{{ old('biometric_late_clock_in_repeat_count', $lateFlagTriggerCount) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                <p class="mt-2 text-xs text-gray-500">Flag and notify staff after this many accepted late clock-ins beyond the threshold.</p>
                            </div>
                        </div>

                        <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                            Save Clock-In Settings
                        </button>

                        <div class="mt-5 rounded-md border border-gray-200 bg-gray-50 px-4 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">Flagged Staff</h3>
                                    <p class="mt-1 text-xs text-gray-500">Shown after {{ $lateFlagTriggerCount }} accepted late clock-in{{ $lateFlagTriggerCount === 1 ? '' : 's' }} beyond the configured threshold.</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $flaggedLateCount }}</span>
                            </div>

                            @if($flaggedLateStaff->isEmpty())
                                <p class="mt-4 text-sm text-gray-500">No staff have reached the repeated lateness flag yet.</p>
                            @else
                                <div class="mt-4 space-y-3">
                                    @foreach($flaggedLateStaff as $lateStaff)
                                        <div class="rounded-md border border-amber-200 bg-white px-3 py-3">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="text-sm font-semibold text-gray-900">{{ $lateStaff['staff_name'] }}</p>
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-800">
                                                    {{ $lateStaff['late_count'] }} late clock-ins
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">
                                                Worst lateness: {{ $lateStaff['worst_minutes_late'] ?? 0 }} min
                                                @if($lateStaff['last_late_at'])
                                                    / Last flagged late punch: {{ $lateStaff['last_late_at']->format('M j, Y H:i') }}
                                                @endif
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        @endif

        @if ($canManageBiometrics && $activeBiometricPage === 'attendance')
            {{-- Legacy offline clocking flow is intentionally disabled in HR.
            <div class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <form method="POST" action="{{ route('hr.biometrics.legacy-devices.store') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    @csrf
                    <div class="mb-5">
                        <h2 class="text-base font-semibold text-gray-900">Register Biometric Machine</h2>
                        <p class="mt-1 text-sm text-gray-500">Save each legacy clocking machine once so users can pick it during offline clocking uploads instead of retyping provider and terminal details.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="legacy_device_name">Machine Name</label>
                            <input id="legacy_device_name" name="legacy_device_name" value="{{ old('legacy_device_name') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Main Door Terminal">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="legacy_device_provider">Provider</label>
                            <input id="legacy_device_provider" name="legacy_device_provider" value="{{ old('legacy_device_provider', 'zkteco') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="legacy_device_identifier">Device ID</label>
                            <input id="legacy_device_identifier" name="legacy_device_identifier" value="{{ old('legacy_device_identifier') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="ZK-MAIN-001">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="legacy_device_location">Location</label>
                            <input id="legacy_device_location" name="legacy_device_location" value="{{ old('legacy_device_location') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Main Entrance">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700" for="legacy_device_notes">Notes</label>
                        <textarea id="legacy_device_notes" name="legacy_device_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Optional machine notes">{{ old('legacy_device_notes') }}</textarea>
                    </div>

                    <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                        Register Machine
                    </button>
                </form>

                <form method="POST" action="{{ route('hr.biometrics.legacy-device-import') }}" enctype="multipart/form-data" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    @csrf
                    <div class="mb-5">
                        <h2 class="text-base font-semibold text-gray-900">Upload Offline Clocking Data</h2>
                        <p class="mt-1 text-sm text-gray-500">Choose a registered machine and upload the CSV export generated from that device.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="legacy_biometric_device_id">Registered Machine</label>
                        <select id="legacy_biometric_device_id" name="legacy_biometric_device_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" @disabled($legacyDevices->isEmpty())>
                            <option value="">Select machine</option>
                            @foreach ($legacyDevices as $legacyDevice)
                                <option value="{{ $legacyDevice->id }}" @selected((string) old('legacy_biometric_device_id') === (string) $legacyDevice->id)>
                                    {{ $legacyDevice->name }} | {{ strtoupper($legacyDevice->provider) }} | {{ $legacyDevice->device_id }}
                                </option>
                            @endforeach
                        </select>
                        @if($legacyDevices->isEmpty())
                            <p class="mt-2 text-xs text-amber-700">Register at least one machine before uploading offline clocking data.</p>
                        @endif
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700" for="legacy_device_file">Clocking CSV File</label>
                        <input id="legacy_device_file" name="legacy_device_file" type="file" accept=".csv,.txt,text/csv" class="mt-1 block w-full rounded-md border border-gray-300 text-sm shadow-sm file:mr-4 file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white focus:border-brand-blue focus:ring-brand-blue">
                    </div>

                    <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2" @disabled($legacyDevices->isEmpty())>
                        Upload Clocking Data
                    </button>
                </form>
            </div>

            <div class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Registered Machines</h2>
                        <p class="mt-1 text-sm text-gray-500">Saved biometric machines available for offline clocking uploads.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $legacyDevices->count() }}</span>
                </div>

                @if ($legacyDevices->isEmpty())
                    <p class="mt-4 text-sm text-gray-500">No biometric machines have been registered yet.</p>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($legacyDevices as $legacyDevice)
                            <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900">{{ $legacyDevice->name }}</p>
                                    <span class="rounded-full {{ $legacyDevice->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }} px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide">
                                        {{ $legacyDevice->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ strtoupper($legacyDevice->provider) }} / {{ $legacyDevice->device_id }}
                                    @if($legacyDevice->location)
                                        / {{ $legacyDevice->location }}
                                    @endif
                                </p>
                                @if($legacyDevice->last_synced_at)
                                    <p class="mt-1 text-xs text-gray-500">Last upload: {{ $legacyDevice->last_synced_at->format('M j, Y H:i') }}</p>
                                @endif
                                @if($legacyDevice->notes)
                                    <p class="mt-2 text-sm text-gray-600">{{ $legacyDevice->notes }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            --}}
        @endif

        @if ($canManageBiometrics && $activeBiometricPage === 'enrollment')
            <div id="biometric-enrollment" class="mb-8 space-y-6 scroll-mt-6">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <form method="POST" action="{{ route('hr.biometrics.enrollment-authorization.send') }}" class="space-y-4">
                            @csrf
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Authorize Personal Device</h2>
                                <p class="mt-1 text-sm text-gray-500">HR must send and confirm a secret code before face capture and fingerprint enrollment can begin.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="authorization_staff_assignment_id">Staff Member</label>
                                <select id="authorization_staff_assignment_id" name="staff_assignment_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                    <option value="">Choose staff</option>
                                    @foreach ($staffAssignments as $staffAssignment)
                                        <option value="{{ $staffAssignment->id }}" @selected((string) $authorizationStaffAssignmentId === (string) $staffAssignment->id)>
                                            {{ $staffAssignment->staff_name }} @if($staffAssignment->staff_title) - {{ $staffAssignment->staff_title }} @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                                Send Secret Code
                            </button>
                        </form>

                        <div class="rounded-md border border-dashed border-gray-200 bg-gray-50 p-4">
                            @if ($activeEnrollmentSession)
                                <p class="text-sm font-semibold text-gray-900">Current Authorization</p>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ $activeEnrollmentSession->staff_name }} / {{ $activeEnrollmentSession->recipient_email }}
                                </p>
                                <p class="mt-2 text-xs text-gray-500">
                                    Purpose: {{ $activeEnrollmentPurpose === 're-enrollment' ? 'Re-enrollment' : 'Enrollment' }}
                                </p>
                                @if ($activeEnrollmentSession->confirmed_at)
                                    <p class="mt-2 text-xs font-medium text-green-700">
                                        Code confirmed. Capture window ends at {{ $activeEnrollmentSession->capture_deadline_at?->format('M j, Y H:i:s') }}.
                                    </p>
                                @else
                                    <p class="mt-2 text-xs font-medium text-gray-600">
                                        Code sent at {{ $activeEnrollmentSession->secret_code_sent_at?->format('M j, Y H:i') }} and expires at {{ $activeEnrollmentSession->secret_code_expires_at?->format('M j, Y H:i') }}.
                                    </p>
                                @endif
                            @else
                                <p class="text-sm text-gray-500">No active biometric authorization is open yet.</p>
                            @endif
                        </div>
                    </div>

                    @if ($activeEnrollmentSession && ! $activeEnrollmentSession->confirmed_at)
                        <form method="POST" action="{{ route('hr.biometrics.enrollment-authorization.confirm') }}" class="mt-6 grid grid-cols-1 gap-4 border-t border-gray-200 pt-5 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                            @csrf
                            <input type="hidden" name="enrollment_session_uuid" value="{{ $activeEnrollmentSession->uuid }}">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="secret_code">Secret Code</label>
                                <input id="secret_code" name="secret_code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Enter 6-digit code">
                            </div>
                            <div class="md:self-end">
                                <p class="text-xs text-gray-500">Confirm the emailed code before the fingerprint prompt can start.</p>
                            </div>
                            <div class="md:self-end">
                                <button type="submit" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                                    Confirm Code
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                @if ($activeEnrollmentSession && $activeEnrollmentSession->confirmed_at && $activeEnrollmentSession->capture_deadline_at && $activeEnrollmentSession->capture_deadline_at->isFuture())
                    <form method="POST" action="{{ route('hr.biometrics.secure-enrollment') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" data-biometric-form data-secure-enrollment-form>
                        @csrf
                        <input type="hidden" name="enrollment_session_uuid" value="{{ $activeEnrollmentSession->uuid }}">
                        <input type="hidden" name="staff_assignment_id" value="{{ $activeEnrollmentSession->staff_assignment_id }}">
                        <input type="hidden" name="fingerprint_credential" id="fingerprint_credential">
                        <input type="hidden" name="face_descriptor" id="enroll_face_descriptor">
                        <input type="hidden" name="face_sample" id="enroll_face_sample">
                        <input type="hidden" name="face_photo" id="enroll_face_photo">
                        <input type="hidden" name="quality_score" id="enroll_face_quality_score">
                        <input type="hidden" name="face_protocol_version" id="enroll_face_protocol_version">
                        <input type="hidden" name="face_liveness_passed" id="enroll_face_liveness_passed">
                        <input type="hidden" name="face_liveness_challenge" id="enroll_face_liveness_challenge">
                        <input type="hidden" name="face_sample_count" id="enroll_face_sample_count">
                        <input type="hidden" name="face_detection_status" id="enroll_face_detection_status">
                        <input type="hidden" name="face_quality_min" id="enroll_face_quality_min">
                        <input type="hidden" name="face_quality_average" id="enroll_face_quality_average">
                        <input type="hidden" name="capture_source" value="browser_camera">
                        <input type="hidden" name="geo_latitude" data-geo-latitude>
                        <input type="hidden" name="geo_longitude" data-geo-longitude>
                        <input type="hidden" name="geo_accuracy" data-geo-accuracy>

                        <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Complete Secure Biometric {{ $activeEnrollmentPurpose === 're-enrollment' ? 'Re-enrollment' : 'Enrollment' }}</h2>
                                <p class="mt-1 text-sm text-gray-500">{{ $activeEnrollmentSession->staff_name }} must complete both fingerprint and face capture in the same authorized session.</p>
                            </div>
                            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                <p class="font-semibold">2-minute capture window</p>
                                <p class="mt-1 text-xs" data-enrollment-deadline="{{ $enrollmentWindowDeadline }}">
                                    Ends at {{ $activeEnrollmentSession->capture_deadline_at->format('M j, Y H:i:s') }}
                                </p>
                                <p class="mt-1 text-xs font-medium" id="enrollment_window_status">Capture both face and fingerprint before the timer runs out.</p>
                            </div>
                        </div>

                        <div class="mb-5 rounded-md border border-gray-200 bg-gray-50 p-4">
                            <p class="text-sm font-semibold text-gray-900">Authorized Staff Member</p>
                            <p class="mt-1 text-sm text-gray-600">{{ $activeEnrollmentSession->staff_name }}</p>
                            <p class="mt-1 text-xs text-gray-500">Secret code recipient: {{ $activeEnrollmentSession->recipient_email }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">Fingerprint</h3>
                                    <p class="mt-1 text-sm text-gray-500">Open this page on the staff member's phone and complete the fingerprint prompt after code confirmation.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="fingerprint_label">Label</label>
                                        <input id="fingerprint_label" name="fingerprint_label" value="{{ old('fingerprint_label', 'Fingerprint') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="fingerprint_device_id">Device ID</label>
                                        <input id="fingerprint_device_id" name="fingerprint_device_id" value="{{ old('fingerprint_device_id') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700" for="fingerprint_threshold">Threshold</label>
                                    <input id="fingerprint_threshold" name="fingerprint_verification_threshold" type="number" min="0" max="1" step="0.0001" value="{{ old('fingerprint_verification_threshold', '0.98') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                </div>

                                <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                                    <p class="text-sm font-semibold text-gray-900">Fingerprint registration</p>
                                    <p class="mt-1 text-sm text-gray-500">The device prompt remains locked until the secret code is confirmed for this session.</p>
                                    <p id="fingerprint_status" class="mt-2 text-xs text-gray-500">No fingerprint registered in this session yet.</p>
                                    <button type="button" data-mobile-fingerprint-register class="mt-3 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                        Register Fingerprint
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">Face Capture</h3>
                                    <p class="mt-1 text-sm text-gray-500">Use the guided live face capture flow for the same staff member inside the current authorization window.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="face_label">Label</label>
                                        <input id="face_label" name="face_label" value="{{ old('face_label', 'Primary face') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="face_threshold">Threshold</label>
                                        <input id="face_threshold" name="face_verification_threshold" type="number" min="0" max="1" step="0.0001" value="{{ old('face_verification_threshold', '0.86') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="face_provider">Provider</label>
                                        <input id="face_provider" name="face_provider" value="{{ old('face_provider', 'browser-camera') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="face_device_id">Device ID</label>
                                        <input id="face_device_id" name="face_device_id" value="{{ old('face_device_id') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                                    </div>
                                </div>

                                <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                                    <video data-face-video="enroll" class="h-56 w-full rounded-md bg-gray-900 object-cover" autoplay muted playsinline></video>
                                    <canvas data-face-canvas="enroll" class="hidden"></canvas>
                                    <img data-face-preview="enroll" alt="Captured face preview" class="mt-3 hidden h-40 w-40 rounded-md border border-gray-200 object-cover">
                                    <p data-face-status="enroll" class="mt-2 text-xs text-gray-500">Camera not started.</p>
                                    <p data-face-quality="enroll" class="mt-1 text-xs text-gray-500">Quality score and captured photo preview will appear after capture.</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button type="button" data-face-start="enroll" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                            Start Camera
                                        </button>
                                        <button type="button" data-face-capture="enroll" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                            Capture Face
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                                Complete Secure Enrollment
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        @if ($activeBiometricPage === 'clocking')
        <div class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-gray-900">Clocking</h2>
                <p class="mt-1 text-sm text-gray-500">Use this page only to register attendance when staff clock in or clock out. The action completes after the office network and enabled geofence checks pass.</p>
            </div>

            <form method="POST" action="{{ route('hr.biometrics.verify') }}" class="space-y-4" data-biometric-form>
                @csrf
                <input type="hidden" name="modality" value="fingerprint">
                <input type="hidden" name="fingerprint_assertion" id="verify_fingerprint_assertion">
                <input type="hidden" name="external_reference" id="verify_external_reference">
                <input type="hidden" name="capture_source" value="mobile_fingerprint">
                <input type="hidden" name="punch_type" id="verify_punch_type" value="{{ old('punch_type', 'in') }}">
                <input type="hidden" name="geo_latitude" data-geo-latitude>
                <input type="hidden" name="geo_longitude" data-geo-longitude>
                <input type="hidden" name="geo_accuracy" data-geo-accuracy>

                <div class="rounded-lg border border-sky-200 bg-sky-50 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-sky-900">Fingerprint attendance actions</p>
                            <p class="mt-1 text-sm text-sky-800">Use the buttons below to authenticate with the staff member's fingerprint and register `Clock In` or `Clock Out`.</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" data-punch-submit data-punch-type="in" data-punch-label="Clock In" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                                Clock In
                            </button>
                            <button type="submit" data-punch-submit data-punch-type="out" data-punch-label="Clock Out" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                                Clock Out
                            </button>
                        </div>
                    </div>
                        <p id="verify_fingerprint_status" class="mt-3 text-xs text-sky-800">Choose `Clock In` or `Clock Out` to start fingerprint verification.</p>
                </div>
            </form>
        </div>

        @endif

        @if ($activeBiometricPage === 'attendance')
        <div class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-gray-900">Biometric Attendance</h2>
                <p class="mt-1 text-sm text-gray-500">Use this page for biometric attendance verification details, staff/profile matching, and manual verification support.</p>
            </div>

            <form method="POST" action="{{ route('hr.biometrics.verify') }}" class="space-y-4" data-biometric-form>
                @csrf
                <input type="hidden" name="face_descriptor" id="verify_face_descriptor">
                <input type="hidden" name="face_sample" id="verify_face_sample">
                <input type="hidden" name="quality_score" id="verify_face_quality_score">
                <input type="hidden" name="face_protocol_version" id="verify_face_protocol_version">
                <input type="hidden" name="face_liveness_passed" id="verify_face_liveness_passed">
                <input type="hidden" name="face_liveness_challenge" id="verify_face_liveness_challenge">
                <input type="hidden" name="face_sample_count" id="verify_face_sample_count">
                <input type="hidden" name="face_detection_status" id="verify_face_detection_status">
                <input type="hidden" name="face_quality_min" id="verify_face_quality_min">
                <input type="hidden" name="face_quality_average" id="verify_face_quality_average">
                <input type="hidden" name="fingerprint_assertion" id="verify_fingerprint_assertion">
                <input type="hidden" name="capture_source" value="browser_camera">
                <input type="hidden" name="punch_type" id="verify_punch_type" value="{{ old('punch_type', 'in') }}">
                <input type="hidden" name="geo_latitude" data-geo-latitude>
                <input type="hidden" name="geo_longitude" data-geo-longitude>
                <input type="hidden" name="geo_accuracy" data-geo-accuracy>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="verify_modality">Mode</label>
                        <select id="verify_modality" name="modality" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            <option value="fingerprint">Fingerprint</option>
                            <option value="face">Face</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="verify_staff_assignment_id">Expected Staff</label>
                        <select id="verify_staff_assignment_id" name="staff_assignment_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            <option value="">Identify across all active profiles</option>
                            @foreach ($staffAssignments as $staffAssignment)
                                <option value="{{ $staffAssignment->id }}">{{ $staffAssignment->staff_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="verify_profile_uuid">Specific Profile</label>
                        <select id="verify_profile_uuid" name="profile_uuid" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            <option value="">Use best match</option>
                            @foreach ($activeProfiles as $profile)
                                <option value="{{ $profile->uuid }}">{{ $profile->staff_name }} - {{ ucfirst($profile->modality) }} - {{ $profile->label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                        <p class="text-sm font-semibold text-gray-900">Fingerprint check</p>
                        <p class="mt-1 text-sm text-gray-500">Use the staff member's mobile device to approve the fingerprint prompt before manual verification completes.</p>
                        <p id="verify_fingerprint_status" class="mt-2 text-xs text-gray-500">No fingerprint check captured yet.</p>
                        <button type="button" data-mobile-fingerprint-verify class="mt-3 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                            Capture Fingerprint Only
                        </button>
                    </div>

                    <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                        <video data-face-video="verify" class="h-48 w-full rounded-md bg-gray-900 object-cover" autoplay muted playsinline></video>
                        <canvas data-face-canvas="verify" class="hidden"></canvas>
                        <p data-face-status="verify" class="mt-2 text-xs text-gray-500">Camera not started.</p>
                        <p data-face-quality="verify" class="mt-1 text-xs text-gray-500">Quality score will appear after capture.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" data-face-start="verify" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Start Camera
                            </button>
                            <button type="button" data-face-capture="verify" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                Capture Face
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="verify_provider">Provider</label>
                        <input id="verify_provider" name="provider" value="local" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="verify_device_id">Device ID</label>
                        <input id="verify_device_id" name="device_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="verify_external_reference">Credential Reference</label>
                        <input id="verify_external_reference" name="external_reference" readonly class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="verify_match_score">Device Score</label>
                        <input id="verify_match_score" name="match_score" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                    Run Manual Verification
                </button>
            </form>
        </div>
        @endif

        @if ($activeBiometricPage === 'enrollment')
        <div class="mb-8 rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 p-5">
                <h2 class="text-base font-semibold text-gray-900">Enrolled Profiles</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3">Provider</th>
                            <th class="px-4 py-3">Quality</th>
                            <th class="px-4 py-3">Threshold</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last Verified</th>
                            @if($canManageBiometrics)
                                <th class="px-4 py-3">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($profiles as $profile)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $profile->staff_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $profile->label ?? 'No label' }}</p>
                                </td>
                                <td class="px-4 py-3 capitalize">{{ $profile->modality }}</td>
                                <td class="px-4 py-3">
                                    <p>{{ $profile->provider }}</p>
                                    @if($profile->device_id)
                                        <p class="text-xs text-gray-500">{{ $profile->device_id }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $profile->quality_score !== null ? number_format($profile->quality_score, 1) . '/100' : 'n/a' }}</td>
                                <td class="px-4 py-3">{{ number_format(($profile->verification_threshold ?? 0) * 100, 1) }}%</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $profile->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                        {{ ucfirst($profile->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $profile->last_verified_at?->format('M j, Y H:i') ?? 'Never' }}</td>
                                @if($canManageBiometrics)
                                    <td class="px-4 py-3">
                                        @if($profile->status === 'active')
                                            <form method="POST" action="{{ route('hr.biometrics.destroy', $profile) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                    Deactivate
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-400">Inactive</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageBiometrics ? 8 : 7 }}" class="px-4 py-8 text-center text-gray-500">
                                    No biometric profiles have been enrolled yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($activeBiometricPage === 'attendance')
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 p-5">
                <h2 class="text-base font-semibold text-gray-900">Recent Verification Attempts</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3">Punch</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Result</th>
                            <th class="px-4 py-3">Score</th>
                            <th class="px-4 py-3">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($verifications as $verification)
                            <tr>
                                <td class="px-4 py-3">{{ $verification->verified_at?->format('M j, Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $verification->staffAssignment?->staff_name ?? $verification->staff_uuid ?? 'No match' }}</td>
                                <td class="px-4 py-3 capitalize">{{ $verification->modality }}</td>
                                <td class="px-4 py-3">
                                    @if($verification->attendanceLedger)
                                        <p class="capitalize">{{ $verification->attendanceLedger->punch_type }}</p>
                                        <p class="text-xs text-gray-500">{{ $verification->attendanceLedger->status }}</p>
                                        @if($verification->attendanceLedger->is_late_flagged)
                                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-amber-700">Flagged late</p>
                                        @elseif($verification->attendanceLedger->is_late_clock_in)
                                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-amber-700">
                                                Late by {{ $verification->attendanceLedger->minutes_late }} min
                                            </p>
                                        @endif
                                    @else
                                        <span class="text-gray-400">n/a</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <p>{{ $verification->provider }}</p>
                                    @if($verification->device_id)
                                        <p class="text-xs text-gray-500">{{ $verification->device_id }}</p>
                                    @endif
                                    @if($verification->event_type)
                                        <p class="text-xs text-gray-500">{{ $verification->event_type }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $verification->result === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ucfirst($verification->result) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $verification->score !== null ? number_format($verification->score * 100, 1) . '%' : 'n/a' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $verification->failure_reason ?? 'Verified' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    No biometric checks have been recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <script>
            (() => {
                const streams = {};
                const mobileFingerprintOptionsUrl = @json(route('hr.biometrics.mobile-fingerprint.options'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const geofenceEnabled = @json($geofenceAccess['enabled'] ?? false);
                const secureEnrollmentDeadline = document.querySelector('[data-enrollment-deadline]')?.dataset.enrollmentDeadline || null;

                function bufferToBase64Url(buffer) {
                    const bytes = new Uint8Array(buffer);
                    let binary = '';

                    bytes.forEach((byte) => binary += String.fromCharCode(byte));

                    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
                }

                function base64UrlToBuffer(value) {
                    const padded = value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4);
                    const binary = atob(padded);
                    const bytes = new Uint8Array(binary.length);

                    for (let index = 0; index < binary.length; index++) {
                        bytes[index] = binary.charCodeAt(index);
                    }

                    return bytes.buffer;
                }

                function preparePublicKeyOptions(options) {
                    options.challenge = base64UrlToBuffer(options.challenge);

                    if (options.user?.id) {
                        options.user.id = base64UrlToBuffer(options.user.id);
                    }

                    if (Array.isArray(options.excludeCredentials)) {
                        options.excludeCredentials = options.excludeCredentials.map((credential) => ({
                            ...credential,
                            id: base64UrlToBuffer(credential.id),
                        }));
                    }

                    if (Array.isArray(options.allowCredentials)) {
                        options.allowCredentials = options.allowCredentials.map((credential) => ({
                            ...credential,
                            id: base64UrlToBuffer(credential.id),
                        }));
                    }

                    return options;
                }

                function browserPosition() {
                    if (!navigator.geolocation) {
                        throw new Error('Location access is not available in this browser.');
                    }

                    return new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, {
                            enableHighAccuracy: true,
                            timeout: 12000,
                            maximumAge: 0,
                        });
                    });
                }

                function setGeofenceSettingsStatus(message, ok = false) {
                    const el = document.querySelector('[data-geofence-settings-status]');

                    if (!el) {
                        return;
                    }

                    const lowerMessage = message.toLowerCase();
                    const isError = lowerMessage.includes('failed')
                        || lowerMessage.includes('denied')
                        || lowerMessage.includes('not available')
                        || lowerMessage.includes('outside')
                        || lowerMessage.includes('require');

                    el.textContent = message;
                    el.classList.toggle('text-green-700', ok);
                    el.classList.toggle('text-red-700', !ok && isError);
                    el.classList.toggle('text-gray-500', !ok && !isError);
                }

                function fillGeolocationFields(form, position) {
                    const latitude = position.coords.latitude.toFixed(7);
                    const longitude = position.coords.longitude.toFixed(7);
                    const accuracy = Math.round(position.coords.accuracy);

                    form.querySelectorAll('[name="geo_latitude"]').forEach((input) => input.value = latitude);
                    form.querySelectorAll('[name="geo_longitude"]').forEach((input) => input.value = longitude);
                    form.querySelectorAll('[name="geo_accuracy"]').forEach((input) => input.value = accuracy);

                    return { latitude, longitude, accuracy };
                }

                async function collectGeolocation(form) {
                    if (!geofenceEnabled) {
                        return null;
                    }

                    setGeofenceSettingsStatus('Confirming current location...');
                    const position = await browserPosition();
                    const location = fillGeolocationFields(form, position);
                    setGeofenceSettingsStatus(`Location captured with ${location.accuracy}m accuracy.`, true);

                    return location;
                }

                async function mobileFingerprintOptions(action, form) {
                    await collectGeolocation(form);

                    const response = await fetch(mobileFingerprintOptionsUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            action,
                            staff_assignment_id: form.querySelector('[name="staff_assignment_id"]')?.value || null,
                            profile_uuid: form.querySelector('[name="profile_uuid"]')?.value || null,
                            enrollment_session_uuid: form.querySelector('[name="enrollment_session_uuid"]')?.value || null,
                            geo_latitude: form.querySelector('[name="geo_latitude"]')?.value || null,
                            geo_longitude: form.querySelector('[name="geo_longitude"]')?.value || null,
                            geo_accuracy: form.querySelector('[name="geo_accuracy"]')?.value || null,
                        }),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                        throw new Error(firstError || 'The fingerprint prompt could not be started.');
                    }

                    return preparePublicKeyOptions(payload.publicKey);
                }

                function mobileFingerprintStatus(id, message, ok = false) {
                    const el = document.getElementById(id);

                    if (!el) {
                        return;
                    }

                    const lowerMessage = message.toLowerCase();
                    const isError = lowerMessage.includes('failed')
                        || lowerMessage.includes('require')
                        || lowerMessage.includes('not allowed')
                        || lowerMessage.includes('location');

                    el.textContent = message;
                    el.classList.toggle('text-green-600', ok);
                    el.classList.toggle('text-red-600', !ok && isError);
                    el.classList.toggle('text-gray-500', !ok && !isError);
                }

                function updateEnrollmentWindowStatus() {
                    const status = document.getElementById('enrollment_window_status');
                    const secureForm = document.querySelector('[data-secure-enrollment-form]');

                    if (!status || !secureEnrollmentDeadline) {
                        return;
                    }

                    const deadline = new Date(secureEnrollmentDeadline);
                    const remainingMs = deadline.getTime() - Date.now();

                    if (remainingMs <= 0) {
                        status.textContent = 'The 2-minute enrollment window expired. Send a new secret code to restart.';
                        status.classList.add('text-red-700');
                        secureForm?.querySelectorAll('button, input, select').forEach((element) => {
                            if (element.name !== '_token') {
                                element.disabled = true;
                            }
                        });
                        return;
                    }

                    const remainingSeconds = Math.floor(remainingMs / 1000);
                    const minutes = Math.floor(remainingSeconds / 60);
                    const seconds = remainingSeconds % 60;
                    status.textContent = `Time remaining: ${minutes}:${String(seconds).padStart(2, '0')}. Capture both face and fingerprint before it ends.`;
                }

                function credentialToJson(credential) {
                    return {
                        id: credential.id,
                        type: credential.type,
                        rawId: bufferToBase64Url(credential.rawId),
                        response: {
                            clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                            attestationObject: credential.response.attestationObject
                                ? bufferToBase64Url(credential.response.attestationObject)
                                : undefined,
                            authenticatorData: credential.response.authenticatorData
                                ? bufferToBase64Url(credential.response.authenticatorData)
                                : undefined,
                            signature: credential.response.signature
                                ? bufferToBase64Url(credential.response.signature)
                                : undefined,
                            userHandle: credential.response.userHandle
                                ? bufferToBase64Url(credential.response.userHandle)
                                : undefined,
                            transports: typeof credential.response.getTransports === 'function'
                                ? credential.response.getTransports()
                                : [],
                        },
                    };
                }

                function setStatus(target, message, ok = false) {
                    const el = document.querySelector(`[data-face-status="${target}"]`);

                    if (!el) {
                        return;
                    }

                    el.textContent = message;
                    el.classList.toggle('text-green-600', ok);
                    el.classList.toggle('text-gray-500', !ok);
                }

                async function startCamera(target) {
                    const video = document.querySelector(`[data-face-video="${target}"]`);

                    if (!video || !navigator.mediaDevices?.getUserMedia) {
                        setStatus(target, 'Camera access is not available in this browser.');
                        return null;
                    }

                    if (!streams[target]) {
                        streams[target] = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 720 } },
                            audio: false,
                        });
                    }

                    video.srcObject = streams[target];
                    await video.play();
                    setStatus(target, 'Camera ready.');

                    return video;
                }

                const faceCaptureProtocolVersion = 'face-capture-v2';
                const faceCaptureTargets = {
                    enroll: { minimumQuality: 70 },
                    verify: { minimumQuality: 60 },
                };
                const faceChallenge = [
                    { key: 'center', label: 'Step 1/3: look straight at the camera.', minCenter: 0.38, maxCenter: 0.62 },
                    { key: 'left', label: 'Step 2/3: move your face slightly to the left.', minCenter: 0, maxCenter: 0.50 },
                    { key: 'right', label: 'Step 3/3: move your face slightly to the right.', minCenter: 0.50, maxCenter: 1 },
                ];

                function delay(ms) {
                    return new Promise((resolve) => window.setTimeout(resolve, ms));
                }

                async function faceCrop(sourceCanvas) {
                    const fallbackSize = Math.min(sourceCanvas.width, sourceCanvas.height) * 0.72;
                    let crop = {
                        x: (sourceCanvas.width - fallbackSize) / 2,
                        y: (sourceCanvas.height - fallbackSize) / 2,
                        width: fallbackSize,
                        height: fallbackSize,
                        detected: 'FaceDetector' in window ? false : null,
                    };

                    if ('FaceDetector' in window) {
                        try {
                            const detector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
                            const faces = await detector.detect(sourceCanvas);

                            if (faces.length > 0) {
                                const box = faces[0].boundingBox;
                                const pad = Math.max(box.width, box.height) * 0.18;
                                crop = {
                                    x: Math.max(0, box.x - pad),
                                    y: Math.max(0, box.y - pad),
                                    width: Math.min(sourceCanvas.width, box.width + pad * 2),
                                    height: Math.min(sourceCanvas.height, box.height + pad * 2),
                                    detected: true,
                                };
                            }
                        } catch (error) {
                            // The center crop remains a useful fallback when native face detection is unavailable.
                        }
                    }

                    return crop;
                }

                function faceCenterRatio(sourceCanvas, crop) {
                    return (crop.x + crop.width / 2) / Math.max(1, sourceCanvas.width);
                }

                function faceDetectionStatus(crop) {
                    if (crop.detected === true) {
                        return 'detected';
                    }

                    if (crop.detected === false) {
                        return 'not_detected';
                    }

                    return 'unsupported';
                }

                function descriptorFromCanvas(sourceCanvas, crop) {
                    const size = 16;
                    const canvas = document.createElement('canvas');
                    canvas.width = size;
                    canvas.height = size;
                    const context = canvas.getContext('2d', { willReadFrequently: true });
                    context.drawImage(sourceCanvas, crop.x, crop.y, crop.width, crop.height, 0, 0, size, size);

                    const pixels = context.getImageData(0, 0, size, size).data;
                    const values = [];

                    for (let index = 0; index < pixels.length; index += 4) {
                        values.push((pixels[index] * 0.299 + pixels[index + 1] * 0.587 + pixels[index + 2] * 0.114) / 255);
                    }

                    const mean = values.reduce((sum, value) => sum + value, 0) / values.length;
                    const variance = values.reduce((sum, value) => sum + Math.pow(value - mean, 2), 0) / values.length;
                    const deviation = Math.sqrt(variance) || 1;

                    return values.map((value) => Number(((value - mean) / deviation).toFixed(6)));
                }

                function faceQualityFromCanvas(sourceCanvas, crop) {
                    const size = 48;
                    const canvas = document.createElement('canvas');
                    canvas.width = size;
                    canvas.height = size;
                    const context = canvas.getContext('2d', { willReadFrequently: true });
                    context.drawImage(sourceCanvas, crop.x, crop.y, crop.width, crop.height, 0, 0, size, size);

                    const pixels = context.getImageData(0, 0, size, size).data;
                    const values = [];

                    for (let index = 0; index < pixels.length; index += 4) {
                        values.push(pixels[index] * 0.299 + pixels[index + 1] * 0.587 + pixels[index + 2] * 0.114);
                    }

                    const mean = values.reduce((sum, value) => sum + value, 0) / values.length;
                    const variance = values.reduce((sum, value) => sum + Math.pow(value - mean, 2), 0) / values.length;
                    const deviation = Math.sqrt(variance);
                    let edgeTotal = 0;
                    let edgeCount = 0;

                    for (let y = 0; y < size; y++) {
                        for (let x = 0; x < size; x++) {
                            const current = values[y * size + x];

                            if (x + 1 < size) {
                                edgeTotal += Math.abs(current - values[y * size + x + 1]);
                                edgeCount++;
                            }

                            if (y + 1 < size) {
                                edgeTotal += Math.abs(current - values[(y + 1) * size + x]);
                                edgeCount++;
                            }
                        }
                    }

                    const brightnessScore = Math.max(0, 1 - Math.abs(mean - 135) / 105);
                    const contrastScore = Math.min(1, deviation / 55);
                    const sharpnessScore = Math.min(1, (edgeTotal / Math.max(1, edgeCount)) / 22);
                    const coverageScore = Math.min(1, ((crop.width * crop.height) / (sourceCanvas.width * sourceCanvas.height)) * 2.2);
                    const detectionScore = crop.detected === false ? 0.75 : 1;
                    const score = 100 * detectionScore * (
                        brightnessScore * 0.30
                        + contrastScore * 0.25
                        + sharpnessScore * 0.25
                        + coverageScore * 0.20
                    );

                    return Math.max(0, Math.min(100, Math.round(score)));
                }

                function setFaceQuality(target, score, minimum = 70) {
                    const el = document.querySelector(`[data-face-quality="${target}"]`);

                    if (!el) {
                        return;
                    }

                    const passed = score >= minimum;
                    el.textContent = passed
                        ? `Quality score: ${score}/100. Capture accepted.`
                        : `Quality score: ${score}/100. Retake with better light and a steadier camera.`;
                    el.classList.toggle('text-green-600', passed);
                    el.classList.toggle('text-red-600', !passed);
                    el.classList.toggle('text-gray-500', false);
                }

                async function captureFaceSample(target, video, step) {
                    const frame = document.querySelector(`[data-face-canvas="${target}"]`);
                    frame.width = video.videoWidth;
                    frame.height = video.videoHeight;
                    frame.getContext('2d').drawImage(video, 0, 0, frame.width, frame.height);

                    const crop = await faceCrop(frame);
                    const detectionStatus = faceDetectionStatus(crop);

                    if (detectionStatus === 'not_detected') {
                        throw new Error('No face was detected. Reposition and try again.');
                    }

                    const centerRatio = faceCenterRatio(frame, crop);

                    if (detectionStatus === 'detected' && (centerRatio < step.minCenter || centerRatio > step.maxCenter)) {
                        throw new Error('Face position did not match the live challenge. Try again.');
                    }

                    const descriptor = descriptorFromCanvas(frame, crop);
                    const qualityScore = faceQualityFromCanvas(frame, crop);

                    return {
                        descriptor,
                        qualityScore,
                        detectionStatus,
                        challenge: step.key,
                        centerRatio: Number(centerRatio.toFixed(4)),
                        photoDataUrl: facePhotoDataUrl(frame, crop),
                        capturedAt: new Date().toISOString(),
                    };
                }

                function averagedDescriptor(samples) {
                    const length = samples[0]?.descriptor?.length || 0;
                    const average = [];

                    for (let index = 0; index < length; index++) {
                        const sum = samples.reduce((total, sample) => total + sample.descriptor[index], 0);
                        average.push(Number((sum / samples.length).toFixed(6)));
                    }

                    return average;
                }

                function fillFaceInput(target, suffix, value) {
                    const input = document.getElementById(`${target}_${suffix}`);

                    if (input) {
                        input.value = value;
                    }
                }

                function setFacePreview(target, dataUrl) {
                    const preview = document.querySelector(`[data-face-preview="${target}"]`);

                    if (!preview) {
                        return;
                    }

                    if (dataUrl) {
                        preview.src = dataUrl;
                        preview.classList.remove('hidden');
                        return;
                    }

                    preview.removeAttribute('src');
                    preview.classList.add('hidden');
                }

                function resetFaceInputs(target) {
                    [
                        'face_descriptor',
                        'face_sample',
                        'face_photo',
                        'face_quality_score',
                        'face_protocol_version',
                        'face_liveness_passed',
                        'face_liveness_challenge',
                        'face_sample_count',
                        'face_detection_status',
                        'face_quality_min',
                        'face_quality_average',
                    ].forEach((suffix) => fillFaceInput(target, suffix, ''));
                    setFacePreview(target, null);
                }

                function facePhotoDataUrl(frame, crop) {
                    const output = document.createElement('canvas');
                    output.width = Math.max(1, Math.round(crop.width));
                    output.height = Math.max(1, Math.round(crop.height));
                    output.getContext('2d').drawImage(
                        frame,
                        crop.x,
                        crop.y,
                        crop.width,
                        crop.height,
                        0,
                        0,
                        output.width,
                        output.height
                    );

                    return output.toDataURL('image/jpeg', 0.92);
                }

                function storeFaceCapture(target, samples) {
                    const descriptor = averagedDescriptor(samples);
                    const qualityScores = samples.map((sample) => sample.qualityScore);
                    const qualityMin = Math.min(...qualityScores);
                    const qualityAverage = Math.round(qualityScores.reduce((sum, score) => sum + score, 0) / qualityScores.length);
                    const detectionStatuses = [...new Set(samples.map((sample) => sample.detectionStatus))];
                    const detectionStatus = detectionStatuses.includes('detected')
                        ? 'detected'
                        : detectionStatuses[0] || 'unsupported';
                    const protocolSample = {
                        protocol: faceCaptureProtocolVersion,
                        liveness_passed: true,
                        challenge: samples.map((sample) => ({
                            step: sample.challenge,
                            quality: sample.qualityScore,
                            detection: sample.detectionStatus,
                            center: sample.centerRatio,
                            captured_at: sample.capturedAt,
                        })),
                    };
                    const previewPhoto = samples[samples.length - 1]?.photoDataUrl || '';

                    fillFaceInput(target, 'face_descriptor', JSON.stringify(descriptor));
                    fillFaceInput(target, 'face_sample', JSON.stringify(protocolSample));
                    fillFaceInput(target, 'face_photo', previewPhoto);
                    fillFaceInput(target, 'face_quality_score', qualityAverage);
                    fillFaceInput(target, 'face_protocol_version', faceCaptureProtocolVersion);
                    fillFaceInput(target, 'face_liveness_passed', '1');
                    fillFaceInput(target, 'face_liveness_challenge', samples.map((sample) => sample.challenge).join(','));
                    fillFaceInput(target, 'face_sample_count', samples.length);
                    fillFaceInput(target, 'face_detection_status', detectionStatus);
                    fillFaceInput(target, 'face_quality_min', qualityMin);
                    fillFaceInput(target, 'face_quality_average', qualityAverage);
                    setFacePreview(target, previewPhoto);

                    return { qualityMin, qualityAverage };
                }

                async function captureFace(target) {
                    resetFaceInputs(target);
                    const video = await startCamera(target);

                    if (!video || !video.videoWidth || !video.videoHeight) {
                        setStatus(target, 'Camera is still warming up. Try capture again.');
                        return;
                    }

                    const minimumQuality = faceCaptureTargets[target]?.minimumQuality || 70;
                    const samples = [];

                    for (const step of faceChallenge) {
                        setStatus(target, step.label);
                        await delay(900);

                        const sample = await captureFaceSample(target, video, step);

                        if (sample.qualityScore < minimumQuality) {
                            setFaceQuality(target, sample.qualityScore, minimumQuality);
                            throw new Error('Face quality was below the required level. Retake with better light and a steadier camera.');
                        }

                        samples.push(sample);
                        setFaceQuality(target, sample.qualityScore, minimumQuality);
                    }

                    const result = storeFaceCapture(target, samples);
                    setFaceQuality(target, result.qualityAverage, minimumQuality);
                    setStatus(target, 'Live face capture accepted.', true);
                }

                document.querySelectorAll('[data-face-start]').forEach((button) => {
                    button.addEventListener('click', async () => {
                        try {
                            await startCamera(button.dataset.faceStart);
                        } catch (error) {
                            setStatus(button.dataset.faceStart, 'Camera permission was denied or unavailable.');
                        }
                    });
                });

                document.querySelectorAll('[data-face-capture]').forEach((button) => {
                    button.addEventListener('click', async () => {
                        try {
                            await captureFace(button.dataset.faceCapture);
                        } catch (error) {
                            resetFaceInputs(button.dataset.faceCapture);
                            setStatus(button.dataset.faceCapture, error.message || 'Face capture failed. Check camera permissions.');
                        }
                    });
                });

                document.querySelectorAll('[data-biometric-form]').forEach((form) => {
                    form.addEventListener('submit', async (event) => {
                        if (!geofenceEnabled || form.dataset.geolocationReady === '1') {
                            return;
                        }

                        event.preventDefault();

                        try {
                            await collectGeolocation(form);
                            form.dataset.geolocationReady = '1';
                            form.requestSubmit();
                        } catch (error) {
                            setGeofenceSettingsStatus(error.message || 'Location capture failed.');
                            mobileFingerprintStatus('fingerprint_status', error.message || 'Location capture failed.');
                            mobileFingerprintStatus('verify_fingerprint_status', error.message || 'Location capture failed.');
                        }
                    });
                });

                document.querySelector('[data-add-biometric-network]')?.addEventListener('click', () => {
                    const container = document.querySelector('[data-biometric-network-entries]');

                    if (!container) {
                        return;
                    }

                    const index = container.querySelectorAll('input[name^="biometric_allowed_networks["][name$="[network]"]').length;
                    const row = document.createElement('div');
                    row.className = 'grid grid-cols-12 gap-3';
                    row.innerHTML = `
                        <div class="col-span-12 sm:col-span-3">
                            <label class="sr-only" for="biometric_allowed_network_name_${index}">Name</label>
                            <input id="biometric_allowed_network_name_${index}" name="biometric_allowed_networks[${index}][name]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Branch name">
                        </div>
                        <div class="col-span-12 sm:col-span-3">
                            <label class="sr-only" for="biometric_allowed_network_provider_${index}">Service Provider</label>
                            <input id="biometric_allowed_network_provider_${index}" name="biometric_allowed_networks[${index}][service_provider]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Provider">
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="sr-only" for="biometric_allowed_network_${index}">IP Address / CIDR Range</label>
                            <input id="biometric_allowed_network_${index}" name="biometric_allowed_networks[${index}][network]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="IP address or CIDR range">
                        </div>
                    `;
                    container.appendChild(row);
                    row.querySelector('input')?.focus();
                });

                document.querySelector('[data-add-biometric-geofence-location]')?.addEventListener('click', () => {
                    const container = document.querySelector('[data-biometric-geofence-locations]');

                    if (!container) {
                        return;
                    }

                    const index = container.querySelectorAll('[data-geofence-location-row]').length;
                    const row = document.createElement('div');
                    row.className = 'rounded-lg border border-gray-200 p-4';
                    row.setAttribute('data-geofence-location-row', '');
                    row.innerHTML = `
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 lg:col-span-3">
                                <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_name_${index}">Location Name</label>
                                <input id="biometric_geofence_location_name_${index}" name="biometric_geofence_locations[${index}][name]" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Branch office">
                            </div>
                            <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_latitude_${index}">Latitude</label>
                                <input id="biometric_geofence_location_latitude_${index}" name="biometric_geofence_locations[${index}][latitude]" type="number" step="0.0000001" min="-90" max="90" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                            <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_longitude_${index}">Longitude</label>
                                <input id="biometric_geofence_location_longitude_${index}" name="biometric_geofence_locations[${index}][longitude]" type="number" step="0.0000001" min="-180" max="180" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                            <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_radius_${index}">Radius (m)</label>
                                <input id="biometric_geofence_location_radius_${index}" name="biometric_geofence_locations[${index}][radius_meters]" type="number" min="25" max="50000" value="100" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                            <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700" for="biometric_geofence_location_accuracy_${index}">Max GPS Accuracy (m)</label>
                                <input id="biometric_geofence_location_accuracy_${index}" name="biometric_geofence_locations[${index}][max_accuracy_meters]" type="number" min="5" max="5000" value="150" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                            <div class="col-span-12 lg:col-span-1">
                                <label class="block text-sm font-medium text-transparent" aria-hidden="true">Action</label>
                                <button type="button" data-geofence-current-location class="mt-1 inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Use Current
                                </button>
                            </div>
                        </div>
                    `;
                    container.appendChild(row);
                    row.querySelector('input')?.focus();
                });

                document.addEventListener('click', async (event) => {
                    const button = event.target.closest('[data-geofence-current-location]');

                    if (!button) {
                        return;
                    }

                    const row = button.closest('[data-geofence-location-row]');

                    if (!row) {
                        return;
                    }

                    try {
                        setGeofenceSettingsStatus('Confirming current location...');
                        const position = await browserPosition();
                        const accuracy = Math.round(position.coords.accuracy);

                        row.querySelector('input[name$="[latitude]"]').value = position.coords.latitude.toFixed(7);
                        row.querySelector('input[name$="[longitude]"]').value = position.coords.longitude.toFixed(7);
                        setGeofenceSettingsStatus(`Current location captured with ${accuracy}m accuracy.`, true);
                    } catch (error) {
                        setGeofenceSettingsStatus(error.message || 'Location capture failed.');
                    }
                });

                document.querySelector('[data-mobile-fingerprint-register]')?.addEventListener('click', async (event) => {
                    const form = event.currentTarget.closest('form');

                    if (!window.PublicKeyCredential || !navigator.credentials?.create) {
                        mobileFingerprintStatus('fingerprint_status', 'Fingerprint registration is not available in this browser.');
                        return;
                    }

                    try {
                        mobileFingerprintStatus('fingerprint_status', 'Waiting for the fingerprint prompt...');
                        const publicKey = await mobileFingerprintOptions('enroll', form);
                        const credential = await navigator.credentials.create({ publicKey });
                        document.getElementById('fingerprint_credential').value = JSON.stringify(credentialToJson(credential));
                        mobileFingerprintStatus('fingerprint_status', 'Fingerprint captured. Save the profile to finish.', true);
                    } catch (error) {
                        mobileFingerprintStatus('fingerprint_status', error.message || 'Fingerprint registration failed.');
                    }
                });

                async function runPhoneFingerprintVerification(form, successMessage = 'Fingerprint approved. Run verification to finish.') {
                    if (!window.PublicKeyCredential || !navigator.credentials?.get) {
                        throw new Error('Fingerprint verification is not available in this browser.');
                    }

                    form.querySelector('[name="modality"]').value = 'fingerprint';
                    mobileFingerprintStatus('verify_fingerprint_status', 'Waiting for the fingerprint prompt...');
                    const publicKey = await mobileFingerprintOptions('verify', form);
                    const credential = await navigator.credentials.get({ publicKey });
                    document.getElementById('verify_fingerprint_assertion').value = JSON.stringify(credentialToJson(credential));
                    document.getElementById('verify_external_reference').value = credential.id;
                    mobileFingerprintStatus('verify_fingerprint_status', successMessage, true);
                    form.dataset.geolocationReady = '1';
                }

                document.querySelector('[data-mobile-fingerprint-verify]')?.addEventListener('click', async (event) => {
                    const form = event.currentTarget.closest('form');

                    try {
                        await runPhoneFingerprintVerification(form);
                    } catch (error) {
                        mobileFingerprintStatus('verify_fingerprint_status', error.message || 'Phone fingerprint verification failed.');
                    }
                });

                document.querySelectorAll('[data-punch-submit]').forEach((button) => {
                    button.addEventListener('click', async (event) => {
                        event.preventDefault();

                        const form = event.currentTarget.closest('form');
                        const punchType = event.currentTarget.dataset.punchType || 'in';
                        const punchLabel = event.currentTarget.dataset.punchLabel || 'Attendance';
                        const punchInput = form?.querySelector('#verify_punch_type');

                        if (!form || !punchInput) {
                            return;
                        }

                        punchInput.value = punchType;

                        try {
                            await runPhoneFingerprintVerification(form, `${punchLabel} fingerprint approved. Finishing attendance check...`);
                            form.requestSubmit();
                        } catch (error) {
                            mobileFingerprintStatus('verify_fingerprint_status', error.message || 'Phone fingerprint verification failed.');
                        }
                    });
                });

                if (secureEnrollmentDeadline) {
                    updateEnrollmentWindowStatus();
                    window.setInterval(updateEnrollmentWindowStatus, 1000);
                }
            })();
        </script>
    @endif
</x-hr-layout>
