<?php

namespace App\Services;

use App\Models\HrStaffUnavailability;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BiometricNetworkPolicy
{
    public function __construct(
        private readonly BiometricAttendanceBypassService $attendanceBypass
    ) {
    }

    /**
     * @param array{allow_offsite_bypass?: bool} $options
     */
    public function assertAllowed(Request $request, Organization $organization, array $options = []): void
    {
        if (! $organization->biometric_network_restriction_enabled) {
            return;
        }

        if (($options['allow_offsite_bypass'] ?? false) && $this->applyOffsiteBypass($request, $organization)) {
            return;
        }

        $ip = $request->ip();
        $entries = $this->normalizeNetworkEntries($organization->biometric_allowed_networks ?? []);
        $networks = $this->networksFromEntries($entries);

        if ($ip && $this->ipAllowed($ip, $networks)) {
            return;
        }

        throw ValidationException::withMessages([
            'office_network' => $this->deniedMessage($ip, $networks),
        ]);
    }

    /**
     * @return array{enabled: bool, allowed: bool, ip: ?string, allowed_networks: array<int, string>, allowed_network_entries: array<int, array{network: string, name: ?string, service_provider: ?string}>, matched_network: ?string, matched_network_name: ?string, matched_service_provider: ?string, message: string}
     */
    public function status(Request $request, ?Organization $organization): array
    {
        $ip = $request->ip();

        if (! $organization || ! $organization->biometric_network_restriction_enabled) {
            return [
                'enabled' => false,
                'allowed' => true,
                'ip' => $ip,
                'allowed_networks' => [],
                'allowed_network_entries' => [],
                'matched_network' => null,
                'matched_network_name' => null,
                'matched_service_provider' => null,
                'message' => 'Office network restriction is off.',
            ];
        }

        $entries = $this->normalizeNetworkEntries($organization->biometric_allowed_networks ?? []);
        $networks = $this->networksFromEntries($entries);
        $matchedNetwork = $ip ? $this->matchedNetwork($ip, $networks) : null;
        $matchedEntry = $matchedNetwork ? $this->entryForNetwork($entries, $matchedNetwork) : null;

        return [
            'enabled' => true,
            'allowed' => $matchedNetwork !== null,
            'ip' => $ip,
            'allowed_networks' => $networks,
            'allowed_network_entries' => $entries,
            'matched_network' => $matchedNetwork,
            'matched_network_name' => $matchedEntry['name'] ?? null,
            'matched_service_provider' => $matchedEntry['service_provider'] ?? null,
            'message' => $matchedNetwork
                ? 'Current request is on ' . $this->networkLabel($matchedEntry, $matchedNetwork) . '.'
                : $this->deniedMessage($ip, $networks),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function normalizeNetworks(mixed $value): array
    {
        return $this->networksFromEntries($this->normalizeNetworkEntries($value));
    }

    /**
     * @return array<int, array{network: string, name: ?string, service_provider: ?string}>
     */
    public function normalizeNetworkEntries(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $entries = [];

        foreach ($value as $entry) {
            $name = null;
            $serviceProvider = null;
            $network = null;

            if (is_array($entry)) {
                $network = $entry['network'] ?? $entry['ip'] ?? $entry['address'] ?? null;
                $name = $this->cleanString($entry['name'] ?? null);
                $serviceProvider = $this->cleanString($entry['service_provider'] ?? $entry['provider'] ?? null);
            } elseif (is_string($entry)) {
                $network = $entry;
            }

            if (! is_string($network)) {
                continue;
            }

            $network = $this->cleanString($network);

            if ($network === '') {
                continue;
            }

            $entries[$network] = [
                'network' => $network,
                'name' => $name,
                'service_provider' => $serviceProvider,
            ];
        }

        return array_values($entries);
    }

    /**
     * @param array<int, array{network: string, name: ?string, service_provider: ?string}> $entries
     * @return array<int, string>
     */
    public function networksFromEntries(array $entries): array
    {
        return array_values(array_unique(array_map(
            fn (array $entry): string => $entry['network'],
            $entries
        )));
    }

    /**
     * @param array<int, string> $networks
     * @return array<int, string>
     */
    public function invalidNetworks(array $networks): array
    {
        return array_values(array_filter(
            $networks,
            fn (string $network): bool => ! $this->isValidNetwork($network)
        ));
    }

    /**
     * @param array<int, string> $networks
     */
    public function ipAllowed(string $ip, array $networks): bool
    {
        return $this->matchedNetwork($ip, $networks) !== null;
    }

    /**
     * @param array<int, string> $networks
     */
    private function matchedNetwork(string $ip, array $networks): ?string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $ipBytes = inet_pton($ip);

        foreach ($networks as $network) {
            if (! $this->isValidNetwork($network)) {
                continue;
            }

            if (! str_contains($network, '/') && inet_pton($network) === $ipBytes) {
                return $network;
            }

            if (str_contains($network, '/') && $this->ipMatchesCidr($ip, $network)) {
                return $network;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{network: string, name: ?string, service_provider: ?string}> $entries
     * @return array{network: string, name: ?string, service_provider: ?string}|null
     */
    private function entryForNetwork(array $entries, string $network): ?array
    {
        foreach ($entries as $entry) {
            if (($entry['network'] ?? null) === $network) {
                return $entry;
            }
        }

        return null;
    }

    private function isValidNetwork(string $network): bool
    {
        if (! str_contains($network, '/')) {
            return filter_var($network, FILTER_VALIDATE_IP) !== false;
        }

        [$address, $prefix] = explode('/', $network, 2);

        if (filter_var($address, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefix)) {
            return false;
        }

        $maxPrefix = str_contains($address, ':') ? 128 : 32;
        $prefix = (int) $prefix;

        return $prefix >= 0 && $prefix <= $maxPrefix;
    }

    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        [$range, $prefix] = explode('/', $cidr, 2);
        $ipBytes = inet_pton($ip);
        $rangeBytes = inet_pton($range);

        if ($ipBytes === false || $rangeBytes === false || strlen($ipBytes) !== strlen($rangeBytes)) {
            return false;
        }

        $prefix = (int) $prefix;
        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBytes, 0, $fullBytes) !== substr($rangeBytes, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($ipBytes[$fullBytes]) & $mask) === (ord($rangeBytes[$fullBytes]) & $mask);
    }

    /**
     * @param array<int, string> $networks
     */
    private function deniedMessage(?string $ip, array $networks): string
    {
        if ($networks === []) {
            return 'Biometrics require the office network, but no office networks are configured.';
        }

        $ipLabel = $ip ?: 'unknown IP';

        return "Biometrics require the office network. Current request IP {$ipLabel} is not allowed.";
    }

    /**
     * @param array{network: string, name: ?string, service_provider: ?string}|null $entry
     */
    private function networkLabel(?array $entry, string $network): string
    {
        $parts = array_filter([
            $entry['name'] ?? null,
            $entry['service_provider'] ?? null,
        ]);

        if ($parts === []) {
            return $network;
        }

        return implode(' / ', $parts) . " ({$network})";
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function applyOffsiteBypass(Request $request, Organization $organization): bool
    {
        $unavailability = $this->attendanceBypass->approvedOffsiteDutyForRequest($request, $organization);

        if (! $unavailability instanceof HrStaffUnavailability || ! $unavailability->allowsAttendanceBypass()) {
            return false;
        }

        $request->attributes->set('biometric_access_bypass', [
            'type' => 'offsite_duty',
            'source' => 'office_network',
            'title' => $unavailability->title ?: 'Official Workshop/Meeting',
            'staff_assignment_id' => $unavailability->staff_assignment_id,
            'approval_request_id' => $unavailability->approval_request_id,
            'message' => 'Approved Official Workshop/Meeting bypassed the office network restriction.',
        ]);

        return true;
    }
}
