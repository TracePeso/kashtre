<?php

namespace App\Livewire\Inventory;

use App\Contracts\Clinical\ConsumptionGateway;
use App\Models\ClinicalFloorStockReview;
use App\Support\Clinical\ClinicalActor;
use App\Support\InventoryBusinessContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * A checklist, not a billing feed — floor-stock usage a clinician recorded
 * from the Clinical chart panel, so whoever runs Record Usage (below) knows
 * to go bill it there. Per the Inventory module's own
 * INTEGRATION_README.md, billing stays a human action on that existing
 * screen; this only makes the chart-recorded lines visible to that human.
 * Marking one reviewed writes to ClinicalFloorStockReview alone — it never
 * touches inventory_usage_events, invoices, or stock.
 */
class PendingClinicalFloorStockUsage extends Component
{
    public bool $showReviewed = false;

    public function render()
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();
        $actor = new ClinicalActor(userId: (int) Auth::id(), businessId: (int) $businessId);

        $lines = collect(app(ConsumptionGateway::class)->pendingFloorStockForBusiness($actor));

        if (! $this->showReviewed) {
            $lines = $lines->reject(fn (array $line) => $line['reviewed']);
        }

        return view('livewire.inventory.pending-clinical-floor-stock-usage', [
            'lines' => $lines->values(),
        ]);
    }

    public function markReviewed(string $eventId): void
    {
        $businessId = InventoryBusinessContext::effectiveBusinessId();

        ClinicalFloorStockReview::updateOrCreate(
            ['business_id' => $businessId, 'clinical_event_id' => $eventId],
            ['reviewed_by_user_id' => Auth::id(), 'reviewed_at' => now()],
        );
    }
}
