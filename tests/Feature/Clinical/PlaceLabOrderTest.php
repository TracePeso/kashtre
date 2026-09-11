<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\PlaceLabOrder;
use App\Models\ClinicalWorkOrder;
use App\Models\User;
use Database\Seeders\ClinicalMasterDictionariesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PlaceLabOrderTest extends TestCase
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
            'email' => 'lab-order-test-'.uniqid().'@example.test',
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
            ->test(PlaceLabOrder::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_placing_an_order_creates_a_pending_lims_linked_work_order(): void
    {
        $user = $this->userWithPermissions(['View Clinical Work Orders', 'Add Clinical Work Orders']);

        Livewire::actingAs($user)
            ->test(PlaceLabOrder::class, ['clientId' => 'CLIENT-LAB-1'])
            ->set('testCode', 'glucose')
            ->call('place')
            ->assertSee('LAB_GLUCOSE');

        $order = ClinicalWorkOrder::where('client_id', 'CLIENT-LAB-1')->first();
        $this->assertNotNull($order);
        $this->assertSame('lims', $order->external_module);
        $this->assertSame(ClinicalWorkOrder::STATUS_PENDING, $order->status);
        $this->assertNotEmpty($order->external_reference);
    }

    public function test_simulating_a_result_completes_the_work_order_and_shows_it_in_the_ui(): void
    {
        $user = $this->userWithPermissions(['View Clinical Work Orders', 'Add Clinical Work Orders']);

        $component = Livewire::actingAs($user)
            ->test(PlaceLabOrder::class, ['clientId' => 'CLIENT-LAB-2'])
            ->set('testCode', 'glucose')
            ->call('place');

        $order = ClinicalWorkOrder::where('client_id', 'CLIENT-LAB-2')->first();

        $component
            ->set("simulatedValues.{$order->id}", 6.5)
            ->call('simulateResult', $order->id)
            ->assertSee('COMPLETED');

        $this->assertSame(ClinicalWorkOrder::STATUS_COMPLETED, $order->fresh()->status);
    }
}
