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
     * @return array{enabled: bool, configured: bool, latitude: ?float, longitude: ?float, radius_meters: int, max_accuracy_meters: int, location_count: int, locations: array<int, array{name: string, latitude: float, longitude: float, radius_meters: int, max_accuracy_meters: int}>, message: string}
     */
    public function status(?Organization $organization): array
    {
        $locations = $this->configuredLocations($organization);
        $primaryLocation = $locations[0] ?? null;

        if (! $organization || ! $organization->biometric_geofence_enabled) {
            return [
                'enabled' => false,
                'configured' => false,
                'latitude' => $primaryLocation['latitude'] ?? null,
                'longitude' => $primaryLocation['longitude'] ?? null,
                'radius_meters' => $primaryLocation['radius_meters'] ?? $this->radiusMeters($organization),
                'max_accuracy_meters' => $primaryLocation['max_accuracy_meters'] ?? $this->maxAccuracyMeters($organization),
                'location_count' => count($locations),
                'locations' => $locations,
                'message' => 'Office geofence restriction is off.',
            ];
        }

        $configured = $locations !== [];
        $locationCount = count($locations);

        return [
            'enabled' => true,
            'configured' => $configured,
            'latitude' => $primaryLocation['latitude'] ?? null,
            'longitude' => $primaryLocation['longitude'] ?? null,
            'radius_meters' => $primaryLocation['radius_meters'] ?? $this->radiusMeters($organization),
            'max_accuracy_meters' => $primaryLocation['max_accuracy_meters'] ?? $this->maxAccuracyMeters($organization),
            'location_count' => $locationCount,
            'locations' => $locations,
            'message' => $configured
                ? ($locationCount === 1
                    ? 'Biometrics require GPS confirmation inside the configured geofence location.'
                    : "Biometrics require GPS confirmation inside one of the {$locationCount} configured geofence locations.")
                : 'Office geofence is on, but no office location is configured.',
        ];
    }

    /**
     * @return array{allowed: bool, message: string, distance_meters: ?int, accuracy_meters: ?int}
     */
    public function check(Request $request, Organization $organization): array
    {
        $locations = $this->configuredLocations($organization);

        if ($locations === []) {
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

        $supportedLocations = array_values(array_filter(
            $locations,
            fn (array $location): bool => $accuracy <= (float) $location['max_accuracy_meters']
        ));

        if ($supportedLocations === []) {
            $maxAccuracy = max(array_map(
                fn (array $location): int => (int) $location['max_accuracy_meters'],
                $locations
            ));

            return [
                'allowed' => false,
                'message' => "Location accuracy {$accuracyMeters}m is outside the allowed {$maxAccuracy}m accuracy.",
                'distance_meters' => null,
                'accuracy_meters' => $accuracyMeters,
            ];
        }

        $nearest = null;
        $matched = null;

        foreach ($supportedLocations as $index => $location) {
            $distance = $this->distanceMeters(
                $latitude,
                $longitude,
                (float) $location['latitude'],
                (float) $location['longitude']
            );
            $distanceMeters = (int) round($distance);
            $candidate = [
                'index' => $index,
                'location' => $location,
                'distance' => $distance,
                'distance_meters' => $distanceMeters,
            ];

            if ($nearest === null || $distance < $nearest['distance']) {
                $nearest = $candidate;
            }

            if ($distance <= (int) $location['radius_meters']) {
                if ($matched === null || $distance < $matched['distance']) {
                    $matched = $candidate;
                }
            }
        }

        if ($matched !== null) {
            $locationName = $this->locationName($matched['location'], $matched['index']);

            return [
                'allowed' => true,
                'message' => "Location confirmed within {$matched['distance_meters']}m of {$locationName}.",
                'distance_meters' => $matched['distance_meters'],
                'accuracy_meters' => $accuracyMeters,
            ];
        }

        $nearest ??= [
            'index' => 0,
            'location' => $locations[0],
            'distance_meters' => null,
        ];
        $nearestName = $this->locationName($nearest['location'], $nearest['index']);
        $nearestRadius = (int) $nearest['location']['radius_meters'];

        return [
            'allowed' => false,
            'message' => "Biometrics require the office geofence. Current location is {$nearest['distance_meters']}m from {$nearestName}, outside the {$nearestRadius}m radius.",
            'distance_meters' => $nearest['distance_meters'],
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
        return $this->configuredLocations($organization) !== [];
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

    /**
     * @return array<int, array{name: string, latitude: float, longitude: float, radius_meters: int, max_accuracy_meters: int}>
     */
    private function configuredLocations(?Organization $organization): array
    {
        if (! $organization) {
            return [];
        }

        $locations = [];

        foreach ((array) ($organization->biometric_geofence_locations ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $latitude = $this->coordinate($entry['latitude'] ?? null);
            $longitude = $this->coordinate($entry['longitude'] ?? null);
            $radius = (int) ($entry['radius_meters'] ?? 0);
            $maxAccuracy = (int) ($entry['max_accuracy_meters'] ?? 0);

            if ($latitude === null || $longitude === null || $radius < 1 || $maxAccuracy < 1) {
                continue;
            }

            $locations[] = [
                'name' => trim((string) ($entry['name'] ?? '')),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius_meters' => $radius,
                'max_accuracy_meters' => $maxAccuracy,
            ];
        }

        if ($locations !== []) {
            return array_values($locations);
        }

        $latitude = $this->coordinate($organization->biometric_geofence_latitude);
        $longitude = $this->coordinate($organization->biometric_geofence_longitude);

        if ($latitude === null || $longitude === null || $this->radiusMeters($organization) < 1 || $this->maxAccuracyMeters($organization) < 1) {
            return [];
        }

        return [[
            'name' => 'Primary office',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $this->radiusMeters($organization),
            'max_accuracy_meters' => $this->maxAccuracyMeters($organization),
        ]];
    }

    private function locationName(array $location, int $index): string
    {
        $name = trim((string) ($location['name'] ?? ''));

        return $name !== '' ? $name : 'Location ' . ($index + 1);
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
