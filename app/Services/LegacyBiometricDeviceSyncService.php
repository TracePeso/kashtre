<?php

namespace App\Services;

use App\Models\HrAttendanceLedger;
use App\Models\HrBiometricProfile;
use App\Models\HrBiometricVerification;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LegacyBiometricDeviceSyncService
{
    private const DEFAULT_PROVIDER = 'zkteco';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rowsFromText(string $contents, ?string $filename = null): array
    {
        $contents = trim($contents);

        if ($contents === '') {
            return [];
        }

        if ($this->looksLikeJson($contents, $filename)) {
            $decoded = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'legacy_device_payload' => 'The device import JSON could not be read.',
                ]);
            }

            return $this->rowsFromDecodedJson($decoded);
        }

        return $this->rowsFromCsv($contents);
    }

    /**
     * @param iterable<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    public function import(Organization $organization, iterable $rows, array $options = []): array
    {
        $stats = [
            'received' => 0,
            'imported' => 0,
            'duplicates' => 0,
            'unmatched' => 0,
            'failed' => 0,
            'profiles_created' => 0,
            'profiles_updated' => 0,
        ];

        DB::transaction(function () use ($organization, $rows, $options, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $stats['received']++;
                $event = $this->normalizeEvent($row, $options);

                if ($this->alreadyImported($organization, $event)) {
                    $stats['duplicates']++;
                    continue;
                }

                $profile = $this->profileForEvent($organization, $event);
                $staffAssignment = $profile?->staffAssignment ?? $this->staffAssignmentForEvent($organization, $event);

                if ($staffAssignment && $event['staff_reference']) {
                    $profileBefore = $profile;
                    $profile = $this->upsertDeviceProfile($staffAssignment, $event, $profile);

                    if (! $profileBefore && $profile?->wasRecentlyCreated) {
                        $stats['profiles_created']++;
                    } elseif ($profile?->wasChanged()) {
                        $stats['profiles_updated']++;
                    }
                }

                if (! $staffAssignment) {
                    $stats['unmatched']++;
                }

                $verification = $this->recordVerification(
                    $organization,
                    $event,
                    $staffAssignment,
                    $profile,
                    $options['actor'] ?? null
                );

                $stats['imported']++;

                if (! $verification->passed()) {
                    $stats['failed']++;
                }
            }
        });

        return $stats;
    }

    private function normalizeEvent(array $row, array $options): array
    {
        $normalized = $this->normalizeKeys($row);
        $defaultProvider = $this->cleanString($options['provider'] ?? null) ?: self::DEFAULT_PROVIDER;
        $provider = $this->firstValue($normalized, ['provider', 'manufacturer', 'terminal_provider']) ?: $defaultProvider;
        $deviceId = $this->firstValue($normalized, ['device_id', 'terminal_id', 'terminal_sn', 'serial_number', 'sn', 'device']);
        $deviceId = $deviceId ?: $this->cleanString($options['device_id'] ?? null);
        $staffUuid = $this->firstValue($normalized, ['staff_uuid', 'hr_staff_uuid']);
        $staffReference = $this->firstValue($normalized, [
            'external_reference',
            'device_user_id',
            'user_id',
            'pin',
            'enroll_number',
            'enroll_id',
            'employee_id',
            'employee_no',
            'staff_id',
            'biometric_id',
            'card_no',
            'badge_number',
        ]);
        $staffReference = $staffReference ?: $staffUuid;
        $verifiedAt = $this->timestampFromValue($this->firstValue($normalized, [
            'verified_at',
            'timestamp',
            'event_time',
            'punch_time',
            'date_time',
            'datetime',
            'time',
        ]));
        $eventType = $this->firstValue($normalized, [
            'event_type',
            'punch_type',
            'punch_state',
            'attendance_state',
            'state',
            'status',
            'type',
        ]);
        $resultValue = $this->firstValue($normalized, [
            'result',
            'access_result',
            'verification_result',
            'verify_result',
            'passed',
            'success',
        ]);

        if ($resultValue === null && $this->statusLooksLikeAccessResult($eventType)) {
            $resultValue = $eventType;
        }

        $sourceEventId = $this->sourceEventId(
            $this->firstValue($normalized, ['source_event_id', 'event_id', 'log_id', 'record_id', 'transaction_id', 'attendance_id', 'uid']),
            $provider,
            $deviceId,
            $staffReference,
            $verifiedAt,
            $eventType,
            $row
        );

        return [
            'provider' => $this->normalizeProvider($provider),
            'device_id' => $deviceId,
            'source_event_id' => $sourceEventId,
            'event_type' => $eventType,
            'punch_type' => app(HybridAttendanceLedgerService::class)->normalizePunchType($eventType) ?? HrAttendanceLedger::PUNCH_IN,
            'staff_uuid' => $staffUuid,
            'staff_reference' => $staffReference,
            'staff_name' => $this->firstValue($normalized, ['staff_name', 'employee_name', 'name', 'user_name']),
            'email' => $this->firstValue($normalized, ['email', 'email_address']),
            'modality' => $this->normalizeModality($this->firstValue($normalized, [
                'modality',
                'biometric_type',
                'verify_type',
                'verification_type',
                'auth_type',
                'auth_method',
            ])),
            'verified_at' => $verifiedAt,
            'result' => $this->normalizeResult($resultValue),
            'score' => $this->scoreFromValue($this->firstValue($normalized, ['score', 'match_score', 'confidence'])),
            'failure_reason' => $this->firstValue($normalized, ['failure_reason', 'reason', 'message']),
            'raw' => $row,
        ];
    }

    private function alreadyImported(Organization $organization, array $event): bool
    {
        return HrBiometricVerification::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $event['provider'])
            ->where('source_event_id', $event['source_event_id'])
            ->when(
                $event['device_id'],
                fn ($query) => $query->where('device_id', $event['device_id']),
                fn ($query) => $query->whereNull('device_id')
            )
            ->exists();
    }

    private function profileForEvent(Organization $organization, array $event): ?HrBiometricProfile
    {
        if (! $event['staff_reference']) {
            return null;
        }

        $query = HrBiometricProfile::query()
            ->with('staffAssignment')
            ->where('organization_id', $organization->id)
            ->active()
            ->forModality($event['modality'])
            ->where('provider', $event['provider'])
            ->where('external_reference', $event['staff_reference']);

        if ($event['device_id']) {
            $deviceProfile = (clone $query)
                ->where('device_id', $event['device_id'])
                ->first();

            if ($deviceProfile) {
                return $deviceProfile;
            }

            return (clone $query)
                ->whereNull('device_id')
                ->first();
        }

        return $query->first();
    }

    private function staffAssignmentForEvent(Organization $organization, array $event): ?StaffAssignment
    {
        $query = StaffAssignment::query()
            ->where('organization_id', $organization->id)
            ->whereNotIn('status', ['inactive', 'orphaned']);

        if ($event['staff_uuid']) {
            $staffAssignment = (clone $query)
                ->where('staff_uuid', $event['staff_uuid'])
                ->first();

            if ($staffAssignment) {
                return $staffAssignment;
            }
        }

        if ($event['email']) {
            $staffUuid = User::query()
                ->where('email', $event['email'])
                ->value('staff_uuid');

            if ($staffUuid) {
                $staffAssignment = (clone $query)
                    ->where('staff_uuid', $staffUuid)
                    ->first();

                if ($staffAssignment) {
                    return $staffAssignment;
                }
            }
        }

        if ($event['staff_reference']) {
            $staffAssignment = (clone $query)
                ->where('staff_uuid', $event['staff_reference'])
                ->first();

            if ($staffAssignment) {
                return $staffAssignment;
            }
        }

        if ($event['staff_name']) {
            return (clone $query)
                ->whereRaw('LOWER(staff_name) = ?', [Str::lower($event['staff_name'])])
                ->first();
        }

        return null;
    }

    private function upsertDeviceProfile(
        StaffAssignment $staffAssignment,
        array $event,
        ?HrBiometricProfile $profile
    ): ?HrBiometricProfile {
        if (! $event['staff_reference']) {
            return $profile;
        }

        if (! $profile) {
            $profile = HrBiometricProfile::query()
                ->where('organization_id', $staffAssignment->organization_id)
                ->where('staff_assignment_id', $staffAssignment->id)
                ->forModality($event['modality'])
                ->where('provider', $event['provider'])
                ->where('external_reference', $event['staff_reference'])
                ->when(
                    $event['device_id'],
                    fn ($query) => $query->where('device_id', $event['device_id']),
                    fn ($query) => $query->whereNull('device_id')
                )
                ->first() ?? new HrBiometricProfile();
        }

        $profile->fill([
            'organization_id' => $staffAssignment->organization_id,
            'staff_assignment_id' => $staffAssignment->id,
            'staff_uuid' => $staffAssignment->staff_uuid,
            'staff_name' => $staffAssignment->staff_name,
            'modality' => $event['modality'],
            'label' => $profile->label ?: $this->profileLabel($event),
            'provider' => $event['provider'],
            'device_id' => $event['device_id'],
            'external_reference' => $event['staff_reference'],
            'verification_threshold' => 1.0000,
            'status' => 'active',
            'enrolled_at' => $profile->enrolled_at ?: now(),
            'metadata' => array_filter(array_merge($profile->metadata ?? [], [
                'legacy_device' => true,
                'last_source_event_id' => $event['source_event_id'],
                'last_event_type' => $event['event_type'],
            ])),
        ]);

        $profile->save();

        return $profile;
    }

    private function recordVerification(
        Organization $organization,
        array $event,
        ?StaffAssignment $staffAssignment,
        ?HrBiometricProfile $profile,
        ?User $actor
    ): HrBiometricVerification {
        $passed = $event['result'] === HrBiometricVerification::RESULT_SUCCESS && $staffAssignment !== null;
        $score = $event['score'];

        if ($score === null) {
            $score = $passed ? 1.0 : 0.0;
        }

        $failureReason = null;

        if (! $passed) {
            $failureReason = ! $staffAssignment
                ? 'Device user could not be matched to HR staff.'
                : ($event['failure_reason'] ?: 'Legacy biometric device rejected the verification.');
        }

        $verification = HrBiometricVerification::create([
            'organization_id' => $organization->id,
            'hr_biometric_profile_id' => $profile?->id,
            'staff_assignment_id' => $staffAssignment?->id,
            'staff_uuid' => $staffAssignment?->staff_uuid ?? $event['staff_uuid'] ?? $event['staff_reference'],
            'modality' => $event['modality'],
            'result' => $passed ? HrBiometricVerification::RESULT_SUCCESS : HrBiometricVerification::RESULT_FAILED,
            'score' => $score,
            'threshold' => $profile?->verification_threshold ?? 1.0000,
            'provider' => $event['provider'],
            'device_id' => $event['device_id'],
            'source_event_id' => $event['source_event_id'],
            'event_type' => $event['event_type'],
            'verified_by_user_id' => $actor?->id,
            'verified_at' => $event['verified_at'],
            'failure_reason' => $failureReason,
            'metadata' => array_filter([
                'legacy_device' => true,
                'legacy_device_user_id' => $event['staff_reference'],
                'legacy_staff_name' => $event['staff_name'],
                'legacy_status' => $event['result'],
                'raw_event' => $event['raw'],
                'imported_at' => now()->toIso8601String(),
            ]),
        ]);

        if ($passed && $profile && (! $profile->last_verified_at || $profile->last_verified_at->lt($event['verified_at']))) {
            $profile->forceFill(['last_verified_at' => $event['verified_at']])->save();
        }

        if ($passed) {
            app(HybridAttendanceLedgerService::class)->recordFromVerification($verification, $event['punch_type']);
        }

        return $verification->load(['profile', 'staffAssignment']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromDecodedJson(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        if (Arr::isAssoc($decoded)) {
            foreach (['events', 'logs', 'records', 'data'] as $key) {
                if (isset($decoded[$key]) && is_array($decoded[$key])) {
                    $decoded = $decoded[$key];
                    break;
                }
            }
        }

        if (is_array($decoded) && Arr::isAssoc($decoded)) {
            $decoded = [$decoded];
        }

        return collect($decoded)
            ->filter(fn ($row): bool => is_array($row))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromCsv(string $contents): array
    {
        $lines = preg_split('/\R/u', $contents) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            return [];
        }

        $headers = array_map('trim', str_getcsv(array_shift($lines)));

        if ($headers === []) {
            return [];
        }

        $rows = [];

        foreach ($lines as $line) {
            $values = str_getcsv($line);
            $row = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $values[$index] ?? null;
            }

            if (array_filter($row, fn ($value): bool => $value !== null && $value !== '') !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$this->key($key)] = $this->cleanScalar($value);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $keys
     */
    private function firstValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$this->key($key)] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function sourceEventId(
        ?string $sourceEventId,
        string $provider,
        ?string $deviceId,
        ?string $staffReference,
        Carbon $verifiedAt,
        ?string $eventType,
        array $row
    ): string {
        $sourceEventId = $this->cleanString($sourceEventId);

        if (! $sourceEventId) {
            $sourceEventId = 'derived:' . hash('sha256', json_encode([
                $provider,
                $deviceId,
                $staffReference,
                $verifiedAt->toIso8601String(),
                $eventType,
                $row,
            ]));
        }

        if (strlen($sourceEventId) <= 191) {
            return $sourceEventId;
        }

        return 'hash:' . hash('sha256', $sourceEventId);
    }

    private function timestampFromValue(?string $value): Carbon
    {
        if (! $value) {
            return now();
        }

        try {
            if (is_numeric($value) && strlen($value) >= 10) {
                return Carbon::createFromTimestamp((int) substr($value, 0, 10));
            }

            return Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = Str::of($provider)
            ->lower()
            ->replace([' ', '_'], '-')
            ->squish()
            ->toString();

        return $provider !== '' ? $provider : self::DEFAULT_PROVIDER;
    }

    private function normalizeModality(?string $value): string
    {
        $value = Str::lower((string) $value);

        if (str_contains($value, 'face')) {
            return HrBiometricProfile::MODALITY_FACE;
        }

        return HrBiometricProfile::MODALITY_FINGERPRINT;
    }

    private function normalizeResult(?string $value): string
    {
        $value = Str::lower((string) $value);

        if (in_array($value, ['failed', 'fail', 'failure', 'denied', 'rejected', 'reject', 'invalid', 'blocked', 'false', 'no', '0'], true)) {
            return HrBiometricVerification::RESULT_FAILED;
        }

        return HrBiometricVerification::RESULT_SUCCESS;
    }

    private function statusLooksLikeAccessResult(?string $value): bool
    {
        if (! $value) {
            return false;
        }

        return in_array(Str::lower($value), [
            'success',
            'successful',
            'granted',
            'allowed',
            'accepted',
            'verified',
            'failed',
            'fail',
            'failure',
            'denied',
            'rejected',
            'invalid',
            'blocked',
        ], true);
    }

    private function scoreFromValue(?string $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $score = (float) $value;

        if ($score > 1.0 && $score <= 100.0) {
            $score = $score / 100.0;
        }

        return max(0.0, min(1.0, $score));
    }

    private function cleanScalar(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return null;
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function key(mixed $key): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', Str::lower((string) $key));
    }

    private function profileLabel(array $event): string
    {
        $provider = Str::headline(str_replace('-', ' ', $event['provider']));

        return trim($provider . ' device user');
    }

    private function looksLikeJson(string $contents, ?string $filename): bool
    {
        $extension = $filename ? Str::lower(pathinfo($filename, PATHINFO_EXTENSION)) : null;

        return $extension === 'json' || str_starts_with($contents, '{') || str_starts_with($contents, '[');
    }
}
