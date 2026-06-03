<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Business;
use App\Models\HrBiometricDevice;
use App\Models\HrStaffUnavailability;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HrBiometricAttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_biometric_template_shows_clocking_actions_without_login_and_logout_wording(): void
    {
        $view = file_get_contents(resource_path('views/hr/biometrics/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("Clocking", $view);
        $this->assertStringContainsString("Clock In", $view);
        $this->assertStringContainsString("Clock Out", $view);
        $this->assertStringNotContainsString("Attendance Clocking", $view);
        $this->assertStringNotContainsString("Attendance Login / Logout", $view);
        $this->assertStringNotContainsString("Login / Clock In", $view);
        $this->assertStringNotContainsString("Logout / Clock Out", $view);
        $this->assertLessThan(
            strpos($view, 'Enrolled Profiles'),
            strpos($view, 'Clocking')
        );
    }

    public function test_clock_in_verification_requires_office_network_when_network_restriction_is_enabled(): void
    {
        [$user] = $this->createHrBiometricContext(
            ['View HR Staff'],
            [
                'biometric_network_restriction_enabled' => true,
                'biometric_allowed_networks' => [
                    ['network' => '10.10.10.0/24', 'name' => 'HQ LAN', 'service_provider' => 'ISP'],
                ],
            ]
        );

        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.attendance'))
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.24'])
            ->post(route('hr.biometrics.verify'), [
                'modality' => 'fingerprint',
                'punch_type' => 'in',
            ]);

        $response->assertRedirect(route('hr.biometrics.attendance'));
        $response->assertSessionHasErrors('office_network');
    }

    public function test_clock_in_verification_requires_geofence_when_geofence_restriction_is_enabled(): void
    {
        [$user] = $this->createHrBiometricContext(
            ['View HR Staff'],
            [
                'biometric_geofence_enabled' => true,
                'biometric_geofence_latitude' => 0.3475964,
                'biometric_geofence_longitude' => 32.5825197,
                'biometric_geofence_radius_meters' => 150,
                'biometric_geofence_max_accuracy_meters' => 100,
            ]
        );

        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.attendance'))
            ->post(route('hr.biometrics.verify'), [
                'modality' => 'fingerprint',
                'punch_type' => 'in',
            ]);

        $response->assertRedirect(route('hr.biometrics.attendance'));
        $response->assertSessionHasErrors('geofence');
    }

    public function test_mobile_fingerprint_attendance_prompt_requires_office_network(): void
    {
        [$user] = $this->createHrBiometricContext(
            ['View HR Staff'],
            [
                'biometric_network_restriction_enabled' => true,
                'biometric_allowed_networks' => [
                    ['network' => '10.10.10.0/24', 'name' => 'HQ LAN', 'service_provider' => 'ISP'],
                ],
            ]
        );

        $this->actingAs($user);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.24'])
            ->postJson(route('hr.biometrics.mobile-fingerprint.options'), [
                'action' => 'verify',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('office_network');
    }

    public function test_approved_offsite_duty_bypasses_attendance_network_and_geofence_restrictions(): void
    {
        [$user, $organization] = $this->createHrBiometricContext(
            ['View HR Staff'],
            [
                'biometric_network_restriction_enabled' => true,
                'biometric_allowed_networks' => [
                    ['network' => '10.10.10.0/24', 'name' => 'HQ LAN', 'service_provider' => 'ISP'],
                ],
                'biometric_geofence_enabled' => true,
                'biometric_geofence_latitude' => 0.3475964,
                'biometric_geofence_longitude' => 32.5825197,
                'biometric_geofence_radius_meters' => 150,
                'biometric_geofence_max_accuracy_meters' => 100,
            ]
        );

        $assignment = $this->createActiveStaffAssignment($organization, $user);
        $this->createApprovedOffsiteDuty($organization, $assignment);

        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.attendance'))
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.24'])
            ->post(route('hr.biometrics.verify'), [
                'modality' => 'fingerprint',
                'staff_assignment_id' => $assignment->id,
                'punch_type' => 'in',
            ]);

        $response->assertRedirect(route('hr.biometrics.attendance'));
        $response->assertSessionDoesntHaveErrors(['office_network', 'geofence']);
        $response->assertSessionHas('biometric_verification');
    }

    public function test_approved_offsite_duty_allows_mobile_fingerprint_attendance_prompt(): void
    {
        [$user, $organization] = $this->createHrBiometricContext(
            ['View HR Staff'],
            [
                'biometric_network_restriction_enabled' => true,
                'biometric_allowed_networks' => [
                    ['network' => '10.10.10.0/24', 'name' => 'HQ LAN', 'service_provider' => 'ISP'],
                ],
                'biometric_geofence_enabled' => true,
                'biometric_geofence_latitude' => 0.3475964,
                'biometric_geofence_longitude' => 32.5825197,
                'biometric_geofence_radius_meters' => 150,
                'biometric_geofence_max_accuracy_meters' => 100,
            ]
        );

        $assignment = $this->createActiveStaffAssignment($organization, $user);
        $this->createApprovedOffsiteDuty($organization, $assignment);

        $this->actingAs($user);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.24'])
            ->postJson(route('hr.biometrics.mobile-fingerprint.options'), [
                'action' => 'verify',
                'staff_assignment_id' => $assignment->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['publicKey' => ['challenge', 'rpId', 'timeout', 'userVerification', 'allowCredentials']]);
    }

    public function test_hr_sidebar_lists_clocking_before_people(): void
    {
        $sidebar = file_get_contents(resource_path('views/components/hr/sidebar.blade.php'));

        $dashboardPosition = strpos($sidebar, "'label' => 'Dashboard'");
        $clockingPosition = strpos($sidebar, "'label' => 'Clocking'");
        $attendancePosition = strpos($sidebar, "'label' => 'Attendance'");
        $biometricAttendancePosition = strpos($sidebar, "'label' => 'Biometric Attendance'");
        $peoplePosition = strpos($sidebar, "'label' => 'People'");

        $this->assertNotFalse($dashboardPosition);
        $this->assertNotFalse($clockingPosition);
        $this->assertNotFalse($attendancePosition);
        $this->assertNotFalse($biometricAttendancePosition);
        $this->assertNotFalse($peoplePosition);
        $this->assertLessThan($clockingPosition, $dashboardPosition);
        $this->assertLessThan($attendancePosition, $clockingPosition);
        $this->assertLessThan($biometricAttendancePosition, $attendancePosition);
        $this->assertLessThan($peoplePosition, $clockingPosition);
    }

    public function test_hr_user_can_register_legacy_biometric_machine(): void
    {
        [$user, $organization] = $this->createHrBiometricContext(['Manage HR Biometrics']);

        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.attendance'))
            ->post(route('hr.biometrics.legacy-devices.store'), [
                'legacy_device_name' => 'Main Door Terminal',
                'legacy_device_provider' => 'ZKTeco',
                'legacy_device_identifier' => 'ZK-MAIN-001',
                'legacy_device_location' => 'Main Entrance',
                'legacy_device_notes' => 'Front office device',
            ]);

        $response->assertRedirect(route('hr.biometrics.attendance'));
        $response->assertSessionHas('status', 'Biometric machine registered.');
        $this->assertDatabaseHas('hr_biometric_devices', [
            'organization_id' => $organization->id,
            'name' => 'Main Door Terminal',
            'provider' => 'zkteco',
            'device_id' => 'ZK-MAIN-001',
            'location' => 'Main Entrance',
            'registered_by_user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_offline_clocking_upload_uses_selected_registered_machine(): void
    {
        [$user, $organization] = $this->createHrBiometricContext(['Manage HR Biometrics']);

        $assignment = StaffAssignment::create([
            'organization_id' => $organization->id,
            'staff_uuid' => 'staff-001',
            'staff_name' => 'Jane Doe',
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $device = HrBiometricDevice::create([
            'organization_id' => $organization->id,
            'name' => 'Main Door Terminal',
            'provider' => 'zkteco',
            'device_id' => 'ZK-MAIN-001',
            'location' => 'Main Entrance',
            'is_active' => true,
            'registered_by_user_id' => $user->id,
        ]);

        $file = UploadedFile::fake()->createWithContent('legacy-clocking.csv', implode("\n", [
            'uid,user_id,name,timestamp,verify_type,punch_type,result',
            '1,staff-001,Jane Doe,2026-06-03 08:05:00,fingerprint,in,success',
        ]));

        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.attendance'))
            ->post(route('hr.biometrics.legacy-device-import'), [
                'legacy_biometric_device_id' => $device->id,
                'legacy_device_file' => $file,
            ]);

        $response->assertRedirect(route('hr.biometrics.attendance'));
        $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Imported 1 offline clocking events from Main Door Terminal.'));

        $this->assertDatabaseHas('hr_biometric_verifications', [
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => 'staff-001',
            'modality' => 'fingerprint',
            'result' => 'success',
            'provider' => 'zkteco',
            'device_id' => 'ZK-MAIN-001',
            'source_event_id' => '1',
        ]);

        $this->assertDatabaseHas('hr_attendance_ledger', [
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => 'staff-001',
            'punch_type' => 'in',
            'provider' => 'zkteco',
            'device_id' => 'ZK-MAIN-001',
            'source_event_id' => '1',
        ]);

        $this->assertNotNull($device->fresh()?->last_synced_at);
    }

    /**
     * @return array{0: User, 1: Organization, 2: Business}
     */
    private function createHrBiometricContext(array $permissions, array $organizationOverrides = []): array
    {
        $business = Business::create([
            'name' => 'HR Test Business',
            'email' => 'hr-test@example.com',
            'address' => 'Kampala',
            'account_number' => 'HR-BIZ-001',
        ]);

        $organization = Organization::create(array_merge([
            'name' => 'HR Test Organization',
            'external_business_uuid' => $business->uuid,
            'weekend_days' => [0, 6],
            'biometric_network_restriction_enabled' => false,
            'biometric_allowed_networks' => [],
            'biometric_geofence_enabled' => false,
            'biometric_geofence_latitude' => null,
            'biometric_geofence_longitude' => null,
            'biometric_geofence_radius_meters' => 100,
            'biometric_geofence_max_accuracy_meters' => 150,
        ], $organizationOverrides));

        $user = User::factory()->create([
            'business_id' => $business->id,
            'permissions' => $permissions,
        ]);

        return [$user, $organization, $business];
    }

    private function createActiveStaffAssignment(Organization $organization, User $user): StaffAssignment
    {
        return StaffAssignment::create([
            'organization_id' => $organization->id,
            'staff_uuid' => $user->staff_uuid,
            'staff_name' => $user->name,
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ]);
    }

    private function createApprovedOffsiteDuty(Organization $organization, StaffAssignment $assignment): HrStaffUnavailability
    {
        return HrStaffUnavailability::create([
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'reason_type' => HrStaffUnavailability::REASON_OFFSITE_DUTY,
            'title' => 'OA-site Duty',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(),
            'status' => HrStaffUnavailability::STATUS_APPROVED,
            'blocks_rosters' => true,
        ]);
    }
}
