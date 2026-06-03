<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Models\HrBiometricDevice;
use App\Models\HrBiometricProfile;
use App\Models\HrBiometricVerification;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Services\BiometricGeofencePolicy;
use App\Services\BiometricNetworkPolicy;
use App\Services\BiometricVerificationService;
use App\Services\LegacyBiometricDeviceSyncService;
use App\Services\MobileFingerprintCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BiometricController extends Controller
{
    public function index(Request $request, BiometricNetworkPolicy $networkPolicy, BiometricGeofencePolicy $geofencePolicy)
    {
        return $this->page($request, $networkPolicy, $geofencePolicy, 'enrollment');
    }

    public function enrollment(Request $request, BiometricNetworkPolicy $networkPolicy, BiometricGeofencePolicy $geofencePolicy)
    {
        return $this->page($request, $networkPolicy, $geofencePolicy, 'enrollment');
    }

    public function attendance(Request $request, BiometricNetworkPolicy $networkPolicy, BiometricGeofencePolicy $geofencePolicy)
    {
        return $this->page($request, $networkPolicy, $geofencePolicy, 'attendance');
    }

    public function clocking(Request $request, BiometricNetworkPolicy $networkPolicy, BiometricGeofencePolicy $geofencePolicy)
    {
        return $this->page($request, $networkPolicy, $geofencePolicy, 'clocking');
    }

    public function settings(Request $request, BiometricNetworkPolicy $networkPolicy, BiometricGeofencePolicy $geofencePolicy)
    {
        return $this->page($request, $networkPolicy, $geofencePolicy, 'settings');
    }

    private function page(Request $request, BiometricNetworkPolicy $networkPolicy, BiometricGeofencePolicy $geofencePolicy, string $activePage)
    {
        $organization = Organization::current($request->user());
        $staffAssignments = collect();
        $profiles = collect();
        $verifications = collect();
        $legacyDevices = collect();
        $networkEntries = [];
        $flaggedLateStaff = collect();

        if ($organization) {
            $lateFlagTriggerCount = $this->lateClockInRepeatCount($organization);

            $staffAssignments = StaffAssignment::query()
                ->where('organization_id', $organization->id)
                ->whereNotIn('status', ['inactive', 'orphaned'])
                ->orderBy('staff_name')
                ->get();

            $profiles = HrBiometricProfile::query()
                ->with(['staffAssignment', 'enrolledBy'])
                ->where('organization_id', $organization->id)
                ->latest()
                ->limit(100)
                ->get();

            $verifications = HrBiometricVerification::query()
                ->with(['profile', 'staffAssignment', 'verifiedBy', 'attendanceLedger'])
                ->where('organization_id', $organization->id)
                ->latest('verified_at')
                ->limit(15)
                ->get();

            $legacyDevices = HrBiometricDevice::query()
                ->where('organization_id', $organization->id)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get();

            $networkEntries = $networkPolicy->normalizeNetworkEntries($organization->biometric_allowed_networks ?? []);
            $flaggedLateStaff = DB::table('hr_attendance_ledger')
                ->join('hr_staff_assignments', 'hr_staff_assignments.id', '=', 'hr_attendance_ledger.staff_assignment_id')
                ->where('hr_attendance_ledger.organization_id', $organization->id)
                ->where('hr_attendance_ledger.punch_type', 'in')
                ->where('hr_attendance_ledger.is_late_clock_in', true)
                ->where('hr_attendance_ledger.status', '!=', 'ignored')
                ->groupBy('hr_attendance_ledger.staff_assignment_id', 'hr_staff_assignments.staff_name')
                ->havingRaw('COUNT(*) >= ?', [$lateFlagTriggerCount])
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->orderBy('hr_staff_assignments.staff_name')
                ->get([
                    'hr_attendance_ledger.staff_assignment_id',
                    'hr_staff_assignments.staff_name',
                    DB::raw('COUNT(*) as late_count'),
                    DB::raw('MAX(hr_attendance_ledger.minutes_late) as worst_minutes_late'),
                    DB::raw('MAX(hr_attendance_ledger.occurred_at) as last_late_at'),
                ])
                ->map(function (object $row): array {
                    return [
                        'staff_assignment_id' => (int) $row->staff_assignment_id,
                        'staff_name' => (string) $row->staff_name,
                        'late_count' => (int) $row->late_count,
                        'worst_minutes_late' => $row->worst_minutes_late !== null ? (int) $row->worst_minutes_late : null,
                        'last_late_at' => $row->last_late_at ? Carbon::parse($row->last_late_at) : null,
                    ];
                });
        }

        return view('hr.biometrics.index', [
            'activeBiometricPage' => $activePage,
            'organization' => $organization,
            'staffAssignments' => $staffAssignments,
            'profiles' => $profiles,
            'verifications' => $verifications,
            'legacyDevices' => $legacyDevices,
            'canManageBiometrics' => $request->user()?->canManageHrBiometrics() ?? false,
            'networkAccess' => $networkPolicy->status($request, $organization),
            'networkEntries' => $networkEntries,
            'geofenceAccess' => $geofencePolicy->status($organization),
            'flaggedLateStaff' => $flaggedLateStaff,
            'lateFlagTriggerCount' => $organization ? $this->lateClockInRepeatCount($organization) : 3,
        ]);
    }

    public function store(
        Request $request,
        BiometricVerificationService $biometrics,
        BiometricNetworkPolicy $networkPolicy,
        BiometricGeofencePolicy $geofencePolicy
    ): RedirectResponse
    {
        abort_unless($request->user()?->canManageHrBiometrics(), 403);

        $organization = $this->currentOrganizationOrFail($request);
        $networkPolicy->assertAllowed($request, $organization, ['allow_offsite_bypass' => true]);

        $data = $request->validate($this->enrollmentRules());
        $geofencePolicy->assertAllowed($request, $organization, ['allow_offsite_bypass' => true]);
        $staffAssignment = $this->staffAssignmentForOrganization($organization, (int) $data['staff_assignment_id']);

        if (($data['modality'] ?? null) === HrBiometricProfile::MODALITY_FINGERPRINT && ! empty($data['fingerprint_credential'])) {
            $mobileCredential = app(MobileFingerprintCredentialService::class)->enroll($request, $staffAssignment, $data);
            $data['provider'] = 'mobile-webauthn';
            $data['external_reference'] = $mobileCredential['credential_id'];
            $data['mobile_credential'] = $mobileCredential;
        }

        $profile = $biometrics->enroll(
            $staffAssignment,
            $data['modality'],
            $this->payloadFromRequest($request, $data),
            $request->user()
        );

        return back()->with('status', "{$profile->staff_name}'s {$profile->modality} profile was enrolled.");
    }

    public function verify(
        Request $request,
        BiometricVerificationService $biometrics,
        BiometricNetworkPolicy $networkPolicy,
        BiometricGeofencePolicy $geofencePolicy
    ): RedirectResponse
    {
        $organization = $this->currentOrganizationOrFail($request);
        $networkPolicy->assertAllowed($request, $organization, ['allow_offsite_bypass' => true]);

        $data = $request->validate($this->verificationRules());
        $geofencePolicy->assertAllowed($request, $organization, ['allow_offsite_bypass' => true]);

        $staffAssignment = null;

        if (! empty($data['staff_assignment_id'])) {
            $staffAssignment = $this->staffAssignmentForOrganization($organization, (int) $data['staff_assignment_id']);
        }

        $profile = null;

        if (($data['modality'] ?? null) === HrBiometricProfile::MODALITY_FINGERPRINT && ! empty($data['fingerprint_assertion'])) {
            $profile = app(MobileFingerprintCredentialService::class)->verify($request, $organization, $data);
            $data['profile_uuid'] = $profile->uuid;
            $data['external_reference'] = $profile->external_reference;
            $data['match_score'] = 1;
            $data['provider'] = 'mobile-webauthn';
        }

        if (! empty($data['profile_uuid'])) {
            $profile = HrBiometricProfile::query()
                ->where('organization_id', $organization->id)
                ->where('uuid', $data['profile_uuid'])
                ->where('modality', $data['modality'])
                ->firstOrFail();
        }

        $verification = $biometrics->verify(
            $data['modality'],
            $this->payloadFromRequest($request, $data),
            $staffAssignment,
            $profile,
            $request->user()
        );

        $staffName = $verification->staffAssignment?->staff_name ?? 'No staff match';
        $score = $verification->score !== null ? number_format($verification->score * 100, 1) . '%' : 'n/a';
        $message = $verification->passed()
            ? "Verified {$staffName} with a {$score} score."
            : "Verification failed for {$staffName}. {$verification->failure_reason}";

        return back()->with('biometric_verification', [
            'passed' => $verification->passed(),
            'message' => $message,
            'score' => $score,
            'staff_name' => $staffName,
        ]);
    }

    public function mobileFingerprintOptions(
        Request $request,
        MobileFingerprintCredentialService $mobileFingerprints,
        BiometricNetworkPolicy $networkPolicy,
        BiometricGeofencePolicy $geofencePolicy
    ): JsonResponse
    {
        $organization = $this->currentOrganizationOrFail($request);
        $data = $request->validate(array_merge([
            'action' => ['required', Rule::in(['enroll', 'verify'])],
            'staff_assignment_id' => ['nullable', 'integer', 'exists:hr_staff_assignments,id'],
            'profile_uuid' => ['nullable', 'string', 'max:80'],
        ], $this->geolocationRules()));

        if (($data['action'] ?? null) === 'enroll') {
            abort_unless($request->user()?->canManageHrBiometrics(), 403);
        }

        $allowOffsiteBypass = ($data['action'] ?? null) === 'verify';

        $networkPolicy->assertAllowed($request, $organization, ['allow_offsite_bypass' => $allowOffsiteBypass]);
        $geofencePolicy->assertAllowed($request, $organization, ['allow_offsite_bypass' => $allowOffsiteBypass]);

        if (($data['action'] ?? null) === 'enroll') {
            $data['staff_assignment_id'] = $this->staffAssignmentForOrganization($organization, (int) ($data['staff_assignment_id'] ?? 0))->id;
        }

        if (! empty($data['staff_assignment_id'])) {
            $this->staffAssignmentForOrganization($organization, (int) $data['staff_assignment_id']);
        }

        if (! empty($data['profile_uuid'])) {
            HrBiometricProfile::query()
                ->where('organization_id', $organization->id)
                ->where('uuid', $data['profile_uuid'])
                ->where('modality', HrBiometricProfile::MODALITY_FINGERPRINT)
                ->firstOrFail();
        }

        return response()->json($mobileFingerprints->options($request, $organization, $data));
    }

    public function importLegacyDeviceLog(Request $request, LegacyBiometricDeviceSyncService $deviceSync): RedirectResponse
    {
        abort_unless($request->user()?->canManageHrBiometrics(), 403);

        $organization = $this->currentOrganizationOrFail($request);
        $data = $request->validate([
            'legacy_biometric_device_id' => ['required', 'integer', 'exists:hr_biometric_devices,id'],
            'legacy_device_file' => ['nullable', 'file', 'max:2048'],
            'legacy_device_payload' => ['nullable', 'string', 'max:200000'],
        ]);

        $device = HrBiometricDevice::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->findOrFail((int) $data['legacy_biometric_device_id']);

        $rows = [];

        if ($request->hasFile('legacy_device_file')) {
            $file = $request->file('legacy_device_file');
            $rows = array_merge(
                $rows,
                $deviceSync->rowsFromText(
                    file_get_contents($file->getRealPath()) ?: '',
                    $file->getClientOriginalName()
                )
            );
        }

        if (filled($data['legacy_device_payload'] ?? null)) {
            $rows = array_merge($rows, $deviceSync->rowsFromText($data['legacy_device_payload']));
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'legacy_device_payload' => 'Upload a CSV/JSON export or paste device events before syncing.',
            ]);
        }

        $stats = $deviceSync->import($organization, $rows, [
            'actor' => $request->user(),
            'provider' => $device->provider,
            'device_id' => $device->device_id,
        ]);

        $device->forceFill(['last_synced_at' => now()])->save();

        return back()->with('status', sprintf(
            'Imported %d offline clocking events from %s. %d duplicate, %d unmatched, %d failed.',
            $stats['imported'],
            $device->name,
            $stats['duplicates'],
            $stats['unmatched'],
            $stats['failed']
        ));
    }

    public function storeLegacyDevice(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageHrBiometrics(), 403);

        $organization = $this->currentOrganizationOrFail($request);
        $data = $request->validate([
            'legacy_device_name' => ['required', 'string', 'max:120'],
            'legacy_device_provider' => ['required', 'string', 'max:80'],
            'legacy_device_identifier' => ['required', 'string', 'max:120'],
            'legacy_device_location' => ['nullable', 'string', 'max:160'],
            'legacy_device_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $normalizedProvider = strtolower(trim($data['legacy_device_provider']));
        $normalizedDeviceId = trim($data['legacy_device_identifier']);

        $exists = HrBiometricDevice::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $normalizedProvider)
            ->where('device_id', $normalizedDeviceId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'legacy_device_identifier' => 'This biometric machine is already registered for the current organization.',
            ]);
        }

        HrBiometricDevice::create([
            'organization_id' => $organization->id,
            'name' => trim($data['legacy_device_name']),
            'provider' => $normalizedProvider,
            'device_id' => $normalizedDeviceId,
            'location' => filled($data['legacy_device_location'] ?? null) ? trim($data['legacy_device_location']) : null,
            'notes' => filled($data['legacy_device_notes'] ?? null) ? trim($data['legacy_device_notes']) : null,
            'is_active' => true,
            'registered_by_user_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Biometric machine registered.');
    }

    public function updateNetworkPolicy(Request $request, BiometricNetworkPolicy $networkPolicy): RedirectResponse
    {
        abort_unless($request->user()?->canManageHrBiometrics(), 403);

        $organization = $this->currentOrganizationOrFail($request);
        $data = $request->validate([
            'biometric_network_restriction_enabled' => ['nullable', 'boolean'],
            'biometric_allowed_networks' => ['nullable'],
            'biometric_allowed_networks.*.name' => ['nullable', 'string', 'max:120'],
            'biometric_allowed_networks.*.service_provider' => ['nullable', 'string', 'max:120'],
            'biometric_allowed_networks.*.network' => ['nullable', 'string', 'max:100'],
        ]);

        $enabled = $request->boolean('biometric_network_restriction_enabled');
        $networkEntries = $networkPolicy->normalizeNetworkEntries($data['biometric_allowed_networks'] ?? []);
        $networks = $networkPolicy->networksFromEntries($networkEntries);
        $invalidNetworks = $networkPolicy->invalidNetworks($networks);

        if ($invalidNetworks !== []) {
            throw ValidationException::withMessages([
                'biometric_allowed_networks' => 'Enter valid IP addresses or CIDR ranges. Invalid: ' . implode(', ', $invalidNetworks),
            ]);
        }

        if ($enabled && $networks === []) {
            throw ValidationException::withMessages([
                'biometric_allowed_networks' => 'Add at least one office IP address or CIDR range before requiring the office network.',
            ]);
        }

        $organization->forceFill([
            'biometric_network_restriction_enabled' => $enabled,
            'biometric_allowed_networks' => $networkEntries,
        ])->save();

        return back()->with('status', 'Biometric office network policy updated.');
    }

    public function updateGeofencePolicy(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageHrBiometrics(), 403);

        $organization = $this->currentOrganizationOrFail($request);
        $data = $request->validate([
            'biometric_geofence_enabled' => ['nullable', 'boolean'],
            'biometric_geofence_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'biometric_geofence_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'biometric_geofence_radius_meters' => ['nullable', 'integer', 'min:25', 'max:50000'],
            'biometric_geofence_max_accuracy_meters' => ['nullable', 'integer', 'min:5', 'max:5000'],
        ]);

        $enabled = $request->boolean('biometric_geofence_enabled');
        $latitude = $data['biometric_geofence_latitude'] ?? null;
        $longitude = $data['biometric_geofence_longitude'] ?? null;
        $radius = $data['biometric_geofence_radius_meters'] ?? null;
        $maxAccuracy = $data['biometric_geofence_max_accuracy_meters'] ?? null;

        if ($enabled && ($latitude === null || $latitude === '' || $longitude === null || $longitude === '')) {
            throw ValidationException::withMessages([
                'biometric_geofence_latitude' => 'Add the office latitude and longitude before requiring the office geofence.',
            ]);
        }

        if ($enabled && ($radius === null || $radius === '')) {
            throw ValidationException::withMessages([
                'biometric_geofence_radius_meters' => 'Add the office geofence radius before requiring the office geofence.',
            ]);
        }

        if ($enabled && ($maxAccuracy === null || $maxAccuracy === '')) {
            throw ValidationException::withMessages([
                'biometric_geofence_max_accuracy_meters' => 'Add the maximum accepted GPS accuracy before requiring the office geofence.',
            ]);
        }

        $organization->forceFill([
            'biometric_geofence_enabled' => $enabled,
            'biometric_geofence_latitude' => $latitude !== null && $latitude !== '' ? (float) $latitude : null,
            'biometric_geofence_longitude' => $longitude !== null && $longitude !== '' ? (float) $longitude : null,
            'biometric_geofence_radius_meters' => $radius !== null && $radius !== '' ? (int) $radius : 100,
            'biometric_geofence_max_accuracy_meters' => $maxAccuracy !== null && $maxAccuracy !== '' ? (int) $maxAccuracy : 150,
        ])->save();

        return back()->with('status', 'Biometric office geofence policy updated.');
    }

    public function updateClockSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageHrBiometrics(), 403);

        $organization = $this->currentOrganizationOrFail($request);
        $data = $request->validate([
            'biometric_late_clock_in_enabled' => ['nullable', 'boolean'],
            'biometric_late_clock_in_threshold_minutes' => ['nullable', 'integer', 'min:1', 'max:720'],
            'biometric_late_clock_in_repeat_count' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $enabled = $request->boolean('biometric_late_clock_in_enabled');
        $threshold = $data['biometric_late_clock_in_threshold_minutes'] ?? null;
        $repeatCount = $data['biometric_late_clock_in_repeat_count'] ?? null;

        if ($enabled && ($threshold === null || $threshold === '')) {
            throw ValidationException::withMessages([
                'biometric_late_clock_in_threshold_minutes' => 'Add the allowed late clock-in minutes before enabling repeated lateness flags.',
            ]);
        }

        if ($enabled && ($repeatCount === null || $repeatCount === '')) {
            throw ValidationException::withMessages([
                'biometric_late_clock_in_repeat_count' => 'Add how many late clock-ins should trigger the repeated lateness flag.',
            ]);
        }

        $organization->forceFill([
            'biometric_late_clock_in_enabled' => $enabled,
            'biometric_late_clock_in_threshold_minutes' => $threshold !== null && $threshold !== '' ? (int) $threshold : null,
            'biometric_late_clock_in_repeat_count' => $repeatCount !== null && $repeatCount !== '' ? (int) $repeatCount : $this->lateClockInRepeatCount($organization),
        ])->save();

        return back()->with('status', 'Biometric clock-in settings updated.');
    }

    public function destroy(Request $request, HrBiometricProfile $biometricProfile): RedirectResponse
    {
        abort_unless($request->user()?->canManageHrBiometrics(), 403);

        $organization = $this->currentOrganizationOrFail($request);
        abort_unless($biometricProfile->organization_id === $organization->id, 404);

        $biometricProfile->forceFill(['status' => 'inactive'])->save();

        return back()->with('status', "{$biometricProfile->staff_name}'s {$biometricProfile->modality} profile was deactivated.");
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentRules(): array
    {
        return array_merge([
            'staff_assignment_id' => ['required', 'integer', 'exists:hr_staff_assignments,id'],
            'modality' => ['required', Rule::in([HrBiometricProfile::MODALITY_FINGERPRINT, HrBiometricProfile::MODALITY_FACE])],
            'label' => ['nullable', 'string', 'max:120'],
            'provider' => ['nullable', 'string', 'max:80'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'fingerprint_template' => ['nullable', 'string', 'max:65535'],
            'fingerprint_credential' => ['nullable', 'string'],
            'face_descriptor' => ['nullable'],
            'face_sample' => ['nullable', 'string'],
            'quality_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'face_protocol_version' => ['nullable', 'string', 'max:40'],
            'face_liveness_passed' => ['nullable', 'boolean'],
            'face_liveness_challenge' => ['nullable', 'string', 'max:500'],
            'face_sample_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'face_detection_status' => ['nullable', 'string', 'max:40'],
            'face_quality_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'face_quality_average' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'verification_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'capture_source' => ['nullable', 'string', 'max:80'],
        ], $this->geolocationRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function verificationRules(): array
    {
        return array_merge([
            'staff_assignment_id' => ['nullable', 'integer', 'exists:hr_staff_assignments,id'],
            'profile_uuid' => ['nullable', 'string', 'max:80'],
            'modality' => ['required', Rule::in([HrBiometricProfile::MODALITY_FINGERPRINT, HrBiometricProfile::MODALITY_FACE])],
            'provider' => ['nullable', 'string', 'max:80'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'fingerprint_template' => ['nullable', 'string', 'max:65535'],
            'fingerprint_assertion' => ['nullable', 'string'],
            'face_descriptor' => ['nullable'],
            'face_sample' => ['nullable', 'string'],
            'quality_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'face_protocol_version' => ['nullable', 'string', 'max:40'],
            'face_liveness_passed' => ['nullable', 'boolean'],
            'face_liveness_challenge' => ['nullable', 'string', 'max:500'],
            'face_sample_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'face_detection_status' => ['nullable', 'string', 'max:40'],
            'face_quality_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'face_quality_average' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'match_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'punch_type' => ['nullable', Rule::in(['in', 'out'])],
            'capture_source' => ['nullable', 'string', 'max:80'],
        ], $this->geolocationRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function geolocationRules(): array
    {
        return [
            'geo_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'geo_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geo_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    private function currentOrganizationOrFail(Request $request): Organization
    {
        $organization = Organization::current($request->user());

        abort_unless($organization, 404);

        return $organization;
    }

    private function staffAssignmentForOrganization(Organization $organization, int $staffAssignmentId): StaffAssignment
    {
        return StaffAssignment::query()
            ->where('organization_id', $organization->id)
            ->whereKey($staffAssignmentId)
            ->firstOrFail();
    }

    private function payloadFromRequest(Request $request, array $data): array
    {
        return array_merge($data, [
            'provider' => $data['provider'] ?? null,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'geo_latitude' => $data['geo_latitude'] ?? null,
                'geo_longitude' => $data['geo_longitude'] ?? null,
                'geo_accuracy' => $data['geo_accuracy'] ?? null,
                'attendance_override' => $request->attributes->get('biometric_access_bypass'),
            ],
        ]);
    }

    private function lateClockInRepeatCount(?Organization $organization): int
    {
        $repeatCount = (int) ($organization?->biometric_late_clock_in_repeat_count ?? 0);

        return $repeatCount >= 1 ? $repeatCount : 3;
    }
}
