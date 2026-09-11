<?php

namespace App\Domain\Time\Services;

use App\Domain\Time\Contracts\Clock;
use App\Domain\Time\ValueObjects\UtcInstant;

final class SystemClock implements Clock
{
    public function now(): UtcInstant
    {
        return UtcInstant::now();
    }
}
