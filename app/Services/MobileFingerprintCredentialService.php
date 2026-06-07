<?php

namespace App\Services;

use App\Models\HrBiometricProfile;
use App\Models\Organization;
use App\Models\StaffAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class MobileFingerprintCredentialService
{
    private const SESSION_PREFIX = 'hr_biometrics.mobile_fingerprint';
    private const CHALLENGE_TTL_MINUTES = 5;

    public function options(Request $request, Organization $organization, array $data): array
    {
        $action = $data['action'] ?? 'verify';
        $rpId = $this->rpId($request);
        $challenge = $this->base64UrlEncode(random_bytes(32));
        $sessionPayload = [
            'action' => $action,
            'challenge' => $challenge,
            'organization_id' => $organization->id,
            'rp_id' => $rpId,
            'origin' => $this->origin($request),
            'staff_assignment_id' => $data['staff_assignment_id'] ?? null,
            'staff_assignment_ids' => $data['staff_assignment_ids'] ?? null,
            'profile_uuid' => $data['profile_uuid'] ?? null,
            'profile_uuids' => $data['profile_uuids'] ?? null,
            'expires_at' => now()->addMinutes(self::CHALLENGE_TTL_MINUTES)->toIso8601String(),
        ];

        $request->session()->put($this->sessionKey($action), $sessionPayload);

        if ($action === 'enroll') {
            $staffAssignment = StaffAssignment::query()
                ->where('organization_id', $organization->id)
                ->whereKey($data['staff_assignment_id'] ?? null)
                ->firstOrFail();

            $existingProfiles = $staffAssignment->biometricProfiles()
                ->active()
                ->forModality(HrBiometricProfile::MODALITY_FINGERPRINT)
                ->where('provider', 'mobile-webauthn')
                ->whereNotNull('external_reference')
                ->get();

            return [
                'publicKey' => [
                    'challenge' => $challenge,
                    'rp' => [
                        'name' => config('app.name', 'Kashtre HR'),
                        'id' => $rpId,
                    ],
                    'user' => [
                        'id' => $this->base64UrlEncode($staffAssignment->staff_uuid),
                        'name' => $staffAssignment->staff_uuid,
                        'displayName' => $staffAssignment->staff_name,
                    ],
                    'pubKeyCredParams' => [
                        ['type' => 'public-key', 'alg' => -7],
                    ],
                    'authenticatorSelection' => [
                        'authenticatorAttachment' => 'platform',
                        'residentKey' => 'preferred',
                        'userVerification' => 'required',
                    ],
                    'timeout' => 60000,
                    'attestation' => 'none',
                    'excludeCredentials' => $existingProfiles
                        ->map(fn (HrBiometricProfile $profile): array => [
                            'type' => 'public-key',
                            'id' => $profile->external_reference,
                        ])
                        ->values()
                        ->all(),
                ],
            ];
        }

        $profiles = HrBiometricProfile::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->forModality(HrBiometricProfile::MODALITY_FINGERPRINT)
            ->where('provider', 'mobile-webauthn')
            ->whereNotNull('external_reference')
            ->when(! empty($data['staff_assignment_id']), fn ($query) => $query->where('staff_assignment_id', $data['staff_assignment_id']))
            ->when(! empty($data['staff_assignment_ids']) && is_array($data['staff_assignment_ids']), fn ($query) => $query->whereIn('staff_assignment_id', $data['staff_assignment_ids']))
            ->when(! empty($data['profile_uuid']), fn ($query) => $query->where('uuid', $data['profile_uuid']))
            ->when(! empty($data['profile_uuids']) && is_array($data['profile_uuids']), fn ($query) => $query->whereIn('uuid', $data['profile_uuids']))
            ->get();

        return [
            'publicKey' => [
                'challenge' => $challenge,
                'rpId' => $rpId,
                'timeout' => 60000,
                'userVerification' => 'required',
                'allowCredentials' => $profiles
                    ->map(fn (HrBiometricProfile $profile): array => [
                        'type' => 'public-key',
                        'id' => $profile->external_reference,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function enroll(Request $request, StaffAssignment $staffAssignment, array $payload): array
    {
        $state = $this->consumeState($request, 'enroll');

        if ((int) ($state['organization_id'] ?? 0) !== (int) $staffAssignment->organization_id) {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'This fingerprint registration was started for another organization.',
            ]);
        }

        if ((int) ($state['staff_assignment_id'] ?? 0) !== (int) $staffAssignment->id) {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'This fingerprint registration was started for another staff member.',
            ]);
        }

        $credential = $this->decodeJsonPayload($payload['fingerprint_credential'] ?? null, 'fingerprint_credential');
        $clientDataBytes = $this->base64UrlDecode($credential['response']['clientDataJSON'] ?? null, 'fingerprint_credential');
        $clientData = $this->decodeClientData($clientDataBytes, 'webauthn.create', $state, 'fingerprint_credential');
        $attestationBytes = $this->base64UrlDecode($credential['response']['attestationObject'] ?? null, 'fingerprint_credential');

        try {
            $offset = 0;
            $attestation = $this->cborDecode($attestationBytes, $offset);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'The fingerprint registration payload was not readable.',
            ]);
        }

        if (! is_array($attestation) || ! is_string($attestation['authData'] ?? null)) {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'The fingerprint registration response was incomplete.',
            ]);
        }

        $authData = $this->parseAttestedCredentialData($attestation['authData'], (string) $state['rp_id']);
        $credentialId = $this->base64UrlEncode($authData['credential_id']);

        if (! hash_equals($credentialId, (string) ($credential['id'] ?? ''))) {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'The fingerprint credential ID did not match the registration response.',
            ]);
        }

        return [
            'credential_id' => $credentialId,
            'public_key_cose' => $this->base64UrlEncode($authData['public_key_cose']),
            'public_key_pem' => $this->coseKeyToPem($authData['public_key']),
            'sign_count' => $authData['sign_count'],
            'origin' => $clientData['origin'],
            'rp_id' => $state['rp_id'],
            'transports' => $credential['response']['transports'] ?? [],
            'registered_at' => now()->toIso8601String(),
        ];
    }

    public function verify(Request $request, Organization $organization, array $payload): HrBiometricProfile
    {
        $state = $this->consumeState($request, 'verify');

        if ((int) ($state['organization_id'] ?? 0) !== (int) $organization->id) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'This fingerprint check was started for another organization.',
            ]);
        }

        $assertion = $this->decodeJsonPayload($payload['fingerprint_assertion'] ?? null, 'fingerprint_assertion');
        $credentialId = (string) ($assertion['id'] ?? '');

        if ($credentialId === '') {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'The fingerprint check was missing a credential ID.',
            ]);
        }

        $profile = HrBiometricProfile::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->forModality(HrBiometricProfile::MODALITY_FINGERPRINT)
            ->where('provider', 'mobile-webauthn')
            ->where('external_reference', $credentialId)
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'This fingerprint is not enrolled for any active staff member.',
            ]);
        }

        if (! empty($state['staff_assignment_id']) && (int) $state['staff_assignment_id'] !== (int) $profile->staff_assignment_id) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'This fingerprint belongs to a different staff member.',
            ]);
        }

        if (! empty($state['staff_assignment_ids']) && is_array($state['staff_assignment_ids']) && ! in_array((int) $profile->staff_assignment_id, array_map('intval', $state['staff_assignment_ids']), true)) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'This fingerprint belongs to a different staff member.',
            ]);
        }

        if (! empty($state['profile_uuid']) && ! hash_equals((string) $state['profile_uuid'], $profile->uuid)) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'This fingerprint belongs to a different biometric profile.',
            ]);
        }

        if (! empty($state['profile_uuids']) && is_array($state['profile_uuids']) && ! in_array((string) $profile->uuid, array_map('strval', $state['profile_uuids']), true)) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'This fingerprint belongs to a different biometric profile.',
            ]);
        }

        $storedCredential = json_decode((string) $profile->template_payload, true);

        if (! is_array($storedCredential) || ! is_string($storedCredential['public_key_pem'] ?? null)) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'This fingerprint profile is missing its verification key.',
            ]);
        }

        $clientDataBytes = $this->base64UrlDecode($assertion['response']['clientDataJSON'] ?? null, 'fingerprint_assertion');
        $this->decodeClientData($clientDataBytes, 'webauthn.get', $state, 'fingerprint_assertion');
        $authenticatorData = $this->base64UrlDecode($assertion['response']['authenticatorData'] ?? null, 'fingerprint_assertion');
        $signature = $this->base64UrlDecode($assertion['response']['signature'] ?? null, 'fingerprint_assertion');
        $authData = $this->parseAuthenticatorData($authenticatorData, (string) $state['rp_id']);
        $signedData = $authenticatorData . hash('sha256', $clientDataBytes, true);
        $verified = openssl_verify($signedData, $signature, $storedCredential['public_key_pem'], OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'The fingerprint signature could not be verified.',
            ]);
        }

        if (($authData['sign_count'] ?? 0) > 0) {
            $storedCredential['sign_count'] = max((int) ($storedCredential['sign_count'] ?? 0), (int) $authData['sign_count']);
            $profile->forceFill(['template_payload' => json_encode($storedCredential)])->save();
        }

        return $profile;
    }

    private function consumeState(Request $request, string $action): array
    {
        $key = $this->sessionKey($action);
        $state = $request->session()->pull($key);

        if (! is_array($state)) {
            throw ValidationException::withMessages([
                $action === 'enroll' ? 'fingerprint_credential' : 'fingerprint_assertion' => 'Start the fingerprint check again.',
            ]);
        }

        if (Carbon::parse($state['expires_at'] ?? now()->subMinute())->isPast()) {
            throw ValidationException::withMessages([
                $action === 'enroll' ? 'fingerprint_credential' : 'fingerprint_assertion' => 'The fingerprint check expired. Start again.',
            ]);
        }

        return $state;
    }

    private function decodeJsonPayload(mixed $value, string $field): array
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([$field => 'The fingerprint response is required.']);
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([$field => 'The fingerprint response was not valid JSON.']);
        }

        return $decoded;
    }

    private function decodeClientData(string $clientDataBytes, string $expectedType, array $state, string $field): array
    {
        $clientData = json_decode($clientDataBytes, true);

        if (! is_array($clientData)) {
            throw ValidationException::withMessages([$field => 'The fingerprint client response was invalid.']);
        }

        if (($clientData['type'] ?? null) !== $expectedType) {
            throw ValidationException::withMessages([$field => 'The fingerprint response had the wrong operation type.']);
        }

        if (! hash_equals((string) $state['challenge'], (string) ($clientData['challenge'] ?? ''))) {
            throw ValidationException::withMessages([$field => 'The fingerprint challenge did not match.']);
        }

        if (! hash_equals((string) $state['origin'], (string) ($clientData['origin'] ?? ''))) {
            throw ValidationException::withMessages([$field => 'The fingerprint response came from the wrong origin.']);
        }

        return $clientData;
    }

    private function parseAttestedCredentialData(string $authenticatorData, string $rpId): array
    {
        $authData = $this->parseAuthenticatorData($authenticatorData, $rpId);

        if (($authData['flags'] & 0x40) !== 0x40) {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'The fingerprint registration did not include a credential key.',
            ]);
        }

        $offset = 37;
        $aaguid = substr($authenticatorData, $offset, 16);
        $offset += 16;
        $credentialIdLength = unpack('n', substr($authenticatorData, $offset, 2))[1] ?? 0;
        $offset += 2;
        $credentialId = substr($authenticatorData, $offset, $credentialIdLength);
        $offset += $credentialIdLength;
        $publicKeyCose = substr($authenticatorData, $offset);

        try {
            $coseOffset = 0;
            $publicKey = $this->cborDecode($publicKeyCose, $coseOffset);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'The fingerprint public key was not readable.',
            ]);
        }

        if (! is_array($publicKey) || $credentialId === '') {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'The fingerprint credential was not readable.',
            ]);
        }

        return $authData + [
            'aaguid' => $aaguid,
            'credential_id' => $credentialId,
            'public_key_cose' => substr($publicKeyCose, 0, $coseOffset),
            'public_key' => $publicKey,
        ];
    }

    private function parseAuthenticatorData(string $authenticatorData, string $rpId): array
    {
        if (strlen($authenticatorData) < 37) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'The fingerprint authenticator data was incomplete.',
            ]);
        }

        $rpIdHash = substr($authenticatorData, 0, 32);

        if (! hash_equals(hash('sha256', $rpId, true), $rpIdHash)) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'This fingerprint was created for another site.',
            ]);
        }

        $flags = ord($authenticatorData[32]);

        if (($flags & 0x01) !== 0x01 || ($flags & 0x04) !== 0x04) {
            throw ValidationException::withMessages([
                'fingerprint_assertion' => 'The phone did not confirm biometric user verification.',
            ]);
        }

        return [
            'flags' => $flags,
            'sign_count' => unpack('N', substr($authenticatorData, 33, 4))[1] ?? 0,
        ];
    }

    private function coseKeyToPem(array $key): string
    {
        $keyType = $key[1] ?? null;
        $algorithm = $key[3] ?? null;
        $curve = $key[-1] ?? null;
        $x = $key[-2] ?? null;
        $y = $key[-3] ?? null;

        if ($keyType !== 2 || $algorithm !== -7 || $curve !== 1 || ! is_string($x) || ! is_string($y)) {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'Only fingerprint credentials supported by this system can be used.',
            ]);
        }

        if (strlen($x) !== 32 || strlen($y) !== 32) {
            throw ValidationException::withMessages([
                'fingerprint_credential' => 'The fingerprint public key format is not supported.',
            ]);
        }

        $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function cborDecode(string $bytes, int &$offset): mixed
    {
        if ($offset >= strlen($bytes)) {
            throw new \UnexpectedValueException('Unexpected end of CBOR payload.');
        }

        $initial = ord($bytes[$offset++]);
        $major = $initial >> 5;
        $additional = $initial & 0x1f;
        $length = $this->cborLength($bytes, $offset, $additional);

        return match ($major) {
            0 => $length,
            1 => -1 - $length,
            2 => $this->cborBytes($bytes, $offset, $length),
            3 => $this->cborText($bytes, $offset, $length),
            4 => $this->cborArray($bytes, $offset, $length),
            5 => $this->cborMap($bytes, $offset, $length),
            7 => $this->cborSimple($additional, $length),
            default => throw new \UnexpectedValueException('Unsupported CBOR major type.'),
        };
    }

    private function cborLength(string $bytes, int &$offset, int $additional): int
    {
        if ($additional < 24) {
            return $additional;
        }

        if ($additional === 24) {
            return ord($bytes[$offset++]);
        }

        if ($additional === 25) {
            $value = unpack('n', substr($bytes, $offset, 2))[1];
            $offset += 2;

            return $value;
        }

        if ($additional === 26) {
            $value = unpack('N', substr($bytes, $offset, 4))[1];
            $offset += 4;

            return $value;
        }

        throw new \UnexpectedValueException('Unsupported CBOR length.');
    }

    private function cborBytes(string $bytes, int &$offset, int $length): string
    {
        $value = substr($bytes, $offset, $length);
        $offset += $length;

        return $value;
    }

    private function cborText(string $bytes, int &$offset, int $length): string
    {
        return $this->cborBytes($bytes, $offset, $length);
    }

    private function cborArray(string $bytes, int &$offset, int $length): array
    {
        $values = [];

        for ($index = 0; $index < $length; $index++) {
            $values[] = $this->cborDecode($bytes, $offset);
        }

        return $values;
    }

    private function cborMap(string $bytes, int &$offset, int $length): array
    {
        $values = [];

        for ($index = 0; $index < $length; $index++) {
            $key = $this->cborDecode($bytes, $offset);
            $values[$key] = $this->cborDecode($bytes, $offset);
        }

        return $values;
    }

    private function cborSimple(int $additional, int $length): mixed
    {
        return match ($additional) {
            20 => false,
            21 => true,
            22 => null,
            default => $length,
        };
    }

    private function rpId(Request $request): string
    {
        return $request->getHost();
    }

    private function origin(Request $request): string
    {
        return $request->getSchemeAndHttpHost();
    }

    private function sessionKey(string $action): string
    {
        return self::SESSION_PREFIX . '.' . $action;
    }

    private function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function base64UrlDecode(mixed $value, string $field): string
    {
        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages([$field => 'The fingerprint response was missing encoded data.']);
        }

        $decoded = base64_decode(strtr($value . str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/'), true);

        if ($decoded === false) {
            throw ValidationException::withMessages([$field => 'The fingerprint response contained invalid encoded data.']);
        }

        return $decoded;
    }
}
