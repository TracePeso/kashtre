<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Mail\HrBiometricEnrollmentCodeMail;
use App\Models\HrBiometricEnrollmentSession;
use App\Models\Business;
use App\Models\HrBiometricDevice;
use App\Models\HrBiometricProfile;
use App\Models\HrStaffUnavailability;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\MobileFingerprintCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    public function test_staff_with_active_assignment_can_open_clocking_tab_without_hr_biometric_permissions(): void
    {
        [, $organization, $business] = $this->createHrBiometricContext([]);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'staff_uuid' => 'staff-clocking-access-001',
            'permissions' => [],
        ]);
        $this->createStaffAssignmentForUser($organization, $staffUser);

        $this->actingAs($staffUser);

        $this->get(route('hr.clocking.index'))
            ->assertOk()
            ->assertSee('Fingerprint attendance actions');
    }

    public function test_staff_clocking_prompt_only_returns_fingerprints_enrolled_for_the_authenticated_staff_user(): void
    {
        [$hrUser, $organization, $business] = $this->createHrBiometricContext([]);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'staff_uuid' => 'staff-clocking-prompt-001',
            'permissions' => [],
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $this->createMobileFingerprintProfile($assignment, $hrUser, [
            'external_reference' => 'cred-own-001',
        ]);

        $otherUser = User::factory()->create([
            'business_id' => $business->id,
            'staff_uuid' => 'staff-clocking-prompt-002',
            'permissions' => [],
        ]);
        $otherAssignment = $this->createStaffAssignmentForUser($organization, $otherUser);
        $this->createMobileFingerprintProfile($otherAssignment, $hrUser, [
            'external_reference' => 'cred-other-001',
        ]);

        $this->actingAs($staffUser);

        $response = $this->postJson(route('hr.biometrics.mobile-fingerprint.options'), [
            'action' => 'verify',
        ]);

        $response->assertOk();
        $response->assertJsonPath('publicKey.allowCredentials.0.id', 'cred-own-001');
        $this->assertCount(1, $response->json('publicKey.allowCredentials'));
    }

    public function test_staff_can_clock_in_from_clocking_tab_with_enrolled_fingerprint(): void
    {
        [$hrUser, $organization, $business] = $this->createHrBiometricContext([]);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'staff_uuid' => 'staff-clocking-verify-001',
            'permissions' => [],
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $profile = $this->createMobileFingerprintProfile($assignment, $hrUser, [
            'external_reference' => 'cred-clock-in-001',
        ]);

        app()->instance(MobileFingerprintCredentialService::class, new class($profile) extends MobileFingerprintCredentialService
        {
            public function __construct(private HrBiometricProfile $profile)
            {
            }

            public function verify(\Illuminate\Http\Request $request, \App\Models\Organization $organization, array $payload): HrBiometricProfile
            {
                return $this->profile->fresh();
            }
        });

        $this->actingAs($staffUser);

        $this->postJson(route('hr.biometrics.mobile-fingerprint.options'), [
            'action' => 'verify',
        ])->assertOk();

        $response = $this
            ->from(route('hr.clocking.index'))
            ->post(route('hr.biometrics.verify'), [
                'modality' => 'fingerprint',
                'fingerprint_assertion' => json_encode(['id' => 'cred-clock-in-001']),
                'punch_type' => 'in',
            ]);

        $response->assertRedirect(route('hr.clocking.index'));
        $response->assertSessionHas('biometric_verification', fn (array $result): bool => (bool) ($result['passed'] ?? false));

        $this->assertDatabaseHas('hr_attendance_ledger', [
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => $assignment->staff_uuid,
            'punch_type' => 'in',
        ]);
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

    public function test_manage_hr_biometrics_can_send_and_confirm_secret_code_for_secure_enrollment(): void
    {
        Mail::fake();

        [$user, $organization, $business] = $this->createHrBiometricContext(['Manage HR Biometrics']);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'email' => 'staff-secure@example.com',
            'staff_uuid' => 'staff-secure-001',
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);

        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.enrollment'))
            ->post(route('hr.biometrics.enrollment-authorization.send'), [
                'staff_assignment_id' => $assignment->id,
            ]);

        $response->assertRedirect(route('hr.biometrics.enrollment'));
        $response->assertSessionHas('status');

        $session = HrBiometricEnrollmentSession::query()->where('staff_assignment_id', $assignment->id)->first();

        $this->assertNotNull($session);
        $this->assertSame('staff-secure@example.com', $session->recipient_email);
        $this->assertNull($session->confirmed_at);

        $sentCode = null;
        Mail::assertSent(HrBiometricEnrollmentCodeMail::class, function (HrBiometricEnrollmentCodeMail $mail) use (&$sentCode, $session): bool {
            $sentCode = $mail->secretCode;

            return $mail->enrollmentSession->is($session);
        });

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $sentCode);

        $confirmResponse = $this
            ->from(route('hr.biometrics.enrollment'))
            ->post(route('hr.biometrics.enrollment-authorization.confirm'), [
                'enrollment_session_uuid' => $session->uuid,
                'secret_code' => $sentCode,
            ]);

        $confirmResponse->assertRedirect(route('hr.biometrics.enrollment'));
        $confirmResponse->assertSessionHas('status');

        $session->refresh();

        $this->assertNotNull($session->confirmed_at);
        $this->assertNotNull($session->capture_deadline_at);
        $this->assertTrue($session->capture_deadline_at->greaterThan($session->confirmed_at));
    }

    public function test_staff_can_open_signed_enrollment_link_and_confirm_secret_code_without_hr_login(): void
    {
        [$user, $organization, $business] = $this->createHrBiometricContext(['Manage HR Biometrics']);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'email' => 'staff-public-confirm@example.com',
            'staff_uuid' => 'staff-public-confirm-001',
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $session = $this->createEnrollmentSession($organization, $assignment, $user, [
            'recipient_email' => $staffUser->email,
            'metadata' => ['recipient_user_id' => $staffUser->id],
        ]);

        $showUrl = URL::signedRoute('biometric-enrollment.show', ['enrollmentSession' => $session]);
        $confirmUrl = URL::signedRoute('biometric-enrollment.confirm', ['enrollmentSession' => $session]);

        $this->get($showUrl)
            ->assertOk()
            ->assertSee('Secure Device Authorization');

        $response = $this
            ->from($showUrl)
            ->post($confirmUrl, [
                'secret_code' => '123456',
            ]);

        $response->assertRedirect($showUrl);
        $response->assertSessionHas('status');

        $session->refresh();

        $this->assertNotNull($session->confirmed_at);
        $this->assertNotNull($session->capture_deadline_at);
        $this->assertTrue($session->capture_deadline_at->greaterThan($session->confirmed_at));
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

    public function test_mobile_fingerprint_enrollment_prompt_requires_confirmed_secret_code_session(): void
    {
        [$user, $organization, $business] = $this->createHrBiometricContext(['Manage HR Biometrics']);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'email' => 'staff-gated@example.com',
            'staff_uuid' => 'staff-gated-001',
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $session = $this->createEnrollmentSession($organization, $assignment, $user);

        $this->actingAs($user);

        $response = $this->postJson(route('hr.biometrics.mobile-fingerprint.options'), [
            'action' => 'enroll',
            'staff_assignment_id' => $assignment->id,
            'enrollment_session_uuid' => $session->uuid,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('enrollment_session_uuid');
    }

    public function test_confirmed_secret_code_session_allows_mobile_fingerprint_enrollment_prompt(): void
    {
        [$user, $organization, $business] = $this->createHrBiometricContext(['Manage HR Biometrics']);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'email' => 'staff-authorized@example.com',
            'staff_uuid' => 'staff-authorized-001',
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $session = $this->createEnrollmentSession($organization, $assignment, $user, [
            'confirmed_at' => now(),
            'capture_deadline_at' => now()->addMinutes(2),
        ]);

        $this->actingAs($user);

        $response = $this->postJson(route('hr.biometrics.mobile-fingerprint.options'), [
            'action' => 'enroll',
            'staff_assignment_id' => $assignment->id,
            'enrollment_session_uuid' => $session->uuid,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['publicKey' => ['challenge', 'rp', 'user', 'timeout']]);
    }

    public function test_signed_public_enrollment_prompt_requires_confirmed_secret_code_session(): void
    {
        [$user, $organization, $business] = $this->createHrBiometricContext(['Manage HR Biometrics']);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'email' => 'staff-public-gated@example.com',
            'staff_uuid' => 'staff-public-gated-001',
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $session = $this->createEnrollmentSession($organization, $assignment, $user, [
            'recipient_email' => $staffUser->email,
            'metadata' => ['recipient_user_id' => $staffUser->id],
        ]);

        $response = $this->postJson(
            URL::signedRoute('biometric-enrollment.mobile-fingerprint.options', ['enrollmentSession' => $session]),
            [
                'geo_latitude' => 0.3136112,
                'geo_longitude' => 32.5811112,
                'geo_accuracy' => 20,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('enrollment_session_uuid');
    }

    public function test_signed_public_secure_enrollment_completes_for_staff_recipient(): void
    {
        [$user, $organization, $business] = $this->createHrBiometricContext(['Manage HR Biometrics']);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'email' => 'staff-public-complete@example.com',
            'staff_uuid' => 'staff-public-complete-001',
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $session = $this->createEnrollmentSession($organization, $assignment, $user, [
            'recipient_email' => $staffUser->email,
            'metadata' => ['recipient_user_id' => $staffUser->id],
            'confirmed_at' => now(),
            'capture_deadline_at' => now()->addMinutes(2),
        ]);

        app()->instance(MobileFingerprintCredentialService::class, new class extends MobileFingerprintCredentialService
        {
            public function enroll(\Illuminate\Http\Request $request, \App\Models\StaffAssignment $staffAssignment, array $payload): array
            {
                return [
                    'credential_id' => 'public-cred-001',
                    'public_key_cose' => 'cose-key',
                    'public_key_pem' => 'pem-key',
                    'sign_count' => 0,
                    'origin' => 'https://example.test',
                    'rp_id' => 'example.test',
                    'transports' => ['internal'],
                    'registered_at' => now()->toIso8601String(),
                ];
            }
        });

        Storage::fake('local');

        $showUrl = URL::signedRoute('biometric-enrollment.show', ['enrollmentSession' => $session]);
        $completeUrl = URL::signedRoute('biometric-enrollment.complete', ['enrollmentSession' => $session]);

        $response = $this
            ->from($showUrl)
            ->post($completeUrl, array_merge(
                $this->validSecureEnrollmentPayload($assignment, $session),
                ['fingerprint_credential' => json_encode(['id' => 'public-cred-001'])]
            ));

        $response->assertRedirect($showUrl);
        $response->assertSessionHas('status', 'Biometric enrollment completed successfully.');

        $session->refresh();

        $this->assertNotNull($session->completed_at);
        $this->assertSame($staffUser->id, $session->completed_by_user_id);

        $this->assertDatabaseHas('hr_biometric_profiles', [
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'modality' => HrBiometricProfile::MODALITY_FINGERPRINT,
            'external_reference' => 'public-cred-001',
            'status' => 'active',
            'enrolled_by_user_id' => $staffUser->id,
        ]);
        $this->assertDatabaseHas('hr_biometric_profiles', [
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'modality' => HrBiometricProfile::MODALITY_FACE,
            'label' => 'Primary face',
            'status' => 'active',
            'enrolled_by_user_id' => $staffUser->id,
        ]);
    }

    public function test_manage_hr_biometrics_can_store_multiple_geofence_locations(): void
    {
        [$user, $organization] = $this->createHrBiometricContext(['Manage HR Biometrics']);

        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.settings'))
            ->patch(route('hr.biometrics.geofence-policy'), [
                'biometric_geofence_enabled' => '1',
                'biometric_geofence_locations' => [
                    [
                        'name' => 'Main Office',
                        'latitude' => '0.3475964',
                        'longitude' => '32.5825197',
                        'radius_meters' => '150',
                        'max_accuracy_meters' => '100',
                    ],
                    [
                        'name' => 'Branch Office',
                        'latitude' => '0.3136111',
                        'longitude' => '32.5811111',
                        'radius_meters' => '200',
                        'max_accuracy_meters' => '120',
                    ],
                ],
            ]);

        $response->assertRedirect(route('hr.biometrics.settings'));
        $response->assertSessionHas('status', 'Biometric office geofence policy updated.');

        $organization->refresh();

        $this->assertTrue($organization->biometric_geofence_enabled);
        $this->assertSame(2, count($organization->biometric_geofence_locations ?? []));
        $this->assertSame('Main Office', $organization->biometric_geofence_locations[0]['name'] ?? null);
        $this->assertSame('Branch Office', $organization->biometric_geofence_locations[1]['name'] ?? null);
        $this->assertSame(0.3475964, $organization->biometric_geofence_latitude);
        $this->assertSame(32.5825197, $organization->biometric_geofence_longitude);
        $this->assertSame(150, $organization->biometric_geofence_radius_meters);
        $this->assertSame(100, $organization->biometric_geofence_max_accuracy_meters);
    }

    public function test_mobile_fingerprint_attendance_prompt_allows_any_configured_geofence_location(): void
    {
        [$user] = $this->createHrBiometricContext(
            ['View HR Staff'],
            [
                'biometric_geofence_enabled' => true,
                'biometric_geofence_locations' => [
                    [
                        'name' => 'Main Office',
                        'latitude' => 0.3475964,
                        'longitude' => 32.5825197,
                        'radius_meters' => 150,
                        'max_accuracy_meters' => 100,
                    ],
                    [
                        'name' => 'Branch Office',
                        'latitude' => 0.3136111,
                        'longitude' => 32.5811111,
                        'radius_meters' => 150,
                        'max_accuracy_meters' => 100,
                    ],
                ],
            ]
        );

        $this->actingAs($user);

        $response = $this->postJson(route('hr.biometrics.mobile-fingerprint.options'), [
            'action' => 'verify',
            'geo_latitude' => 0.3136112,
            'geo_longitude' => 32.5811112,
            'geo_accuracy' => 35,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['publicKey' => ['challenge', 'rpId', 'timeout', 'userVerification', 'allowCredentials']]);
    }

    public function test_secure_biometric_enrollment_requires_active_two_minute_window(): void
    {
        [$user, $organization, $business] = $this->createHrBiometricContext(['Manage HR Biometrics']);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'email' => 'staff-expired@example.com',
            'staff_uuid' => 'staff-expired-001',
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $session = $this->createEnrollmentSession($organization, $assignment, $user, [
            'confirmed_at' => now()->subMinutes(3),
            'capture_deadline_at' => now()->subMinute(),
        ]);

        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.enrollment'))
            ->post(route('hr.biometrics.secure-enrollment'), array_merge(
                $this->validSecureEnrollmentPayload($assignment, $session),
                ['fingerprint_credential' => json_encode(['id' => 'cred-expired'])]
            ));

        $response->assertRedirect(route('hr.biometrics.enrollment'));
        $response->assertSessionHasErrors('enrollment_session_uuid');
    }

    public function test_secure_biometric_enrollment_creates_face_and_fingerprint_profiles_and_replaces_existing_profiles(): void
    {
        [$user, $organization, $business] = $this->createHrBiometricContext(['Manage HR Biometrics']);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'email' => 'staff-complete@example.com',
            'staff_uuid' => 'staff-complete-001',
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $session = $this->createEnrollmentSession($organization, $assignment, $user, [
            'confirmed_at' => now(),
            'capture_deadline_at' => now()->addMinutes(2),
        ]);

        $oldFingerprint = HrBiometricProfile::create([
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => $assignment->staff_uuid,
            'staff_name' => $assignment->staff_name,
            'modality' => HrBiometricProfile::MODALITY_FINGERPRINT,
            'label' => 'Old fingerprint',
            'provider' => 'mobile-webauthn',
            'external_reference' => 'old-cred',
            'template_digest' => hash('sha256', 'old-cred'),
            'template_payload' => json_encode(['public_key_pem' => 'old']),
            'verification_threshold' => 0.98,
            'status' => 'active',
            'enrolled_by_user_id' => $user->id,
            'enrolled_at' => now()->subDay(),
        ]);

        $oldFace = HrBiometricProfile::create([
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => $assignment->staff_uuid,
            'staff_name' => $assignment->staff_name,
            'modality' => HrBiometricProfile::MODALITY_FACE,
            'label' => 'Old face',
            'provider' => 'browser-camera',
            'face_descriptor' => array_fill(0, 16, 0.125),
            'quality_score' => 84,
            'verification_threshold' => 0.86,
            'status' => 'active',
            'enrolled_by_user_id' => $user->id,
            'enrolled_at' => now()->subDay(),
        ]);

        app()->instance(MobileFingerprintCredentialService::class, new class extends MobileFingerprintCredentialService
        {
            public function enroll(\Illuminate\Http\Request $request, \App\Models\StaffAssignment $staffAssignment, array $payload): array
            {
                return [
                    'credential_id' => 'new-cred-001',
                    'public_key_cose' => 'cose-key',
                    'public_key_pem' => 'pem-key',
                    'sign_count' => 0,
                    'origin' => 'https://example.test',
                    'rp_id' => 'example.test',
                    'transports' => ['internal'],
                    'registered_at' => now()->toIso8601String(),
                ];
            }
        });

        Storage::fake('local');
        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.enrollment'))
            ->post(route('hr.biometrics.secure-enrollment'), array_merge(
                $this->validSecureEnrollmentPayload($assignment, $session),
                ['fingerprint_credential' => json_encode(['id' => 'new-cred-001'])]
            ));

        $response->assertRedirect(route('hr.biometrics.enrollment'));
        $response->assertSessionHas('status', "{$assignment->staff_name}'s secure biometric enrollment completed.");

        $oldFingerprint->refresh();
        $oldFace->refresh();
        $session->refresh();

        $this->assertSame('inactive', $oldFingerprint->status);
        $this->assertSame('inactive', $oldFace->status);
        $this->assertNotNull($session->completed_at);
        $this->assertSame($user->id, $session->completed_by_user_id);

        $this->assertDatabaseHas('hr_biometric_profiles', [
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'modality' => HrBiometricProfile::MODALITY_FINGERPRINT,
            'external_reference' => 'new-cred-001',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('hr_biometric_profiles', [
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'modality' => HrBiometricProfile::MODALITY_FACE,
            'label' => 'Primary face',
            'status' => 'active',
        ]);

        $faceProfile = HrBiometricProfile::query()
            ->where('organization_id', $organization->id)
            ->where('staff_assignment_id', $assignment->id)
            ->where('modality', HrBiometricProfile::MODALITY_FACE)
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertNotEmpty($faceProfile->metadata['face_photo_path'] ?? null);
        Storage::disk('local')->assertExists($faceProfile->metadata['face_photo_path']);
    }

    public function test_secure_biometric_enrollment_requires_live_face_photo_capture(): void
    {
        [$user, $organization, $business] = $this->createHrBiometricContext(['Manage HR Biometrics']);
        $staffUser = User::factory()->create([
            'business_id' => $business->id,
            'email' => 'staff-photo@example.com',
            'staff_uuid' => 'staff-photo-001',
        ]);
        $assignment = $this->createStaffAssignmentForUser($organization, $staffUser);
        $session = $this->createEnrollmentSession($organization, $assignment, $user, [
            'confirmed_at' => now(),
            'capture_deadline_at' => now()->addMinutes(2),
        ]);

        $this->actingAs($user);

        $response = $this
            ->from(route('hr.biometrics.enrollment'))
            ->post(route('hr.biometrics.secure-enrollment'), array_merge(
                $this->validSecureEnrollmentPayload($assignment, $session),
                [
                    'fingerprint_credential' => json_encode(['id' => 'new-cred-photo']),
                    'face_photo' => '',
                ]
            ));

        $response->assertRedirect(route('hr.biometrics.enrollment'));
        $response->assertSessionHasErrors('face_photo');
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
            'biometric_geofence_locations' => [],
        ], $organizationOverrides));

        $user = User::factory()->create([
            'business_id' => $business->id,
            'permissions' => $permissions,
        ]);

        return [$user, $organization, $business];
    }

    private function createStaffAssignmentForUser(Organization $organization, User $user, array $overrides = []): StaffAssignment
    {
        return StaffAssignment::create(array_merge([
            'organization_id' => $organization->id,
            'staff_uuid' => $user->staff_uuid,
            'staff_name' => $user->name,
            'assignment_type' => 'primary',
            'status' => 'active',
            'assigned_at' => now(),
        ], $overrides));
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

    private function createEnrollmentSession(Organization $organization, StaffAssignment $assignment, User $actor, array $overrides = []): HrBiometricEnrollmentSession
    {
        $defaults = [
            'organization_id' => $organization->id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => $assignment->staff_uuid,
            'staff_name' => $assignment->staff_name,
            'recipient_email' => 'staff@example.com',
            'purpose' => 'enrollment',
            'secret_code_hash' => Hash::make('123456'),
            'secret_code_sent_at' => now(),
            'secret_code_expires_at' => now()->addMinutes(10),
            'authorized_by_user_id' => $actor->id,
            'metadata' => [],
        ];

        return HrBiometricEnrollmentSession::create(array_merge($defaults, $overrides));
    }

    private function createMobileFingerprintProfile(StaffAssignment $assignment, User $actor, array $overrides = []): HrBiometricProfile
    {
        return HrBiometricProfile::create(array_merge([
            'organization_id' => $assignment->organization_id,
            'staff_assignment_id' => $assignment->id,
            'staff_uuid' => $assignment->staff_uuid,
            'staff_name' => $assignment->staff_name,
            'modality' => HrBiometricProfile::MODALITY_FINGERPRINT,
            'label' => 'Phone fingerprint',
            'provider' => 'mobile-webauthn',
            'device_id' => 'PHONE-001',
            'external_reference' => 'cred-default-001',
            'template_payload' => json_encode(['public_key_pem' => 'pem-key', 'sign_count' => 0]),
            'verification_threshold' => 0.98,
            'status' => 'active',
            'enrolled_by_user_id' => $actor->id,
            'enrolled_at' => now(),
            'metadata' => [],
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function validSecureEnrollmentPayload(StaffAssignment $assignment, HrBiometricEnrollmentSession $session): array
    {
        return [
            'enrollment_session_uuid' => $session->uuid,
            'staff_assignment_id' => $assignment->id,
            'fingerprint_label' => 'Phone fingerprint',
            'fingerprint_device_id' => 'PHONE-001',
            'fingerprint_verification_threshold' => '0.98',
            'face_label' => 'Primary face',
            'face_provider' => 'browser-camera',
            'face_device_id' => 'CAM-001',
            'face_verification_threshold' => '0.86',
            'face_descriptor' => json_encode(array_fill(0, 16, 0.125)),
            'face_sample' => json_encode([
                'protocol' => 'face-capture-v2',
                'liveness_passed' => true,
                'challenge' => [
                    ['step' => 'center', 'quality' => 82, 'detection' => 'detected', 'center' => 0.5, 'captured_at' => now()->toIso8601String()],
                    ['step' => 'left', 'quality' => 84, 'detection' => 'detected', 'center' => 0.35, 'captured_at' => now()->toIso8601String()],
                    ['step' => 'right', 'quality' => 85, 'detection' => 'detected', 'center' => 0.65, 'captured_at' => now()->toIso8601String()],
                ],
            ]),
            'face_photo' => $this->sampleFacePhotoDataUrl(),
            'quality_score' => 84,
            'face_protocol_version' => 'face-capture-v2',
            'face_liveness_passed' => '1',
            'face_liveness_challenge' => 'center,left,right',
            'face_sample_count' => 3,
            'face_detection_status' => 'detected',
            'face_quality_min' => 82,
            'face_quality_average' => 84,
            'capture_source' => 'browser_camera',
        ];
    }

    private function sampleFacePhotoDataUrl(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn4u3sAAAAASUVORK5CYII=';
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
