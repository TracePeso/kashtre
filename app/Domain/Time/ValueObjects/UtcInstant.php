<?php

namespace App\Domain\Time\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class UtcInstant
{
    private function __construct(private readonly CarbonImmutable $value) {}

    public static function now(): self
    {
        return new self(CarbonImmutable::now('UTC'));
    }

    public static function fromString(string $iso8601): self
    {
        $dt = CarbonImmutable::parse($iso8601)->utc();

        return new self($dt);
    }

    public static function fromDateTime(DateTimeInterface $dt): self
    {
        return new self(CarbonImmutable::instance($dt)->utc());
    }

    public static function fromUnix(int $seconds, int $micros = 0): self
    {
        return new self(CarbonImmutable::createFromTimestamp($seconds, 'UTC')->micro($micros));
    }

    public function toCarbon(): CarbonImmutable
    {
        return $this->value;
    }

    public function toIso8601(): string
    {
        return $this->value->format('Y-m-d\TH:i:s.u\Z');
    }

    public function unixSeconds(): int
    {
        return $this->value->timestamp;
    }

    public function equals(self $other): bool
    {
        return $this->value->equalTo($other->value);
    }

    public function isBefore(self $other): bool
    {
        return $this->value->lt($other->value);
    }

    public function isAfter(self $other): bool
    {
        return $this->value->gt($other->value);
    }
}
