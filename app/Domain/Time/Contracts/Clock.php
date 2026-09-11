<?php

namespace App\Domain\Time\Contracts;

use App\Domain\Time\ValueObjects\UtcInstant;

interface Clock
{
    public function now(): UtcInstant;
}
