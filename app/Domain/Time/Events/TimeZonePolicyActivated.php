<?php

namespace App\Domain\Time\Events;

use App\Domain\Time\Models\TimeZonePolicy;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TimeZonePolicyActivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly TimeZonePolicy $policy,
        public readonly string $correlationId,
    ) {}
}
