<?php

namespace App\Livewire\Clinical;

use App\Models\ClinicalBed;
use App\Models\ClinicalConsumptionEvent;
use App\Models\Item;
use App\Services\Clinical\ConsumptionEventBroker;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Chunk 4 MVP point-of-care trigger — MAR (Chunk 6) will call
 * ConsumptionEventBroker the same way once it exists; this gives a real,
 * demoable path before then, per the plan's exit criteria ("administering
 * a med from Chunk 6-in-progress MAR (or a manual test fact)").
 */
class RecordConsumption extends Component
{
    public string $clientId;

    public ?string $visitId = null;

    public string $itemCode = '';

    public string $quantity = '';

    public string $factToken = ClinicalConsumptionEvent::TOKEN_MEDICATION_ADMINISTERED;

    public string $usageContext = 'PATIENT';

    public ?string $lastResultMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        return view('livewire.clinical.record-consumption', [
            'items' => Item::where('business_id', Auth::user()->business_id)->orderBy('name')->limit(200)->get(),
            'recentEvents' => ClinicalConsumptionEvent::where('business_id', Auth::user()->business_id)
                ->where('client_id', $this->clientId)
                ->orderByDesc('occurred_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function record(): void
    {
        $this->validate([
            'itemCode' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'factToken' => ['required', 'string'],
            'usageContext' => ['required', 'string'],
        ]);

        $user = Auth::user();

        // The patient's current ward, if admitted — resolved from the
        // Chunk 2 bed board rather than asking the nurse to pick one.
        $wardId = ClinicalBed::where('current_client_id', $this->clientId)->value('ward_id');

        $response = app(ConsumptionEventBroker::class)->emitConsumptionFact([
            'business_id' => $user->business_id,
            'branch_id' => $user->branch_id,
            'client_id' => $this->clientId,
            'visit_id' => $this->visitId,
            'ward_id' => $wardId,
            'item_code' => $this->itemCode,
            'quantity' => (float) $this->quantity,
            'fact_token' => $this->factToken,
            'usage_context' => $this->usageContext,
        ], $user->id);

        $this->lastResultMessage = match ($response['status']) {
            'RECONCILED' => "Recorded ({$response['reconciliation_scenario']})".($response['billing_triggered'] ? ' — billing event created.' : '.'),
            'EXCEPTION' => "Could not deplete stock: {$response['exception_reason']}",
            'INVENTORY_MODULE_INACTIVE' => 'Inventory module is not active for this business.',
            default => $response['status'],
        };

        if ($response['status'] === 'RECONCILED') {
            $this->itemCode = '';
            $this->quantity = '';
        }
    }
}
