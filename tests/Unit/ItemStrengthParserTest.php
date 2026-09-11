<?php

namespace Tests\Unit;

use App\Support\ItemStrengthParser;
use PHPUnit\Framework\TestCase;

class ItemStrengthParserTest extends TestCase
{
    /**
     * @dataProvider strengthProvider
     */
    public function test_it_parses_strength_from_item_names(?string $name, ?string $expected): void
    {
        $this->assertSame($expected, ItemStrengthParser::parse($name));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function strengthProvider(): array
    {
        return [
            'plain mg' => ['Axcel 400mg', '400mg'],
            'space before unit' => ['wellquine 500 mg', '500mg'],
            'grams' => ['Cefoperazone 1g', '1g'],
            'trailing words' => ['Levofloxacin 500mg Inj', '500mg'],
            'inside parentheses' => ['Rifacolon (rifaximin550mg)', '550mg'],
            'concentration ratio' => ['Danset 8mg/4ml ampuole', '8mg/4ml'],
            'per-ml concentration' => ['Sedafol 10mg/ml', '10mg/ml'],
            'percentage with w/v' => ['Troypofol (1% w/v)', '1% w/v'],
            'percent beats later volume' => ['Bupitroy 0.5% 20mls', '0.5%'],
            'decimal' => ['Naloxone Injection 0.4mg', '0.4mg'],
            'combination product' => ['Esofag D 40mg/30mg', '40mg/30mg'],
            'first of several' => ['Fentwell 100mcg 0.05mg/ml', '100mcg'],

            // A bare number has no unit, so it is not a strength — guessing "mg"
            // on a suture gauge or a pack count would be worse than null.
            'bare number' => ['Ciprobid 500', null],
            'bare number with dosage form' => ['Spamclox capsules 500', null],
            'suture gauge' => ['topcryl 3/0', null],

            // A volume alone is a pack size, not a dose.
            'volume only' => ['relcer gel 180 ml', null],
            'volume only plural' => ['Bupitroy heavy 4mls', null],

            'no strength at all' => ['Foley catheter', null],
            'dimensions are not strength' => ['gauze 10*10 cm', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    public function test_it_skips_a_leading_pack_volume_to_find_the_real_strength(): void
    {
        $this->assertSame('0.5%', ItemStrengthParser::parse('Bupitroy 20mls 0.5% heavy'));
    }
}
