<?php

namespace App\Domain\Time\Exceptions;

use RuntimeException;

final class TimeEngineException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidTimezone(string $id): self
    {
        return new self('TIME_ZONE_INVALID', 'Timezone identifier is not recognized.', ['ianaId' => $id]);
    }

    public static function policyNotFound(string $context): self
    {
        return new self('TIME_POLICY_NOT_FOUND', 'No applicable timezone policy was found.', ['context' => $context]);
    }

    public static function policyOverlap(string $context): self
    {
        return new self('TIME_POLICY_OVERLAP', 'More than one timezone policy applies.', ['context' => $context]);
    }

    public static function clockUnhealthy(string $status): self
    {
        return new self('TIME_CLOCK_UNHEALTHY', 'Trusted clock is not healthy.', ['status' => $status]);
    }

    public static function localAmbiguous(string $local, string $iana): self
    {
        return new self('TIME_LOCAL_AMBIGUOUS', 'Local time maps to more than one instant.', [
            'local' => $local,
            'ianaId' => $iana,
        ]);
    }

    public static function localNonexistent(string $local, string $iana): self
    {
        return new self('TIME_LOCAL_NONEXISTENT', 'Local time falls in a civil-time gap.', [
            'local' => $local,
            'ianaId' => $iana,
        ]);
    }

    public static function periodClosed(string $code): self
    {
        return new self('TIME_PERIOD_CLOSED', 'Financial period is closed.', ['code' => $code]);
    }

    public static function quarantine(string $reason): self
    {
        return new self('TIME_DEVICE_QUARANTINED', $reason);
    }
}
