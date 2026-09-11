<?php

namespace App\Livewire\Clinical;

use App\Contracts\LabResultsBroker;
use App\Contracts\ModuleDispatcher;
use App\Models\ClinicalWorkOrder;
use App\Services\Clinical\Facts\LabOrderPlacedFact;
use App\Services\Clinical\Integration\StubLimsClient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use App\Support\Clinical\ClinicalDriver;

/**
 * Chunk 7: lab ordering against the (stubbed) LIMS. The "Simulate Result"
 * controls only render while LabResultsBroker resolves to StubLimsClient
 * — they disappear automatically once a real LIMS is wired in, without
 * needing a separate feature flag.
 */
#[Lazy]
class PlaceLabOrder extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $testCode = '';

    public string $clinicalIndication = '';

    public array $simulatedValues = [];

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Work Orders', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        // Reads Main's clinical_* tables, which do not exist under
        // CLINICAL_DRIVER=api. Render an explanation instead of a 500.
        if (ClinicalDriver::isApi()) {
            return view('livewire.clinical.partials.driver-unavailable', [
                'title' => 'Lab Orders',
                'detail' => 'Clinical can list work orders but publishes no endpoint to place one.',
            ]);
        }
        return view('livewire.clinical.place-lab-order', [
            'workOrders' => ClinicalWorkOrder::where('business_id', Auth::user()->business_id)
                ->where('client_id', $this->clientId)
                ->where('order_type', 'like', 'LAB_%')
                ->orderByDesc('created_at')
                ->get(),
            'isStubbed' => app(LabResultsBroker::class) instanceof StubLimsClient,
        ]);
    }

    public function place(): void
    {
        abort_unless(in_array('Add Clinical Work Orders', Auth::user()->permissions ?? []), 403);

        $this->validate(['testCode' => ['required', 'string']]);

        $user = Auth::user();

        $fact = new LabOrderPlacedFact(
            businessId: $user->business_id,
            branchId: $user->branch_id,
            globalClientId: $this->clientId,
            visitId: $this->visitId,
            orderingClinicianId: $user->id,
            testCode: strtoupper($this->testCode),
            clinicalIndication: $this->clinicalIndication ?: null,
        );

        $response = app(ModuleDispatcher::class)->dispatch($fact);

        ClinicalWorkOrder::create([
            'business_id' => $user->business_id,
            'branch_id' => $user->branch_id,
            'client_id' => $this->clientId,
            'visit_id' => $this->visitId,
            'order_type' => 'LAB_'.strtoupper($this->testCode),
            'ordering_user_id' => $user->id,
            'status' => ClinicalWorkOrder::STATUS_PENDING,
            'external_module' => 'lims',
            'external_reference' => $response['lab_order_uuid'],
        ]);

        $this->testCode = '';
        $this->clinicalIndication = '';
    }

    public function simulateResult(int $workOrderId): void
    {
        $user = Auth::user();
        $workOrder = ClinicalWorkOrder::where('business_id', $user->business_id)->findOrFail($workOrderId);
        $testCode = substr($workOrder->order_type, 4); // strip 'LAB_' prefix

        $value = (float) ($this->simulatedValues[$workOrderId] ?? 0);

        app(StubLimsClient::class)->simulateResultValidated(
            businessId: $user->business_id,
            branchId: $user->branch_id,
            clientId: $workOrder->client_id,
            visitId: $workOrder->visit_id,
            labOrderUuid: $workOrder->external_reference,
            testCode: $testCode,
            value: $value,
            validatedByUserId: $user->id,
        );
    }
}
