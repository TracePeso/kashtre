<?php

namespace App\Domain\Time\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class IanaTimezoneId
{
    private function __construct(private readonly string $value) {}

    public static function of(string $id): self
    {
        $id = trim($id);
        if ($id === '' || ! in_array($id, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException("Invalid IANA timezone: {$id}");
        }

        return new self($id);
    }

    public static function tryOf(string $id): ?self
    {
        try {
            return self::of($id);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toDateTimeZone(): \DateTimeZone
    {
        return new \DateTimeZone($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
