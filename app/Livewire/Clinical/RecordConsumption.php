<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\ConsumptionGateway;
use App\Models\ClinicalConsumptionEvent;
use App\Models\Item;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Chunk 4 MVP point-of-care trigger — MAR (Chunk 6) will call
 * ConsumptionEventBroker the same way once it exists; this gives a real,
 * demoable path before then, per the plan's exit criteria ("administering
 * a med from Chunk 6-in-progress MAR (or a manual test fact)").
 *
 * Talks only to ConsumptionGateway, so it works unchanged under either
 * CLINICAL_DRIVER. Under `api` this only covers the general floor-stock
 * scenario — crash-cart consumption is a separate, larger workflow Clinical
 * exposes but this panel does not (see ApiConsumptionGateway) — and
 * recording here does not yet reduce what Main's Inventory module believes
 * is on the shelf (see the same gateway's class doc). That gap is shown to
 * the clinician, not hidden.
 */
#[Lazy]
class RecordConsumption extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $itemCode = '';

    public string $quantity = '';

    public string $factToken = ClinicalConsumptionEvent::TOKEN_MEDICATION_ADMINISTERED;

    public string $usageContext = 'PATIENT';

    public string $justificationNote = '';

    public ?string $lastResultMessage = null;

    public ?string $errorMessage = null;

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
            'recentEvents' => collect(
                app(ConsumptionGateway::class)->forPatient($this->actor(), $this->clientId)
            ),
        ]);
    }

    public function record(): void
    {
        $this->validate([
            'itemCode' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'factToken' => ['required', 'string'],
            'usageContext' => ['required', 'string'],
            // Clinical requires this on every floor-stock write (confirmed
            // live) — asked for up front here so a clinician sees one clear
            // field, not a round trip to Clinical and back.
            'justificationNote' => ['required', 'string'],
        ]);

        $this->errorMessage = null;
        $this->lastResultMessage = null;

        try {
            app(ConsumptionGateway::class)->record(
                $this->actor(),
                $this->clientId,
                $this->visitId,
                $this->itemCode,
                (float) $this->quantity,
                $this->factToken,
                $this->usageContext,
                $this->justificationNote,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->lastResultMessage = 'Recorded.';
        $this->itemCode = '';
        $this->quantity = '';
        $this->justificationNote = '';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
