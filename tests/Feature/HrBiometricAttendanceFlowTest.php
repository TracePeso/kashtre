<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Business;
use App\Models\HrStaffUnavailability;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrBiometricAttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_attendance_template_shows_explicit_login_and_logout_actions(): void
    {
        $view = file_get_contents(resource_path('views/hr/biometrics/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("Attendance Login / Logout", $view);
        $this->assertStringContainsString("Login / Clock In", $view);
        $this->assertStringContainsString("Logout / Clock Out", $view);
        $this->assertLessThan(
            strpos($view, 'Enrolled Profiles'),
            strpos($view, 'Attendance Login / Logout')
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

    public function test_hr_sidebar_lists_attendance_before_people(): void
    {
        $sidebar = file_get_contents(resource_path('views/components/hr/sidebar.blade.php'));

        $attendancePosition = strpos($sidebar, "'label' => 'Attendance'");
        $peoplePosition = strpos($sidebar, "'label' => 'People'");

        $this->assertNotFalse($attendancePosition);
        $this->assertNotFalse($peoplePosition);
        $this->assertLessThan($peoplePosition, $attendancePosition);
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
