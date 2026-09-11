<?php

namespace App\Domain\Time\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class LocalDate
{
    private function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {}

    public static function of(int $y, int $m, int $d): self
    {
        if (! checkdate($m, $d, $y)) {
            throw new InvalidArgumentException('Invalid local date.');
        }

        return new self($y, $m, $d);
    }

    public static function parse(string $value): self
    {
        $dt = CarbonImmutable::parse($value);

        return self::of((int) $dt->year, (int) $dt->month, (int) $dt->day);
    }

    public function toString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    public function addDays(int $days): self
    {
        $dt = CarbonImmutable::create($this->year, $this->month, $this->day, 0, 0, 0, 'UTC')
            ->addDays($days);

        return self::of((int) $dt->year, (int) $dt->month, (int) $dt->day);
    }
}
