<?php

namespace Tests\Feature\Clinical;

use App\Models\CdeObservation;
use App\Models\CdeRegistry;
use App\Models\ClinicalBed;
use App\Models\ClinicalUomMaster;
use App\Models\ClinicalWard;
use App\Services\Clinical\CdeExecutionEngine;
use Database\Seeders\ClinicalMasterDictionariesSeeder;
use Exception;
use Tests\TestCase;

class CdeExecutionEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 'clinical' is a separate connection from the app's default —
        // wrap it in its own rolled-back transaction per test so seeder +
        // fixture rows don't persist in the real dev database.
        $this->app->make('db')->connection('clinical')->beginTransaction();
        $this->beforeApplicationDestroyed(fn () => $this->app->make('db')->connection('clinical')->rollBack());

        (new ClinicalMasterDictionariesSeeder())->run();
    }

    public function test_ward_and_bed_can_be_created_and_a_bed_can_be_occupied(): void
    {
        $ward = ClinicalWard::create([
            'business_id' => 1,
            'branch_id' => 1,
            'building_wing' => 'Main Block',
            'ward_code' => 'GYN-01',
            'ward_name' => 'Gynaecology Ward',
        ]);

        $bed = ClinicalBed::create([
            'ward_id' => $ward->id,
            'bed_code' => 'BED-01',
        ]);

        $this->assertSame(ClinicalBed::STATE_AVAILABLE, $bed->operational_state);

        $bed->update([
            'operational_state' => ClinicalBed::STATE_OCCUPIED,
            'current_client_id' => 'CLIENT-001',
            'current_visit_id' => 'VISIT-001',
        ]);

        $this->assertSame('CLIENT-001', $bed->fresh()->current_client_id);
    }

    public function test_captures_a_glucose_observation_and_normalizes_to_the_base_unit(): void
    {
        $mgdlId = ClinicalUomMaster::whereNull('business_id')->where('unit_label', 'mg/dL')->value('id');

        $result = app(CdeExecutionEngine::class)->captureObservation([
            'client_id' => 'CLIENT-001',
            'visit_id' => 'VISIT-001',
            'cde_code' => 'GLUCOSE_RANDOM',
            'value_numeric' => 126.1, // mg/dL, base unit is mmol/L
            'input_uom_id' => $mgdlId,
        ], userId: 42, businessId: 1);

        // 126.1 mg/dL / 18.0182 ~= 7.0 mmol/L
        $this->assertEqualsWithDelta(7.0, $result['base_value_normalized'], 0.1);
        $this->assertFalse($result['is_panic_high']);

        $observation = CdeObservation::find($result['observation_id']);
        $this->assertSame('GLUCOSE_RANDOM', $observation->cde_code);
    }

    public function test_captures_a_temperature_observation_using_the_polynomial_conversion(): void
    {
        $fahrenheitId = ClinicalUomMaster::whereNull('business_id')->where('unit_label', '°F')->value('id');

        $result = app(CdeExecutionEngine::class)->captureObservation([
            'client_id' => 'CLIENT-001',
            'cde_code' => 'TEMP_AXILLARY',
            'value_numeric' => 100.4, // °F, base unit is °C
            'input_uom_id' => $fahrenheitId,
        ], userId: 42, businessId: 1);

        $this->assertEqualsWithDelta(38.0, $result['base_value_normalized'], 0.05);
        $this->assertFalse($result['is_panic_high']); // critical_high is seeded at 39.0
    }

    public function test_physiological_heuristic_blocks_an_out_of_range_value(): void
    {
        $mmolId = ClinicalUomMaster::whereNull('business_id')->where('unit_label', 'mmol/L')->value('id');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/HEURISTIC_SAFETY_BLOCK/');

        // 180 mmol/L is physiologically impossible (typed value almost
        // certainly meant mg/dL) — physiological_max for GLUCOSE_RANDOM is
        // seeded at 50.0 mmol/L.
        app(CdeExecutionEngine::class)->captureObservation([
            'client_id' => 'CLIENT-001',
            'cde_code' => 'GLUCOSE_RANDOM',
            'value_numeric' => 180,
            'input_uom_id' => $mmolId,
        ], userId: 42, businessId: 1);
    }

    public function test_unknown_cde_code_throws(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Invalid CDE Code/');

        app(CdeExecutionEngine::class)->captureObservation([
            'client_id' => 'CLIENT-001',
            'cde_code' => 'NOT_A_REAL_CDE',
            'value_numeric' => 1,
            'input_uom_id' => 1,
        ], userId: 42, businessId: 1);
    }
}
