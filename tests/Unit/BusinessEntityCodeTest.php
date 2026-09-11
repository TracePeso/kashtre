<?php

namespace Tests\Unit;

use App\Support\BusinessEntityCode;
use PHPUnit\Framework\TestCase;

/**
 * Uniqueness needs the database, so it is exercised against the real table
 * elsewhere; these pin the derivation itself, which is pure.
 */
class BusinessEntityCodeTest extends TestCase
{
    /**
     * @dataProvider nameProvider
     */
    public function test_it_derives_a_code_from_the_business_name(string $name, string $expected): void
    {
        // No DB in a unit test, so exercise the private derivation directly.
        $base = (new \ReflectionClass(BusinessEntityCode::class))->getMethod('base');
        $base->setAccessible(true);

        $this->assertSame($expected, $base->invoke(null, $name));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nameProvider(): array
    {
        return [
            'initials of each word' => ['Kashtre Community Hospital', 'KCH'],
            'three words' => ['Exquisite Test Life', 'ETL'],
            'single word takes opening letters' => ['Kashtre', 'KASH'],
            'punctuation is not a word' => ['St. Mary\'s Hospital', 'SMH'],
            'digits survive' => ['Clinic 24 Seven', 'C2S'],
            'lowercase is upper-cased' => ['city health clinic', 'CHC'],
            'extra whitespace ignored' => ['  Nakawa   General  ', 'NG'],
            'unnamed falls back' => ['', 'ENTITY'],
            'symbols only falls back' => ['---', 'ENTITY'],
        ];
    }

    public function test_it_caps_the_code_length(): void
    {
        $base = (new \ReflectionClass(BusinessEntityCode::class))->getMethod('base');
        $base->setAccessible(true);

        $name = 'A B C D E F G H I J K L M N O P Q R S T';

        $this->assertLessThanOrEqual(16, strlen($base->invoke(null, $name)));
    }
}
