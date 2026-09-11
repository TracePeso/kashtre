<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\EntitlementGateway;
use App\Models\Item;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.0 §6.3 — the missing half of the entitlement framework. Package
 * registration (on sale, via ClinicalModuleIntegrationService) and
 * consumption (automatic, inside Clinical's own order-placement pipeline)
 * were already real and working; nothing anywhere showed a clinician what a
 * patient actually has left before they order — Clinical even built a
 * `preview` endpoint anticipating this screen, unused until now.
 *
 * Purely a display. The engine that decides INTERNAL vs EXCESS runs
 * server-side on order placement regardless of whether anyone ever looked
 * at this panel — it cannot fall out of sync with what billing decided,
 * because it reads the exact same stored remaining_qty.
 */
#[Lazy]
class EntitlementBalancesPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public function mount(string $clientId): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
    }

    public function render()
    {
        $actor = $this->actor();
        $balances = collect(app(EntitlementGateway::class)->balancesFor($actor, $this->clientId));

        // service_code is a logical key (often a store Item's own code, per
        // clinical_entitlements' own migration comment) — resolving it to a
        // name is a display nicety only; the raw code is what the
        // consumption engine actually matches on and is shown as a fallback.
        $itemNames = Item::where('business_id', $actor->businessId)
            ->whereIn('code', $balances->pluck('serviceCode')->unique())
            ->pluck('name', 'code');

        return view('livewire.clinical.entitlement-balances-panel', [
            'balances' => $balances->groupBy('packageId'),
            'itemNames' => $itemNames,
        ]);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
