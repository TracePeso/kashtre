<?php

namespace App\Domain\Units\Contracts;

interface DecimalMath
{
    public function multiply(string $a, string $b, int $scale): string;

    public function add(string $a, string $b, int $scale): string;

    public function divide(string $a, string $b, int $scale): string;

    public function compare(string $a, string $b): int;

    /** Raise $base to an integer power (exponent as decimal integer string). */
    public function pow(string $base, string $exponent, int $scale): string;

    public function round(string $value, int $scale, string $mode): string;
}
