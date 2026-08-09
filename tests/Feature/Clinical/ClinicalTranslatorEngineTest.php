<?php

namespace Tests\Feature\Clinical;

use App\Models\Business;
use App\Models\Item;
use App\Services\Clinical\ClinicalTranslatorEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicalTranslatorEngineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_resolves_a_drug_by_generic_name(): void
    {
        Item::create(['business_id' => 1, 'code' => 'PARA_500', 'name' => 'Panadol 500mg', 'generic_name' => 'Paracetamol', 'type' => 'good']);

        $item = app(ClinicalTranslatorEngine::class)->resolveDrug(1, 'Paracetamol');

        $this->assertNotNull($item);
        $this->assertSame('PARA_500', $item->code);
    }

    public function test_it_resolves_a_drug_by_trade_name_in_other_names(): void
    {
        Item::create(['business_id' => 1, 'code' => 'PARA_500B', 'name' => 'Generic Paracetamol', 'generic_name' => 'Paracetamol', 'other_names' => 'Panadol, Calpol, Acetaminophen', 'type' => 'good']);

        $item = app(ClinicalTranslatorEngine::class)->resolveDrug(1, 'Calpol');

        $this->assertNotNull($item);
        $this->assertSame('PARA_500B', $item->code);
    }

    public function test_strength_filter_narrows_to_the_matching_item(): void
    {
        Item::create(['business_id' => 1, 'code' => 'AMOX_250', 'name' => 'Amoxicillin 250mg', 'generic_name' => 'Amoxicillin', 'type' => 'good']);
        Item::create(['business_id' => 1, 'code' => 'AMOX_500', 'name' => 'Amoxicillin 500mg', 'generic_name' => 'Amoxicillin', 'type' => 'good']);

        $item = app(ClinicalTranslatorEngine::class)->resolveDrug(1, 'Amoxicillin', '500mg');

        $this->assertSame('AMOX_500', $item->code);
    }

    public function test_it_returns_null_when_no_internal_sku_matches(): void
    {
        $item = app(ClinicalTranslatorEngine::class)->resolveDrug(1, 'NotARealDrugXYZ');

        $this->assertNull($item);
    }

    public function test_it_generates_an_external_referral_pdf(): void
    {
        Storage::fake('local');
        Business::firstOrCreate(['id' => 1], ['name' => 'Test Business']);

        $path = app(ClinicalTranslatorEngine::class)->generateExternalReferral(1, [
            'client_id' => 'CLIENT-TX-1',
            'drug_search' => 'Rare Imported Drug',
            'strength' => '10mg',
            'dose_amount' => 1,
            'route_code' => 'PO',
            'frequency_code' => 'BID',
            'clinical_indication' => 'Test',
            'ordering_clinician_name' => 'Dr. Test',
        ]);

        Storage::disk('local')->assertExists($path);
    }
}
