<?php

namespace App\Domain\Units\Services;

use App\Domain\Units\Contracts\DecimalMath;
use InvalidArgumentException;
use RuntimeException;

/**
 * Exact-decimal math via BCMath (no PHP floats).
 */
final class BcMathDecimalMath implements DecimalMath
{
    public function multiply(string $a, string $b, int $scale): string
    {
        return $this->normalize(bcmul($this->assertDecimal($a), $this->assertDecimal($b), $scale + 4), $scale, 'HALF_UP');
    }

    public function add(string $a, string $b, int $scale): string
    {
        return $this->normalize(bcadd($this->assertDecimal($a), $this->assertDecimal($b), $scale + 4), $scale, 'HALF_UP');
    }

    public function divide(string $a, string $b, int $scale): string
    {
        if (bccomp($this->assertDecimal($b), '0', $scale + 4) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return $this->normalize(bcdiv($this->assertDecimal($a), $this->assertDecimal($b), $scale + 4), $scale, 'HALF_UP');
    }

    public function compare(string $a, string $b): int
    {
        return bccomp($this->assertDecimal($a), $this->assertDecimal($b), 18);
    }

    public function pow(string $base, string $exponent, int $scale): string
    {
        $exp = (int) $this->assertDecimal($exponent);
        if ($exp === 0) {
            return '1';
        }

        if ($exp < 0) {
            $positive = $this->pow($base, (string) abs($exp), $scale);

            return $this->divide('1', $positive, $scale);
        }

        $result = '1';
        for ($i = 0; $i < $exp; $i++) {
            $result = $this->multiply($result, $base, $scale);
        }

        return $result;
    }

    public function round(string $value, int $scale, string $mode): string
    {
        return $this->normalize($this->assertDecimal($value), $scale, $mode);
    }

    private function assertDecimal(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Value must be a canonical decimal string.');
        }

        return $value;
    }

    private function normalize(string $value, int $scale, string $mode): string
    {
        if (! function_exists('bcadd')) {
            throw new RuntimeException('BCMath extension is required for the Shared Unit Engine.');
        }

        $mode = strtoupper($mode);
        if ($mode !== 'HALF_UP' && $mode !== 'DOWN' && $mode !== 'UP') {
            $mode = 'HALF_UP';
        }

        // bcround-like half-up using integer trick
        $factor = bcpow('10', (string) $scale, 0);
        $scaled = bcmul($value, $factor, $scale + 4);

        if ($mode === 'DOWN') {
            $rounded = $this->truncateTowardZero($scaled);
        } elseif ($mode === 'UP') {
            $rounded = $this->roundAwayFromZero($scaled);
        } else {
            $rounded = $this->roundHalfUp($scaled);
        }

        return bcdiv($rounded, $factor, $scale);
    }

    private function roundHalfUp(string $scaled): string
    {
        $negative = str_starts_with($scaled, '-');
        $abs = ltrim($scaled, '-');
        $parts = explode('.', $abs, 2);
        $int = $parts[0];
        $frac = $parts[1] ?? '0';

        if ($frac !== '' && (int) $frac[0] >= 5) {
            $int = bcadd($int, '1', 0);
        }

        return $negative ? '-'.$int : $int;
    }

    private function truncateTowardZero(string $scaled): string
    {
        $negative = str_starts_with($scaled, '-');
        $abs = explode('.', ltrim($scaled, '-'), 2)[0];

        return $negative ? '-'.$abs : $abs;
    }

    private function roundAwayFromZero(string $scaled): string
    {
        $negative = str_starts_with($scaled, '-');
        $abs = ltrim($scaled, '-');
        $parts = explode('.', $abs, 2);
        $int = $parts[0];
        $frac = rtrim($parts[1] ?? '', '0');

        if ($frac !== '') {
            $int = bcadd($int, '1', 0);
        }

        return $negative ? '-'.$int : $int;
    }
}
