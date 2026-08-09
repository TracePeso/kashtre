<?php

namespace Tests\Feature\Clinical;

use App\Models\ClinicalMarDose;
use App\Models\ClinicalMedicationOrder;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Services\Clinical\MarSchedulerService;
use Database\Seeders\ClinicalMasterDictionariesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MarSchedulerServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    protected function setUp(): void
    {
        parent::setUp();

        (new ClinicalMasterDictionariesSeeder())->run();
    }

    private function order(string $frequencyCode, ?\Illuminate\Support\Carbon $endAt = null): ClinicalMedicationOrder
    {
        return ClinicalMedicationOrder::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-MAR-'.uniqid(),
            'ordering_user_id' => 1,
            'drug_code' => null,
            'drug_display_name' => 'Test Drug',
            'dose_amount' => 1,
            'route_code' => 'PO',
            'frequency_code' => $frequencyCode,
            'start_at' => now()->startOfDay(),
            'end_at' => $endAt,
            'status' => ClinicalMedicationOrder::STATUS_ACTIVE,
        ]);
    }

    public function test_bid_generates_two_doses_per_day_over_a_two_day_window(): void
    {
        $order = $this->order('BID', now()->startOfDay()->addDays(2));

        app(MarSchedulerService::class)->generateDosesForOrder($order);

        $this->assertSame(5, $order->doses()->count()); // day0 00:00,12:00; day1 00:00,12:00; day2 00:00
    }

    public function test_stat_generates_exactly_one_dose(): void
    {
        $order = $this->order('STAT');

        app(MarSchedulerService::class)->generateDosesForOrder($order);

        $this->assertSame(1, $order->doses()->count());
    }

    public function test_prn_generates_no_scheduled_doses(): void
    {
        $order = $this->order('PRN');

        app(MarSchedulerService::class)->generateDosesForOrder($order);

        $this->assertSame(0, $order->doses()->count());
    }

    public function test_administering_a_linked_dose_depletes_inventory_stock(): void
    {
        InventoryModuleConfig::firstOrCreate(['business_id' => 1], ['is_active' => true]);
        $store = Store::create(['business_id' => 1, 'name' => 'MAR Store '.uniqid()]);
        $item = Item::create(['business_id' => 1, 'code' => 'MAR_ITEM', 'name' => 'MAR Drug', 'type' => 'good']);
        InventoryStockLevel::create(['business_id' => 1, 'store_id' => $store->id, 'item_id' => $item->id, 'quantity_suom' => 50]);

        $user = \App\Models\User::create([
            'name' => 'Test Nurse',
            'email' => 'mar-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'business_id' => 1,
            'default_store_id' => $store->id,
        ]);

        $order = ClinicalMedicationOrder::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-MAR-ADMIN',
            'ordering_user_id' => $user->id,
            'drug_code' => 'MAR_ITEM',
            'drug_display_name' => 'MAR Drug',
            'dose_amount' => 2,
            'route_code' => 'PO',
            'frequency_code' => 'STAT',
            'start_at' => now(),
            'status' => ClinicalMedicationOrder::STATUS_ACTIVE,
        ]);

        app(MarSchedulerService::class)->generateDosesForOrder($order);
        $dose = $order->doses()->first();

        $response = app(MarSchedulerService::class)->administerDose($dose, $user->id);

        $this->assertSame('RECONCILED', $response['status']);
        $this->assertSame(ClinicalMarDose::STATUS_ADMINISTERED, $dose->fresh()->status);

        $stock = InventoryStockLevel::where('store_id', $store->id)->where('item_id', $item->id)->value('quantity_suom');
        $this->assertEqualsWithDelta(48.0, (float) $stock, 0.001);
    }

    public function test_holding_a_dose_records_the_reason(): void
    {
        $order = $this->order('STAT');
        app(MarSchedulerService::class)->generateDosesForOrder($order);
        $dose = $order->doses()->first();

        app(MarSchedulerService::class)->holdDose($dose, 1, 'MAR_HOLD_NPO_ORDER', 'Patient NPO for surgery.');

        $dose->refresh();
        $this->assertSame(ClinicalMarDose::STATUS_HELD, $dose->status);
        $this->assertSame('MAR_HOLD_NPO_ORDER', $dose->reason_code);
    }
}
