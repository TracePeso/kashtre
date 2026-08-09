<?php

namespace Tests\Feature\Clinical;

use App\Livewire\Clinical\PlaceDiagnosticOrder;
use App\Models\ClinicalWorkOrder;
use App\Models\ImagingOrder;
use App\Models\ImagingProtocol;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PlaceDiagnosticOrderTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = [null, 'clinical'];

    private function userWithPermissions(array $permissions): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'place-order-test-'.uniqid().'@example.test',
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
            ->test(PlaceDiagnosticOrder::class, ['clientId' => 'CLIENT-001'])
            ->assertForbidden();
    }

    public function test_placing_an_order_creates_a_real_imaging_order_and_a_linked_work_order(): void
    {
        ImagingProtocol::create([
            'business_id' => 1,
            'code' => 'XR_CHEST',
            'name' => 'Chest X-Ray',
            'modality_type' => 'DX',
            'is_active' => true,
            'requires_consent' => false,
            'requires_preparation' => false,
        ]);

        $user = $this->userWithPermissions(['View Clinical Work Orders', 'Add Clinical Work Orders']);

        Livewire::actingAs($user)
            ->test(PlaceDiagnosticOrder::class, ['clientId' => 'CLIENT-001', 'visitId' => 'VISIT-001'])
            ->set('protocolCode', 'XR_CHEST')
            ->set('clinicalIndication', 'Cough and fever')
            ->call('place')
            ->assertSee('RAD_XR_CHEST');

        $imagingOrder = ImagingOrder::where('client_id', 'CLIENT-001')->first();
        $this->assertNotNull($imagingOrder);
        $this->assertSame('XR_CHEST', $imagingOrder->protocol_code);

        $workOrder = ClinicalWorkOrder::where('client_id', 'CLIENT-001')->first();
        $this->assertNotNull($workOrder);
        $this->assertSame('imaging', $workOrder->external_module);
        $this->assertSame((string) $imagingOrder->id, $workOrder->external_reference);
        $this->assertSame(ClinicalWorkOrder::STATUS_IN_PROGRESS, $workOrder->status);
    }

    public function test_placing_an_order_without_the_add_permission_is_blocked(): void
    {
        ImagingProtocol::create([
            'business_id' => 1,
            'code' => 'XR_CHEST_2',
            'name' => 'Chest X-Ray 2',
            'modality_type' => 'DX',
            'is_active' => true,
        ]);

        $user = $this->userWithPermissions(['View Clinical Work Orders']);

        Livewire::actingAs($user)
            ->test(PlaceDiagnosticOrder::class, ['clientId' => 'CLIENT-002'])
            ->set('protocolCode', 'XR_CHEST_2')
            ->call('place')
            ->assertForbidden();
    }
}
