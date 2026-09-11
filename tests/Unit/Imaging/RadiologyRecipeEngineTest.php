<?php

namespace Tests\Unit\Imaging;

use App\Models\ImagingStudy;
use App\Services\Imaging\RadiologyRecipeEngine;
use Tests\TestCase;

class RadiologyRecipeEngineTest extends TestCase
{
    public function test_deplete_for_study_is_a_no_op_when_the_protocol_has_no_recipe(): void
    {
        // CHEST-XRAY has an empty consumables_recipe (seeded) — the engine
        // should return immediately without attempting any DB writes.
        $study = new ImagingStudy([
            'business_id' => 1,
            'protocol_code' => 'CHEST-XRAY',
        ]);

        $engine = app(RadiologyRecipeEngine::class);

        // No exception, no attempted ImagingConsumption::create() on an
        // unsaved (id-less) study — would violate the NOT NULL FK if reached.
        $engine->depleteForStudy($study, 1);

        $this->assertTrue(true);
    }

    public function test_deplete_for_study_is_a_no_op_when_the_protocol_code_does_not_resolve(): void
    {
        $study = new ImagingStudy([
            'business_id' => 1,
            'protocol_code' => 'DOES-NOT-EXIST',
        ]);

        $engine = app(RadiologyRecipeEngine::class);
        $engine->depleteForStudy($study, 1);

        $this->assertTrue(true);
    }
}
