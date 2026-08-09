<?php

namespace Tests\Feature\Clinical;

use App\Contracts\ModuleDispatcher;
use App\Models\CdeObservation;
use App\Models\ClinicalWorkOrder;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\Clinical\Facts\LabOrderPlacedFact;
use App\Services\Clinical\Integration\StubLimsClient;
use Database\Seeders\ClinicalMasterDictionariesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LimsIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    protected function setUp(): void
    {
        parent::setUp();

        (new ClinicalMasterDictionariesSeeder())->run();
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'lims-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
        ]);
    }

    public function test_dispatching_the_order_fact_reaches_the_stub_and_returns_an_accession_style_response(): void
    {
        $clinician = $this->user();

        $response = app(ModuleDispatcher::class)->dispatch(new LabOrderPlacedFact(
            businessId: 1,
            branchId: null,
            globalClientId: 'CLIENT-LIMS-1',
            visitId: 'VISIT-LIMS-1',
            orderingClinicianId: $clinician->id,
            testCode: 'GLUCOSE',
        ));

        $this->assertSame('ORDER_RECEIVED', $response['status']);
        $this->assertNotEmpty($response['lab_order_uuid']);
        $this->assertStringStartsWith('SPEC-', $response['specimen_id']);
    }

    public function test_a_simulated_validated_result_lands_as_a_cde_observation_and_completes_the_work_order(): void
    {
        $clinician = $this->user();

        $response = app(ModuleDispatcher::class)->dispatch(new LabOrderPlacedFact(
            businessId: 1,
            branchId: null,
            globalClientId: 'CLIENT-LIMS-2',
            visitId: null,
            orderingClinicianId: $clinician->id,
            testCode: 'GLUCOSE',
        ));

        $workOrder = ClinicalWorkOrder::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-LIMS-2',
            'order_type' => 'LAB_GLUCOSE',
            'ordering_user_id' => $clinician->id,
            'status' => ClinicalWorkOrder::STATUS_PENDING,
            'external_module' => 'lims',
            'external_reference' => $response['lab_order_uuid'],
        ]);

        app(StubLimsClient::class)->simulateResultValidated(
            businessId: 1,
            branchId: null,
            clientId: 'CLIENT-LIMS-2',
            visitId: null,
            labOrderUuid: $response['lab_order_uuid'],
            testCode: 'GLUCOSE',
            value: 7.0, // mmol/L, within physiological bounds
            validatedByUserId: $clinician->id,
        );

        $observation = CdeObservation::where('client_id', 'CLIENT-LIMS-2')->where('cde_code', 'GLUCOSE_RANDOM')->first();
        $this->assertNotNull($observation);
        $this->assertEqualsWithDelta(7.0, (float) $observation->base_value_numeric, 0.01);

        $this->assertSame(ClinicalWorkOrder::STATUS_COMPLETED, $workOrder->fresh()->status);
    }

    public function test_a_result_for_an_unmapped_test_code_falls_back_to_a_generic_cde(): void
    {
        $clinician = $this->user();

        app(StubLimsClient::class)->simulateResultValidated(
            businessId: 1,
            branchId: null,
            clientId: 'CLIENT-LIMS-3',
            visitId: null,
            labOrderUuid: 'not-linked-to-a-work-order',
            testCode: 'FBC',
            value: 12.5,
            validatedByUserId: $clinician->id,
        );

        $observation = CdeObservation::where('client_id', 'CLIENT-LIMS-3')->where('cde_code', 'LAB_RESULT_FBC')->first();
        $this->assertNotNull($observation);
    }

    public function test_a_simulated_critical_result_lands_as_a_cde_observation(): void
    {
        $clinician = $this->user();

        app(StubLimsClient::class)->simulateCriticalResult(
            businessId: 1,
            branchId: null,
            clientId: 'CLIENT-LIMS-4',
            visitId: null,
            labOrderUuid: 'irrelevant',
            testCode: 'CREATININE',
            observedValue: 900,
            criticalType: 'CRITICAL_HIGH',
            authorizingPathologistId: $clinician->id,
        );

        $observation = CdeObservation::where('client_id', 'CLIENT-LIMS-4')->where('cde_code', 'LAB_CRITICAL_CREATININE')->first();
        $this->assertNotNull($observation);
        $this->assertStringContainsString('CRITICAL_HIGH', $observation->captured_value_text);
    }

    public function test_a_simulated_reagent_consumption_depletes_inventory_stock(): void
    {
        InventoryModuleConfig::firstOrCreate(['business_id' => 1], ['is_active' => true]);
        $store = Store::create(['business_id' => 1, 'name' => 'Lab Store '.uniqid()]);
        $item = Item::create(['business_id' => 1, 'code' => 'REAGENT_A', 'name' => 'Reagent A', 'type' => 'good']);
        InventoryStockLevel::create(['business_id' => 1, 'store_id' => $store->id, 'item_id' => $item->id, 'quantity_suom' => 100]);

        $scientist = $this->user();
        $scientist->update(['default_store_id' => $store->id]);

        $response = app(StubLimsClient::class)->simulateReagentConsumption(
            businessId: 1,
            branchId: null,
            clientId: 'CLIENT-LIMS-5',
            visitId: null,
            scientistUserId: $scientist->id,
            itemCode: 'REAGENT_A',
            quantity: 5,
        );

        $this->assertSame('RECONCILED', $response['status']);
        $this->assertFalse($response['billing_triggered']);

        $stock = InventoryStockLevel::where('store_id', $store->id)->where('item_id', $item->id)->value('quantity_suom');
        $this->assertEqualsWithDelta(95.0, (float) $stock, 0.001);
    }
}
