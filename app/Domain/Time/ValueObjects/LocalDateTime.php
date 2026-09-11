<?php

namespace App\Domain\Time\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Civil (wall-clock) datetime without timezone — year/month/day/hour/minute/second.
 */
final class LocalDateTime
{
    private function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
        public readonly int $hour,
        public readonly int $minute,
        public readonly int $second,
        public readonly int $microsecond = 0,
    ) {}

    public static function of(int $y, int $m, int $d, int $h = 0, int $i = 0, int $s = 0, int $u = 0): self
    {
        if (! checkdate($m, $d, $y)) {
            throw new InvalidArgumentException('Invalid civil date.');
        }
        if ($h < 0 || $h > 23 || $i < 0 || $i > 59 || $s < 0 || $s > 59 || $u < 0 || $u > 999999) {
            throw new InvalidArgumentException('Invalid civil time.');
        }

        return new self($y, $m, $d, $h, $i, $s, $u);
    }

    public static function parse(string $value): self
    {
        $dt = CarbonImmutable::parse($value);

        return self::of(
            (int) $dt->year,
            (int) $dt->month,
            (int) $dt->day,
            (int) $dt->hour,
            (int) $dt->minute,
            (int) $dt->second,
            (int) $dt->micro,
        );
    }

    public static function fromCarbon(CarbonImmutable $dt): self
    {
        return self::of(
            (int) $dt->year,
            (int) $dt->month,
            (int) $dt->day,
            (int) $dt->hour,
            (int) $dt->minute,
            (int) $dt->second,
            (int) $dt->micro,
        );
    }

    public function toString(): string
    {
        return sprintf(
            '%04d-%02d-%02dT%02d:%02d:%02d.%06d',
            $this->year,
            $this->month,
            $this->day,
            $this->hour,
            $this->minute,
            $this->second,
            $this->microsecond,
        );
    }

    public function dateString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }
}
