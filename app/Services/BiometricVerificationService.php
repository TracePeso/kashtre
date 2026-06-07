<?php

namespace App\Services;

use App\Models\HrBiometricProfile;
use App\Models\HrBiometricVerification;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BiometricVerificationService
{
    private const DEFAULT_FINGERPRINT_THRESHOLD = 0.9800;
    private const DEFAULT_FACE_THRESHOLD = 0.8600;
    private const MIN_FACE_ENROLLMENT_QUALITY = 70.0;
    private const MIN_FACE_VERIFICATION_QUALITY = 60.0;
    private const FACE_CAPTURE_PROTOCOL_VERSION = 'face-capture-v2';
    private const MIN_FACE_LIVENESS_SAMPLES = 3;

    public function enroll(StaffAssignment $staffAssignment, string $modality, array $payload, User $actor): HrBiometricProfile
    {
        $this->assertValidModality($modality);

        $templatePayload = null;
        $templateDigest = null;
        $faceDescriptor = null;
        $externalReference = $this->cleanString($payload['external_reference'] ?? null);

        if ($modality === HrBiometricProfile::MODALITY_FINGERPRINT) {
            if (is_array($payload['mobile_credential'] ?? null)) {
                $templatePayload = json_encode($payload['mobile_credential']);
                $templateDigest = $externalReference ? $this->digestTemplate($externalReference) : null;
            } else {
                $templatePayload = $this->canonicalTemplate($payload['fingerprint_template'] ?? null);
                $templateDigest = $templatePayload ? $this->digestTemplate($templatePayload) : null;
            }

            if (! $templatePayload && ! $externalReference) {
                throw ValidationException::withMessages([
                    'fingerprint_template' => 'Register the phone fingerprint credential or provide a fingerprint template.',
                ]);
            }
        }

        if ($modality === HrBiometricProfile::MODALITY_FACE) {
            $faceDescriptor = $this->descriptorFromPayload($payload, true);
            $qualityScore = $this->qualityScore($payload['quality_score'] ?? null);

            if ($qualityScore === null || $qualityScore < self::MIN_FACE_ENROLLMENT_QUALITY) {
                throw ValidationException::withMessages([
                    'quality_score' => 'Face quality must be at least 70 before enrollment. Retake the photo with better light and a steady camera.',
                ]);
            }

            $this->assertFaceCaptureProtocol($payload, true);
            $payload['face_photo_metadata'] = $this->storeFacePhoto($staffAssignment, $payload);
        } else {
            $qualityScore = null;
        }

        return DB::transaction(function () use (
            $staffAssignment,
            $modality,
            $payload,
            $actor,
            $templatePayload,
            $templateDigest,
            $faceDescriptor,
            $externalReference,
            $qualityScore
        ): HrBiometricProfile {
            return HrBiometricProfile::create([
                'organization_id' => $staffAssignment->organization_id,
                'staff_assignment_id' => $staffAssignment->id,
                'staff_uuid' => $staffAssignment->staff_uuid,
                'staff_name' => $staffAssignment->staff_name,
                'modality' => $modality,
                'label' => $this->cleanString($payload['label'] ?? null) ?: $this->defaultLabel($modality),
                'provider' => $this->cleanString($payload['provider'] ?? null) ?: 'local',
                'device_id' => $this->cleanString($payload['device_id'] ?? null),
                'external_reference' => $externalReference,
                'template_digest' => $templateDigest,
                'template_payload' => $templatePayload,
                'face_descriptor' => $faceDescriptor,
                'quality_score' => $qualityScore,
                'verification_threshold' => $this->thresholdFromPayload($payload, $modality),
                'status' => 'active',
                'enrolled_by_user_id' => $actor->id,
                'enrolled_at' => now(),
                'metadata' => $this->enrollmentMetadata($payload),
            ]);
        });
    }

    public function verify(
        string $modality,
        array $payload,
        ?StaffAssignment $expectedStaffAssignment,
        ?HrBiometricProfile $expectedProfile,
        User $actor
    ): HrBiometricVerification {
        $this->assertValidModality($modality);

        $organizationId = $expectedProfile?->organization_id
            ?? $expectedStaffAssignment?->organization_id
            ?? Organization::current($actor)?->id;

        if (! $organizationId) {
            throw ValidationException::withMessages([
                'staff_assignment_id' => 'No organization is available for biometric verification.',
            ]);
        }

        $profiles = HrBiometricProfile::query()
            ->with('staffAssignment')
            ->where('organization_id', $organizationId)
            ->active()
            ->forModality($modality)
            ->when($expectedProfile, fn ($query) => $query->whereKey($expectedProfile->id))
            ->when(! $expectedProfile && $expectedStaffAssignment, fn ($query) => $query->where('staff_assignment_id', $expectedStaffAssignment->id))
            ->get();

        if ($profiles->isEmpty()) {
            return $this->recordVerification(
                $organizationId,
                $modality,
                $payload,
                $actor,
                $expectedStaffAssignment,
                [
                    'profile' => null,
                    'score' => null,
                    'threshold' => $this->defaultThreshold($modality),
                    'passed' => false,
                    'failure_reason' => 'No active biometric profile was found.',
                ]
            );
        }

        $match = $modality === HrBiometricProfile::MODALITY_FINGERPRINT
            ? $this->matchFingerprint($profiles, $payload)
            : $this->matchFace($profiles, $payload);

        return $this->recordVerification($organizationId, $modality, $payload, $actor, $expectedStaffAssignment, $match);
    }

    /**
     * @param Collection<int, HrBiometricProfile> $profiles
     * @return array{profile: ?HrBiometricProfile, score: ?float, threshold: ?float, passed: bool, failure_reason: ?string}
     */
    private function matchFingerprint(Collection $profiles, array $payload): array
    {
        $profile = $this->profileFromPayload($profiles, $payload);
        $score = $this->scoreFromPayload($payload['match_score'] ?? null);

        if ($profile && $score !== null) {
            $threshold = $profile->verification_threshold ?: self::DEFAULT_FINGERPRINT_THRESHOLD;

            return [
                'profile' => $profile,
                'score' => $score,
                'threshold' => $threshold,
                'passed' => $score >= $threshold,
                'failure_reason' => $score >= $threshold ? null : 'Fingerprint score was below the required threshold.',
            ];
        }

        $templatePayload = $this->canonicalTemplate($payload['fingerprint_template'] ?? null);

        if ($templatePayload) {
            $digest = $this->digestTemplate($templatePayload);
            $profile = $profiles->first(fn (HrBiometricProfile $candidate): bool => hash_equals((string) $candidate->template_digest, $digest));

            if (! $profile) {
                return [
                    'profile' => null,
                    'score' => 0.0,
                    'threshold' => self::DEFAULT_FINGERPRINT_THRESHOLD,
                    'passed' => false,
                    'failure_reason' => 'Fingerprint did not match an active profile.',
                ];
            }

            return [
                'profile' => $profile,
                'score' => 1.0,
                'threshold' => $profile->verification_threshold ?: self::DEFAULT_FINGERPRINT_THRESHOLD,
                'passed' => true,
                'failure_reason' => null,
            ];
        }

        return [
            'profile' => $profile,
            'score' => $score,
            'threshold' => $profile?->verification_threshold ?: self::DEFAULT_FINGERPRINT_THRESHOLD,
            'passed' => false,
            'failure_reason' => 'Fingerprint capture, scanner reference, or scanner match score is required.',
        ];
    }

    /**
     * @param Collection<int, HrBiometricProfile> $profiles
     * @return array{profile: ?HrBiometricProfile, score: ?float, threshold: ?float, passed: bool, failure_reason: ?string}
     */
    private function matchFace(Collection $profiles, array $payload): array
    {
        $descriptor = $this->descriptorFromPayload($payload, false);

        if (! $descriptor) {
            return [
                'profile' => null,
                'score' => null,
                'threshold' => self::DEFAULT_FACE_THRESHOLD,
                'passed' => false,
                'failure_reason' => 'Face capture is required.',
            ];
        }

        $protocolFailure = $this->faceCaptureProtocolFailure($payload, false);

        if ($protocolFailure !== null) {
            return [
                'profile' => null,
                'score' => null,
                'threshold' => self::DEFAULT_FACE_THRESHOLD,
                'passed' => false,
                'failure_reason' => $protocolFailure,
            ];
        }

        $bestProfile = null;
        $bestScore = null;

        foreach ($profiles as $profile) {
            $candidateDescriptor = $profile->face_descriptor;

            if (! is_array($candidateDescriptor)) {
                continue;
            }

            $score = $this->cosineSimilarity($descriptor, $candidateDescriptor);

            if ($score === null) {
                continue;
            }

            if ($bestScore === null || $score > $bestScore) {
                $bestProfile = $profile;
                $bestScore = $score;
            }
        }

        if (! $bestProfile) {
            return [
                'profile' => null,
                'score' => null,
                'threshold' => self::DEFAULT_FACE_THRESHOLD,
                'passed' => false,
                'failure_reason' => 'No comparable face profile was found.',
            ];
        }

        $threshold = $bestProfile->verification_threshold ?: self::DEFAULT_FACE_THRESHOLD;

        return [
            'profile' => $bestProfile,
            'score' => $bestScore,
            'threshold' => $threshold,
            'passed' => $bestScore >= $threshold,
            'failure_reason' => $bestScore >= $threshold ? null : 'Face score was below the required threshold.',
        ];
    }

    /**
     * @param array{profile: ?HrBiometricProfile, score: ?float, threshold: ?float, passed: bool, failure_reason: ?string} $match
     */
    private function recordVerification(
        int $organizationId,
        string $modality,
        array $payload,
        User $actor,
        ?StaffAssignment $expectedStaffAssignment,
        array $match
    ): HrBiometricVerification {
        $profile = $match['profile'];
        $staffAssignment = $profile?->staffAssignment ?? $expectedStaffAssignment;
        $passed = $profile && $match['passed'];

        $verification = HrBiometricVerification::create([
            'organization_id' => $organizationId,
            'hr_biometric_profile_id' => $profile?->id,
            'staff_assignment_id' => $staffAssignment?->id,
            'staff_uuid' => $staffAssignment?->staff_uuid ?? $profile?->staff_uuid,
            'modality' => $modality,
            'result' => $passed ? HrBiometricVerification::RESULT_SUCCESS : HrBiometricVerification::RESULT_FAILED,
            'score' => $match['score'],
            'threshold' => $match['threshold'],
            'provider' => $this->cleanString($payload['provider'] ?? null) ?: ($profile?->provider ?? 'local'),
            'device_id' => $this->cleanString($payload['device_id'] ?? null) ?: $profile?->device_id,
            'source_event_id' => $this->cleanString($payload['source_event_id'] ?? null),
            'event_type' => $this->cleanString($payload['punch_type'] ?? null) ?: $this->cleanString($payload['event_type'] ?? null),
            'verified_by_user_id' => $actor->id,
            'verified_at' => now(),
            'failure_reason' => $passed ? null : $match['failure_reason'],
            'metadata' => $this->verificationMetadata($payload),
        ]);

        if ($passed) {
            $profile->forceFill(['last_verified_at' => $verification->verified_at])->save();
            app(HybridAttendanceLedgerService::class)->recordFromVerification($verification, $payload['punch_type'] ?? null);
        }

        return $verification->load(['profile', 'staffAssignment']);
    }

    private function assertValidModality(string $modality): void
    {
        if (! in_array($modality, [HrBiometricProfile::MODALITY_FINGERPRINT, HrBiometricProfile::MODALITY_FACE], true)) {
            throw ValidationException::withMessages([
                'modality' => 'Choose fingerprint or face biometric mode.',
            ]);
        }
    }

    private function defaultLabel(string $modality): string
    {
        return $modality === HrBiometricProfile::MODALITY_FINGERPRINT ? 'Fingerprint' : 'Face profile';
    }

    private function defaultThreshold(string $modality): float
    {
        return $modality === HrBiometricProfile::MODALITY_FINGERPRINT
            ? self::DEFAULT_FINGERPRINT_THRESHOLD
            : self::DEFAULT_FACE_THRESHOLD;
    }

    private function thresholdFromPayload(array $payload, string $modality): float
    {
        $threshold = $payload['verification_threshold'] ?? null;

        if ($threshold === null || $threshold === '') {
            return $this->defaultThreshold($modality);
        }

        return max(0.0, min(1.0, (float) $threshold));
    }

    private function qualityScore(mixed $score): ?float
    {
        if ($score === null || $score === '' || ! is_numeric($score)) {
            return null;
        }

        return max(0.0, min(100.0, (float) $score));
    }

    private function scoreFromPayload(mixed $score): ?float
    {
        if ($score === null || $score === '' || ! is_numeric($score)) {
            return null;
        }

        $score = (float) $score;

        if ($score > 1.0 && $score <= 100.0) {
            $score = $score / 100.0;
        }

        return max(0.0, min(1.0, $score));
    }

    private function assertFaceCaptureProtocol(array $payload, bool $enrollment): void
    {
        $failure = $this->faceCaptureProtocolFailure($payload, $enrollment);

        if ($failure === null) {
            return;
        }

        throw ValidationException::withMessages([
            'face_descriptor' => $failure,
        ]);
    }

    private function faceCaptureProtocolFailure(array $payload, bool $enrollment): ?string
    {
        $version = $this->cleanString($payload['face_protocol_version'] ?? null);

        if ($version !== self::FACE_CAPTURE_PROTOCOL_VERSION) {
            return 'Use the guided face capture flow before saving or verifying a face profile.';
        }

        if (! $this->truthy($payload['face_liveness_passed'] ?? null)) {
            return 'Complete the live face challenge before saving or verifying a face profile.';
        }

        $sampleCount = (int) ($payload['face_sample_count'] ?? 0);

        if ($sampleCount < self::MIN_FACE_LIVENESS_SAMPLES) {
            return 'Capture at least three live face samples before saving or verifying.';
        }

        $detectionStatus = $this->cleanString($payload['face_detection_status'] ?? null);

        if (in_array($detectionStatus, ['not_detected', 'failed'], true)) {
            return 'A face must be detected in the camera frame before this check can continue.';
        }

        $minimumQuality = $this->qualityScore($payload['face_quality_min'] ?? $payload['quality_score'] ?? null);
        $requiredQuality = $enrollment ? self::MIN_FACE_ENROLLMENT_QUALITY : self::MIN_FACE_VERIFICATION_QUALITY;

        if ($minimumQuality === null || $minimumQuality < $requiredQuality) {
            return $enrollment
                ? 'Each enrollment sample must score at least 70 before saving a face profile.'
                : 'Retake the face check with better light and a steadier camera.';
        }

        return null;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function canonicalTemplate(mixed $template): ?string
    {
        if (! is_string($template)) {
            return null;
        }

        $template = trim(str_replace(["\r\n", "\r"], "\n", $template));

        return $template === '' ? null : $template;
    }

    private function digestTemplate(string $template): string
    {
        return hash_hmac('sha256', $template, $this->hmacKey());
    }

    private function hmacKey(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }

    /**
     * @return array<int, float>|null
     */
    private function descriptorFromPayload(array $payload, bool $required): ?array
    {
        $descriptor = $payload['face_descriptor'] ?? null;

        if (is_string($descriptor)) {
            $decoded = json_decode($descriptor, true);
            $descriptor = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (! is_array($descriptor)) {
            if ($required) {
                throw ValidationException::withMessages([
                    'face_descriptor' => 'Capture a face sample before saving.',
                ]);
            }

            return null;
        }

        $values = [];

        foreach ($descriptor as $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $values[] = round((float) $value, 6);
        }

        if (count($values) < 16) {
            if ($required) {
                throw ValidationException::withMessages([
                    'face_descriptor' => 'The face descriptor must include at least 16 numeric values.',
                ]);
            }

            return null;
        }

        if (count($values) > 4096) {
            throw ValidationException::withMessages([
                'face_descriptor' => 'The face descriptor is too large.',
            ]);
        }

        return array_values($values);
    }

    /**
     * @param array<int, float> $left
     * @param array<int, float> $right
     */
    private function cosineSimilarity(array $left, array $right): ?float
    {
        if (count($left) !== count($right) || count($left) === 0) {
            return null;
        }

        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        foreach ($left as $index => $leftValue) {
            $rightValue = (float) $right[$index];
            $dot += $leftValue * $rightValue;
            $leftNorm += $leftValue ** 2;
            $rightNorm += $rightValue ** 2;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return null;
        }

        $cosine = $dot / (sqrt($leftNorm) * sqrt($rightNorm));
        $normalized = ($cosine + 1.0) / 2.0;

        return round(max(0.0, min(1.0, $normalized)), 4);
    }

    /**
     * @param Collection<int, HrBiometricProfile> $profiles
     */
    private function profileFromPayload(Collection $profiles, array $payload): ?HrBiometricProfile
    {
        $profileUuid = $this->cleanString($payload['profile_uuid'] ?? null);

        if ($profileUuid) {
            $profile = $profiles->first(fn (HrBiometricProfile $candidate): bool => $candidate->uuid === $profileUuid);

            if ($profile) {
                return $profile;
            }
        }

        $externalReference = $this->cleanString($payload['external_reference'] ?? null);

        if ($externalReference) {
            return $profiles->first(fn (HrBiometricProfile $candidate): bool => $candidate->external_reference === $externalReference);
        }

        return null;
    }

    private function enrollmentMetadata(array $payload): array
    {
        return array_filter([
            'face_sample_digest' => $this->sampleDigest($payload['face_sample'] ?? null),
            'capture_source' => $this->cleanString($payload['capture_source'] ?? null),
            'face_protocol_version' => $this->cleanString($payload['face_protocol_version'] ?? null),
            'face_liveness_passed' => $this->truthy($payload['face_liveness_passed'] ?? null) ? true : null,
            'face_liveness_challenge' => $this->cleanString($payload['face_liveness_challenge'] ?? null),
            'face_sample_count' => isset($payload['face_sample_count']) ? (int) $payload['face_sample_count'] : null,
            'face_detection_status' => $this->cleanString($payload['face_detection_status'] ?? null),
            'face_quality_min' => $this->qualityScore($payload['face_quality_min'] ?? null),
            'face_quality_average' => $this->qualityScore($payload['face_quality_average'] ?? null),
            'face_photo_disk' => data_get($payload, 'face_photo_metadata.disk'),
            'face_photo_path' => data_get($payload, 'face_photo_metadata.path'),
            'face_photo_mime_type' => data_get($payload, 'face_photo_metadata.mime_type'),
            'face_photo_size_bytes' => data_get($payload, 'face_photo_metadata.size_bytes'),
            'face_photo_width' => data_get($payload, 'face_photo_metadata.width'),
            'face_photo_height' => data_get($payload, 'face_photo_metadata.height'),
            'face_photo_sha256' => data_get($payload, 'face_photo_metadata.sha256'),
            'face_photo_captured_at' => data_get($payload, 'face_photo_metadata.captured_at'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function verificationMetadata(array $payload): array
    {
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        return array_filter(array_merge($metadata, [
            'profile_uuid' => $this->cleanString($payload['profile_uuid'] ?? null),
            'external_reference' => $this->cleanString($payload['external_reference'] ?? null),
            'punch_type' => $this->cleanString($payload['punch_type'] ?? null),
            'face_sample_digest' => $this->sampleDigest($payload['face_sample'] ?? null),
            'face_protocol_version' => $this->cleanString($payload['face_protocol_version'] ?? null),
            'face_liveness_passed' => $this->truthy($payload['face_liveness_passed'] ?? null) ? true : null,
            'face_liveness_challenge' => $this->cleanString($payload['face_liveness_challenge'] ?? null),
            'face_sample_count' => isset($payload['face_sample_count']) ? (int) $payload['face_sample_count'] : null,
            'face_detection_status' => $this->cleanString($payload['face_detection_status'] ?? null),
            'face_quality_min' => $this->qualityScore($payload['face_quality_min'] ?? null),
            'face_quality_average' => $this->qualityScore($payload['face_quality_average'] ?? null),
        ]), fn ($value) => $value !== null && $value !== '');
    }

    private function sampleDigest(mixed $sample): ?string
    {
        if (! is_string($sample) || trim($sample) === '') {
            return null;
        }

        return hash_hmac('sha256', trim($sample), $this->hmacKey());
    }

    /**
     * @return array{disk: string, path: string, mime_type: string, size_bytes: int, width: int, height: int, sha256: string, captured_at: string}
     */
    private function storeFacePhoto(StaffAssignment $staffAssignment, array $payload): array
    {
        $photo = $this->cleanString($payload['face_photo'] ?? null);

        if ($photo === null) {
            throw ValidationException::withMessages([
                'face_photo' => 'Capture the live face photo before saving the biometric profile.',
            ]);
        }

        if (! preg_match('/^data:(image\/(?:jpeg|png|webp));base64,([A-Za-z0-9+\/=]+)$/', $photo, $matches)) {
            throw ValidationException::withMessages([
                'face_photo' => 'The captured face photo was not in a supported image format.',
            ]);
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'face_photo' => 'The captured face photo could not be decoded. Capture it again.',
            ]);
        }

        $imageInfo = @getimagesizefromstring($binary);

        if (! is_array($imageInfo) || empty($imageInfo['mime'])) {
            throw ValidationException::withMessages([
                'face_photo' => 'The captured face photo is not a valid image. Capture it again.',
            ]);
        }

        $mimeType = (string) $imageInfo['mime'];
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        if ($extension === null) {
            throw ValidationException::withMessages([
                'face_photo' => 'The captured face photo must be JPEG, PNG, or WebP.',
            ]);
        }

        $path = sprintf(
            'biometrics/face-captures/%d/%s/%s.%s',
            $staffAssignment->organization_id,
            $staffAssignment->staff_uuid,
            (string) Str::uuid(),
            $extension
        );

        Storage::disk('local')->put($path, $binary);

        return [
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($binary),
            'width' => (int) ($imageInfo[0] ?? 0),
            'height' => (int) ($imageInfo[1] ?? 0),
            'sha256' => hash('sha256', $binary),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
