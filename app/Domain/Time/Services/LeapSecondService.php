<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Exceptions\TimeEngineException;
use App\Domain\Time\Models\TimeAudit;
use Illuminate\Support\Facades\Auth;

/**
 * Leap-second inbound handling (TIME-CLK-020..023).
 */
final class LeapSecondService
{
    public function policy(): string
    {
        return (string) config('time.leap_second.policy', 'REJECT');
    }

    /**
     * @return array{accepted: bool, normalized: ?string, action: string}
     */
    public function inspect(string $rawTimestamp): array
    {
        // Detect :60 second component in common ISO / civil formats.
        if (! preg_match('/(?:T|\s)\d{2}:\d{2}:60(?:\D|$)/', $rawTimestamp)) {
            return ['accepted' => true, 'normalized' => $rawTimestamp, 'action' => 'PASS'];
        }

        $policy = $this->policy();

        return match ($policy) {
            'NORMALIZE' => [
                'accepted' => true,
                'normalized' => preg_replace('/:60/', ':59', $rawTimestamp, 1),
                'action' => 'NORMALIZED',
            ],
            'QUARANTINE' => [
                'accepted' => false,
                'normalized' => null,
                'action' => 'QUARANTINE',
            ],
            default => throw new TimeEngineException(
                'TIME_LEAP_SECOND_REJECTED',
                'Leap-second second=60 is not accepted under platform policy.',
                ['raw' => $rawTimestamp, 'policy' => $policy],
            ),
        };
    }

    public function auditRejected(string $tenantKey, string $raw, string $action): void
    {
        TimeAudit::query()->create([
            'tenant_key' => $tenantKey,
            'actor_user_id' => Auth::id(),
            'action' => 'LEAP_SECOND_'.$action,
            'object_type' => 'inbound_timestamp',
            'object_public_id' => substr(hash('sha256', $raw), 0, 26),
            'after' => ['raw' => $raw, 'policy' => $this->policy()],
            'reason' => 'Leap-second handling',
        ]);
    }
}
