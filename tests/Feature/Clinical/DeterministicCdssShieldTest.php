<?php

namespace Tests\Feature\Clinical;

use App\Models\CdeObservation;
use App\Models\Client;
use App\Models\ClinicalDdiDictionary;
use App\Models\ClinicalMedicationOrder;
use App\Models\ClinicalUomMaster;
use App\Models\Item;
use App\Services\Clinical\DeterministicCdssShield;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeterministicCdssShieldTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    private function recordObservation(string $clientId, string $cdeCode, float $baseValue): void
    {
        $kgId = ClinicalUomMaster::whereNull('business_id')->where('unit_label', 'kg')->value('id')
            ?? ClinicalUomMaster::whereNull('business_id')->where('unit_label', 'umol/L')->value('id');

        CdeObservation::create([
            'business_id' => 1,
            'client_id' => $clientId,
            'cde_code' => $cdeCode,
            'captured_value_numeric' => $baseValue,
            'input_uom_id' => $kgId,
            'base_uom_id' => $kgId,
            'base_value_numeric' => $baseValue,
            'capture_method' => 'MANUAL',
            'validation_status' => 'VALIDATED',
            'captured_at' => now(),
        ]);
    }

    public function test_a_safe_order_with_no_findings_passes(): void
    {
        $result = app(DeterministicCdssShield::class)->evaluateMedicationSafety([
            'drug_code' => null,
            'dose_mg' => 500,
        ], 'CLIENT-CDSS-1', 1);

        $this->assertTrue($result['is_safe']);
        $this->assertEmpty($result['hard_blocks']);
    }

    public function test_pediatric_overdose_is_hard_blocked(): void
    {
        $client = Client::create([
            'business_id' => 1,
            'branch_id' => 1,
            'client_id' => 'CLIENT-CDSS-2',
            'name' => 'Test Child',
            'date_of_birth' => now()->subYears(5),
            'phone_number' => '0700000000',
            'email' => 'test-'.uniqid().'@example.test',
        ]);

        $this->recordObservation('CLIENT-CDSS-2', 'BODY_WEIGHT', 20.0); // 20kg child

        // Max 15mg/kg -> allowed max 300mg, 150% = 450mg. Requesting 600mg.
        $result = app(DeterministicCdssShield::class)->evaluateMedicationSafety([
            'drug_code' => null,
            'dose_mg' => 600,
        ], 'CLIENT-CDSS-2', 1);

        $this->assertFalse($result['is_safe']);
        $this->assertSame('PEDIATRIC_WEIGHT_OVERDOSE', $result['hard_blocks'][0]['type']);
    }

    public function test_pediatric_dose_within_limits_is_not_blocked(): void
    {
        Client::create([
            'business_id' => 1,
            'branch_id' => 1,
            'client_id' => 'CLIENT-CDSS-3',
            'name' => 'Test Child 2',
            'date_of_birth' => now()->subYears(5),
            'phone_number' => '0700000000',
            'email' => 'test-'.uniqid().'@example.test',
        ]);

        $this->recordObservation('CLIENT-CDSS-3', 'BODY_WEIGHT', 20.0);

        $result = app(DeterministicCdssShield::class)->evaluateMedicationSafety([
            'drug_code' => null,
            'dose_mg' => 200, // well within 300mg max
        ], 'CLIENT-CDSS-3', 1);

        $this->assertTrue($result['is_safe']);
    }

    public function test_reduced_egfr_with_a_nephrotoxic_drug_produces_a_warning_not_a_block(): void
    {
        $this->recordObservation('CLIENT-CDSS-4', 'EGFR_CALCULATED', 30.0);

        $result = app(DeterministicCdssShield::class)->evaluateMedicationSafety([
            'drug_code' => null,
            'dose_mg' => 100,
            'is_nephrotoxic' => true,
        ], 'CLIENT-CDSS-4', 1);

        $this->assertTrue($result['is_safe']);
        $this->assertSame('RENAL_DOSE_REDUCTION_RECOMMENDED', $result['warnings'][0]['type']);
    }

    public function test_a_matching_recorded_allergy_hard_blocks_the_order(): void
    {
        $item = Item::create(['business_id' => 1, 'code' => 'PEN_500', 'name' => 'Penicillin V 500mg', 'generic_name' => 'Penicillin', 'type' => 'good']);

        CdeObservation::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-CDSS-5',
            'cde_code' => 'ALLERGY_MEDICATION',
            'captured_value_text' => 'Penicillin',
            'capture_method' => 'MANUAL',
            'validation_status' => 'VALIDATED',
            'captured_at' => now(),
        ]);

        $result = app(DeterministicCdssShield::class)->evaluateMedicationSafety([
            'drug_code' => $item->code,
            'dose_mg' => 500,
        ], 'CLIENT-CDSS-5', 1);

        $this->assertFalse($result['is_safe']);
        $this->assertSame('DRUG_ALLERGY', $result['hard_blocks'][0]['type']);
    }

    public function test_a_hard_block_drug_interaction_with_an_active_order_is_blocked(): void
    {
        $drugA = Item::create(['business_id' => 1, 'code' => 'WARFARIN', 'name' => 'Warfarin', 'type' => 'good']);
        $drugB = Item::create(['business_id' => 1, 'code' => 'ASPIRIN', 'name' => 'Aspirin', 'type' => 'good']);

        ClinicalDdiDictionary::create([
            'business_id' => 1,
            'drug_a_code' => 'WARFARIN',
            'drug_b_code' => 'ASPIRIN',
            'severity' => ClinicalDdiDictionary::SEVERITY_HARD_BLOCK,
            'description' => 'Severe bleeding risk.',
        ]);

        ClinicalMedicationOrder::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-CDSS-6',
            'ordering_user_id' => 1,
            'drug_code' => 'WARFARIN',
            'drug_display_name' => 'Warfarin',
            'dose_amount' => 5,
            'route_code' => 'PO',
            'frequency_code' => 'QD',
            'start_at' => now(),
            'status' => ClinicalMedicationOrder::STATUS_ACTIVE,
        ]);

        $result = app(DeterministicCdssShield::class)->evaluateMedicationSafety([
            'drug_code' => 'ASPIRIN',
            'dose_mg' => 100,
        ], 'CLIENT-CDSS-6', 1);

        $this->assertFalse($result['is_safe']);
        $this->assertStringContainsString('bleeding risk', $result['hard_blocks'][0]['message']);
    }
}
