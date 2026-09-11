<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\WorkOrderGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Ad-hoc ward tasks — API Integration Guide §10.5. "Re-site the cannula",
 * "chase consent" — never a lab/imaging request, which would skip the
 * Translator Engine, the CDSS shield and dispatch entirely.
 */
#[Lazy]
class WorkOrdersPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $orderName = '';

    public string $notes = '';

    public ?string $errorMessage = null;

    public ?string $statusMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Work Orders', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        return view('livewire.clinical.work-orders-panel', [
            'workOrders' => app(WorkOrderGateway::class)->forPatient($this->actor(), $this->clientId),
        ]);
    }

    public function create(): void
    {
        abort_unless(in_array('Add Clinical Work Orders', Auth::user()->permissions ?? []), 403);

        $this->validate(['orderName' => ['required', 'string']]);

        if (! $this->visitId) {
            $this->errorMessage = 'No visit is open for this patient.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(WorkOrderGateway::class)->create(
                $this->actor(),
                $this->clientId,
                $this->visitId,
                $this->orderName,
                notes: $this->notes ?: null,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = 'Task raised.';
        $this->reset(['orderName', 'notes']);
    }

    public function transition(int|string $workOrderId, string $status): void
    {
        abort_unless(in_array('Add Clinical Work Orders', Auth::user()->permissions ?? []), 403);

        $this->errorMessage = null;

        try {
            app(WorkOrderGateway::class)->transition($this->actor(), $workOrderId, $status);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = 'Task updated.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
