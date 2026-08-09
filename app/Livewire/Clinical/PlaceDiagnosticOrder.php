<?php

namespace App\Livewire\Clinical;

use App\Contracts\ModuleDispatcher;
use App\Models\ClinicalWorkOrder;
use App\Models\ImagingProtocol;
use App\Services\Clinical\Facts\DiagnosticOrderPlacedFact;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Chunk 3: diagnostic ordering, wired to the real Imaging module through
 * the ModuleDispatcher rather than a stand-in. Reads ImagingProtocol
 * directly (a plain cross-connection read, not a join) to populate the
 * protocol picker.
 */
class PlaceDiagnosticOrder extends Component
{
    public string $clientId;

    public ?string $visitId = null;

    public string $protocolCode = '';

    public string $clinicalIndication = '';

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Work Orders', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        return view('livewire.clinical.place-diagnostic-order', [
            'protocols' => ImagingProtocol::query()
                ->availableToBusiness(Auth::user()->business_id)
                ->active()
                ->orderBy('name')
                ->get(),
            'workOrders' => ClinicalWorkOrder::query()
                ->where('business_id', Auth::user()->business_id)
                ->where('client_id', $this->clientId)
                ->where('order_type', 'like', 'RAD_%')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function place(): void
    {
        abort_unless(in_array('Add Clinical Work Orders', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'protocolCode' => ['required', 'string'],
            'clinicalIndication' => ['nullable', 'string'],
        ]);

        $user = Auth::user();

        $fact = new DiagnosticOrderPlacedFact(
            businessId: $user->business_id,
            branchId: $user->branch_id,
            globalClientId: $this->clientId,
            visitId: $this->visitId,
            protocolCode: $this->protocolCode,
            orderingClinicianId: $user->id,
            clinicalIndication: $this->clinicalIndication ?: null,
        );

        $response = app(ModuleDispatcher::class)->dispatch($fact);

        ClinicalWorkOrder::create([
            'business_id' => $user->business_id,
            'branch_id' => $user->branch_id,
            'client_id' => $this->clientId,
            'visit_id' => $this->visitId,
            'order_type' => 'RAD_'.$this->protocolCode,
            'ordering_user_id' => $user->id,
            'status' => $response['imaging_study_id'] ?? null ? ClinicalWorkOrder::STATUS_IN_PROGRESS : ClinicalWorkOrder::STATUS_PENDING,
            'external_module' => 'imaging',
            'external_reference' => (string) $response['imaging_order_id'],
        ]);

        $this->protocolCode = '';
        $this->clinicalIndication = '';
    }
}
