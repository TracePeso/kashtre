<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\MedicationOrdersPanel;
use App\Models\ClinicalMedicationOrder;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\ClinicalMasterDictionariesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MedicationOrdersPanelTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    protected function setUp(): void
    {
        parent::setUp();

        (new ClinicalMasterDictionariesSeeder())->run();
    }

    private function userWithPermissions(array $permissions): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'medication-panel-test-'.uniqid().'@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email_verified_at' => now(),
            'business_id' => 1,
            'permissions' => $permissions,
        ]);
    }

    public function test_a_user_without_view_permission_is_blocked(): void
    {
        $user = $this->userWithPermissions([]);

        Livewire::actingAs($user)
            ->test(MedicationOrdersPanel::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_prescribing_a_matched_drug_creates_an_internal_order_with_scheduled_doses(): void
    {
        Item::create(['business_id' => 1, 'code' => 'IBU_400', 'name' => 'Ibuprofen 400mg', 'generic_name' => 'Ibuprofen', 'type' => 'good']);

        $user = $this->userWithPermissions(['View Medication Orders', 'Prescribe Medication Orders']);

        Livewire::actingAs($user)
            ->test(MedicationOrdersPanel::class, ['clientId' => 'CLIENT-RX-1'])
            ->set('drugSearch', 'Ibuprofen')
            ->set('doseAmount', 400)
            ->set('routeCode', 'PO')
            ->set('frequencyCode', 'STAT')
            ->call('prescribe')
            ->assertSee('Prescribed');

        $order = ClinicalMedicationOrder::where('client_id', 'CLIENT-RX-1')->first();
        $this->assertNotNull($order);
        $this->assertFalse($order->is_external);
        $this->assertSame('IBU_400', $order->drug_code);
        $this->assertSame(1, $order->doses()->count());
    }

    public function test_prescribing_an_unmatched_drug_generates_an_external_referral(): void
    {
        Storage::fake('local');
        \App\Models\Business::firstOrCreate(['id' => 1], ['name' => 'Test Business']);

        $user = $this->userWithPermissions(['View Medication Orders', 'Prescribe Medication Orders']);

        Livewire::actingAs($user)
            ->test(MedicationOrdersPanel::class, ['clientId' => 'CLIENT-RX-2'])
            ->set('drugSearch', 'Extremely Rare Imported Compound')
            ->set('doseAmount', 10)
            ->set('routeCode', 'PO')
            ->set('frequencyCode', 'STAT')
            ->call('prescribe')
            ->assertSee('External referral generated');

        $order = ClinicalMedicationOrder::where('client_id', 'CLIENT-RX-2')->first();
        $this->assertTrue($order->is_external);
        $this->assertNotNull($order->external_referral_path);
        Storage::disk('local')->assertExists($order->external_referral_path);
    }

    public function test_a_hard_blocked_order_requires_an_override_reason_before_it_is_created(): void
    {
        $item = Item::create(['business_id' => 1, 'code' => 'PEN_UI', 'name' => 'Penicillin UI Test', 'generic_name' => 'Penicillin', 'type' => 'good']);

        \App\Models\CdeObservation::create([
            'business_id' => 1,
            'client_id' => 'CLIENT-RX-3',
            'cde_code' => 'ALLERGY_MEDICATION',
            'captured_value_text' => 'Penicillin',
            'capture_method' => 'MANUAL',
            'validation_status' => 'VALIDATED',
            'captured_at' => now(),
        ]);

        $user = $this->userWithPermissions(['View Medication Orders', 'Prescribe Medication Orders', 'Override CDSS Safety Block']);

        $component = Livewire::actingAs($user)
            ->test(MedicationOrdersPanel::class, ['clientId' => 'CLIENT-RX-3'])
            ->set('drugSearch', 'Penicillin')
            ->set('doseAmount', 500)
            ->set('routeCode', 'PO')
            ->set('frequencyCode', 'STAT')
            ->call('prescribe');

        // No override reason yet — order should not be created.
        $this->assertSame(0, ClinicalMedicationOrder::where('client_id', 'CLIENT-RX-3')->count());
        $component->assertSee('Safety Block');

        $component->set('overrideReason', 'Patient previously desensitized / tolerates under monitoring.')
            ->call('prescribe');

        $order = ClinicalMedicationOrder::where('client_id', 'CLIENT-RX-3')->first();
        $this->assertNotNull($order);
        $this->assertNotNull($order->cdss_override_reason);
    }
}
