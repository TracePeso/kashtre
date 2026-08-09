<?php

namespace Tests\Feature\Clinical;

use App\Models\ClinicalBed;
use App\Models\ClinicalProcess;
use App\Models\ClinicalProcessExecution;
use App\Models\ClinicalWard;
use App\Services\Clinical\ClinicalProcessEngine;
use Database\Seeders\ClinicalProcessRegistrySeeder;
use Exception;
use Tests\TestCase;

class ClinicalProcessEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('db')->connection('clinical')->beginTransaction();
        $this->beforeApplicationDestroyed(fn () => $this->app->make('db')->connection('clinical')->rollBack());

        (new ClinicalProcessRegistrySeeder())->run();
    }

    private function ward(): ClinicalWard
    {
        return ClinicalWard::create([
            'business_id' => 1,
            'ward_code' => 'PROC-WARD',
            'ward_name' => 'Process Test Ward',
        ]);
    }

    public function test_cannot_start_a_duplicate_in_progress_process(): void
    {
        $engine = app(ClinicalProcessEngine::class);
        $engine->startProcess('ADMISSION', 1, null, 'CLIENT-PROC-1', null, 42);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/already in progress/');

        $engine->startProcess('ADMISSION', 1, null, 'CLIENT-PROC-1', null, 42);
    }

    public function test_skipping_a_mandatory_step_without_a_reason_is_blocked(): void
    {
        $engine = app(ClinicalProcessEngine::class);
        $execution = $engine->startProcess('ADMISSION', 1, null, 'CLIENT-PROC-2', null, 42);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/mandatory/');

        $engine->skipStep($execution, 42, null);
    }

    public function test_skipping_a_mandatory_step_with_a_reason_records_the_override(): void
    {
        $engine = app(ClinicalProcessEngine::class);
        $execution = $engine->startProcess('ADMISSION', 1, null, 'CLIENT-PROC-3', null, 42);

        $stepExecution = $engine->skipStep($execution, 42, 'Assessment already completed in ED.');

        $this->assertSame('SKIPPED', $stepExecution->status);
        $this->assertSame('Assessment already completed in ED.', $stepExecution->override_reason);
        $this->assertSame('INITIAL_NURSING_ASSESSMENT', $execution->fresh()->currentStep->step_code);
    }

    public function test_skipping_an_optional_step_without_a_reason_succeeds(): void
    {
        $engine = app(ClinicalProcessEngine::class);
        $execution = $engine->startProcess('DISCHARGE', 1, null, 'CLIENT-PROC-4', null, 42);

        // Walk to the optional FINANCIAL_CLEARANCE step (7th of 8).
        for ($i = 0; $i < 6; $i++) {
            $engine->completeStep($execution, 42);
        }

        $this->assertSame('FINANCIAL_CLEARANCE', $execution->fresh()->currentStep->step_code);

        $stepExecution = $engine->skipStep($execution, 42);

        $this->assertSame('SKIPPED', $stepExecution->status);
        $this->assertSame('ENCOUNTER_CLOSURE', $execution->fresh()->currentStep->step_code);
    }

    public function test_full_admission_then_discharge_cycle_occupies_and_releases_a_bed(): void
    {
        $ward = $this->ward();
        $bed = ClinicalBed::create(['ward_id' => $ward->id, 'bed_code' => 'BED-01']);

        $engine = app(ClinicalProcessEngine::class);

        // --- OPD -> "Decision to Admit" ---
        $admission = $engine->startProcess('ADMISSION', 1, null, 'CLIENT-PROC-5', 'VISIT-5', 42, 'Admit for observation.');
        $this->assertSame(ClinicalProcessExecution::STATUS_IN_PROGRESS, $admission->status);

        // Steps 1-4: no side effects.
        for ($i = 0; $i < 4; $i++) {
            $engine->completeStep($admission, 42);
        }

        $this->assertSame('BED_ALLOCATION', $admission->fresh()->currentStep->step_code);
        $this->assertSame(ClinicalBed::STATE_AVAILABLE, $bed->fresh()->operational_state);

        // Step 5: BED_ALLOCATION — requires a bed_id.
        try {
            $engine->completeStep($admission, 42, []);
            $this->fail('Expected an exception when completing BED_ALLOCATION without a bed_id.');
        } catch (Exception $e) {
            $this->assertStringContainsString('bed must be selected', $e->getMessage());
        }

        $engine->completeStep($admission, 42, ['bed_id' => $bed->id]);

        $bed->refresh();
        $this->assertSame(ClinicalBed::STATE_OCCUPIED, $bed->operational_state);
        $this->assertSame('CLIENT-PROC-5', $bed->current_client_id);
        $this->assertSame('WARD_NURSE_ACCEPTANCE', $admission->fresh()->currentStep->step_code);

        // Step 6: final admission step -> execution completes.
        $engine->completeStep($admission, 42);
        $admission->refresh();
        $this->assertSame(ClinicalProcessExecution::STATUS_COMPLETED, $admission->status);
        $this->assertNull($admission->current_step_id);

        // --- DISCHARGE ---
        $discharge = $engine->startProcess('DISCHARGE', 1, null, 'CLIENT-PROC-5', 'VISIT-5', 42);

        for ($i = 0; $i < 6; $i++) {
            $engine->completeStep($discharge, 42);
        }
        $engine->skipStep($discharge, 42); // optional FINANCIAL_CLEARANCE
        $engine->completeStep($discharge, 42); // ENCOUNTER_CLOSURE -> releases bed

        $discharge->refresh();
        $this->assertSame(ClinicalProcessExecution::STATUS_COMPLETED, $discharge->status);

        $bed->refresh();
        $this->assertSame(ClinicalBed::STATE_AVAILABLE, $bed->operational_state);
        $this->assertNull($bed->current_client_id);
    }

    public function test_releasing_an_overflow_bed_on_discharge_retires_it(): void
    {
        $ward = $this->ward();
        $bed = ClinicalBed::create([
            'ward_id' => $ward->id,
            'bed_code' => 'BED-1-EXTRA',
            'is_overflow' => true,
            'operational_state' => ClinicalBed::STATE_OCCUPIED,
            'current_client_id' => 'CLIENT-PROC-6',
        ]);

        $engine = app(ClinicalProcessEngine::class);
        $discharge = $engine->startProcess('DISCHARGE', 1, null, 'CLIENT-PROC-6', null, 42);

        for ($i = 0; $i < 6; $i++) {
            $engine->completeStep($discharge, 42);
        }
        $engine->skipStep($discharge, 42);
        $engine->completeStep($discharge, 42);

        $this->assertNull(ClinicalBed::find($bed->id));
    }

    public function test_resolve_returns_business_specific_row_over_global_default(): void
    {
        $global = ClinicalProcess::where('business_id', null)->where('process_code', 'ADMISSION')->first();
        $this->assertNotNull($global);

        $businessSpecific = ClinicalProcess::create([
            'business_id' => 1,
            'process_code' => 'ADMISSION',
            'process_name' => 'Custom Admission for Business 1',
            'is_active' => true,
        ]);

        $resolved = ClinicalProcess::resolve(1, 'ADMISSION');

        $this->assertSame($businessSpecific->id, $resolved->id);
    }
}
