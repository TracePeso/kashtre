<?php

namespace Tests\Unit\Units;

use App\Domain\Units\Services\BcMathDecimalMath;
use PHPUnit\Framework\TestCase;

class BcMathDecimalMathTest extends TestCase
{
    public function test_multiply_and_round_half_up(): void
    {
        $math = new BcMathDecimalMath();

        $this->assertSame('117.00', $math->round($math->multiply('6.5', '18', 8), 2, 'HALF_UP'));
        $this->assertSame('32.00', $math->add($math->multiply('0', '1.8', 8), '32', 2));
    }

    public function test_divide_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new BcMathDecimalMath())->divide('1', '0', 4);
    }
}
