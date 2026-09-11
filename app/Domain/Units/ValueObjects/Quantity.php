<?php

namespace App\Domain\Units\ValueObjects;

use InvalidArgumentException;

final readonly class Quantity
{
    public function __construct(
        public string $value,
        public string $unitPublicId,
    ) {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Value must be a canonical decimal string.');
        }
    }
}
