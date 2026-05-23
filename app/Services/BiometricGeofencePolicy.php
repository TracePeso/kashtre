<?php

namespace App\Services;

use App\Models\HrStaffUnavailability;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BiometricGeofencePolicy
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function __construct(
        private readonly BiometricAttendanceBypassService $attendanceBypass
    ) {
    }

    /**
     * @param array{allow_offsite_bypass?: bool} $options
     */
    public function assertAllowed(Request $request, Organization $organization, array $options = []): void
    {
        if (! $organization->biometric_geofence_enabled) {
            return;
        }

        if (($options['allow_offsite_bypass'] ?? false) && $this->applyOffsiteBypass($request, $organization)) {
            return;
        }

        $check = $this->check($request, $organization);

        if ($check['allowed']) {
            return;
        }

        throw ValidationException::withMessages([
            'geofence' => $check['message'],
        ]);
    }

    /**
     * @return array{enabled: bool, configured: bool, latitude: ?float, longitude: ?float, radius_meters: int, max_accuracy_meters: int, message: string}
     */
    public function status(?Organization $organization): array
    {
        if (! $organization || ! $organization->biometric_geofence_enabled) {
            return [
                'enabled' => false,
                'configured' => false,
                'latitude' => null,
                'longitude' => null,
                'radius_meters' => $this->radiusMeters($organization),
                'max_accuracy_meters' => $this->maxAccuracyMeters($organization),
                'message' => 'Office geofence restriction is off.',
            ];
        }

        $configured = $this->isConfigured($organization);

        return [
            'enabled' => true,
            'configured' => $configured,
            'latitude' => $this->coordinate($organization->biometric_geofence_latitude),
            'longitude' => $this->coordinate($organization->biometric_geofence_longitude),
            'radius_meters' => $this->radiusMeters($organization),
            'max_accuracy_meters' => $this->maxAccuracyMeters($organization),
            'message' => $configured
                ? 'Biometrics require GPS confirmation inside the office geofence.'
                : 'Office geofence is on, but no office location is configured.',
        ];
    }

    /**
     * @return array{allowed: bool, message: string, distance_meters: ?int, accuracy_meters: ?int}
     */
    public function check(Request $request, Organization $organization): array
    {
        if (! $this->isConfigured($organization)) {
            return [
                'allowed' => false,
                'message' => 'Biometrics require the office geofence, but no office location is configured.',
                'distance_meters' => null,
                'accuracy_meters' => null,
            ];
        }

        $latitude = $this->coordinate($request->input('geo_latitude'));
        $longitude = $this->coordinate($request->input('geo_longitude'));
        $accuracy = $this->positiveNumber($request->input('geo_accuracy'));

        if ($latitude === null || $longitude === null || $accuracy === null) {
            return [
                'allowed' => false,
                'message' => 'Biometrics require office location. Allow location access and try again.',
                'distance_meters' => null,
                'accuracy_meters' => null,
            ];
        }

        $maxAccuracy = $this->maxAccuracyMeters($organization);
        $accuracyMeters = (int) round($accuracy);

        if ($accuracy > $maxAccuracy) {
            return [
                'allowed' => false,
                'message' => "Location accuracy {$accuracyMeters}m is outside the allowed {$maxAccuracy}m accuracy.",
                'distance_meters' => null,
                'accuracy_meters' => $accuracyMeters,
            ];
        }

        $distance = $this->distanceMeters(
            $latitude,
            $longitude,
            (float) $organization->biometric_geofence_latitude,
            (float) $organization->biometric_geofence_longitude
        );
        $distanceMeters = (int) round($distance);
        $radius = $this->radiusMeters($organization);

        if ($distance > $radius) {
            return [
                'allowed' => false,
                'message' => "Biometrics require the office geofence. Current location is {$distanceMeters}m from the office, outside the {$radius}m radius.",
                'distance_meters' => $distanceMeters,
                'accuracy_meters' => $accuracyMeters,
            ];
        }

        return [
            'allowed' => true,
            'message' => "Location confirmed within {$distanceMeters}m of the office.",
            'distance_meters' => $distanceMeters,
            'accuracy_meters' => $accuracyMeters,
        ];
    }

    public function distanceMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $fromLatitude = deg2rad($fromLatitude);
        $fromLongitude = deg2rad($fromLongitude);
        $toLatitude = deg2rad($toLatitude);
        $toLongitude = deg2rad($toLongitude);

        $latitudeDelta = $toLatitude - $fromLatitude;
        $longitudeDelta = $toLongitude - $fromLongitude;

        $a = sin($latitudeDelta / 2) ** 2
            + cos($fromLatitude) * cos($toLatitude) * sin($longitudeDelta / 2) ** 2;
        $a = max(0.0, min(1.0, $a));

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function isConfigured(Organization $organization): bool
    {
        return $this->coordinate($organization->biometric_geofence_latitude) !== null
            && $this->coordinate($organization->biometric_geofence_longitude) !== null
            && $this->radiusMeters($organization) > 0
            && $this->maxAccuracyMeters($organization) > 0;
    }

    private function coordinate(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function positiveNumber(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return $value > 0 ? $value : null;
    }

    private function radiusMeters(?Organization $organization): int
    {
        return max(1, (int) ($organization?->biometric_geofence_radius_meters ?: 100));
    }

    private function maxAccuracyMeters(?Organization $organization): int
    {
        return max(1, (int) ($organization?->biometric_geofence_max_accuracy_meters ?: 150));
    }

    private function applyOffsiteBypass(Request $request, Organization $organization): bool
    {
        $unavailability = $this->attendanceBypass->approvedOffsiteDutyForRequest($request, $organization);

        if (! $unavailability instanceof HrStaffUnavailability || ! $unavailability->allowsAttendanceBypass()) {
            return false;
        }

        $request->attributes->set('biometric_access_bypass', [
            'type' => 'offsite_duty',
            'source' => 'geofence',
            'title' => $unavailability->title ?: 'Official Workshop/Meeting',
            'staff_assignment_id' => $unavailability->staff_assignment_id,
            'approval_request_id' => $unavailability->approval_request_id,
            'message' => 'Approved Official Workshop/Meeting bypassed the office geofence restriction.',
        ]);

        return true;
    }
}
