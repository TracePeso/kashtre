<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CareAccessGateway;
use App\Models\ClinicalBed;
use App\Models\Client;
use App\Models\ServiceDeliveryQueue;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * SRD §3: Clinical Task Visibility & Contextual Queue Projection Engine.
 * Projects a filtered view over the Main Module's real enterprise queue
 * (ServiceDeliveryQueue) instead of duplicating it — filtered down to
 * "My Patients" (active care assignments) rather than every pending item
 * hospital-wide.
 *
 * Bridges two different "client_id" concepts in this app: ServiceDeliveryQueue.client_id
 * is the numeric clients.id (a real FK); every Clinical table's client_id
 * is the separate string Client::client_id business identifier. Both
 * columns live on the same `clients` row.
 */
class MyPatientTasksBoard extends Component
{
    public function mount(): void
    {
        abort_unless(in_array('View Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $user = Auth::user();
        $businessId = $user->business_id;

        // The "My Patients" set is the one thing here that comes from the
        // Clinical Module. Everything below it — the enterprise queue, the
        // bed occupancy — is Main's own data, filtered down by that set
        // rather than duplicated. That division is why this board keeps
        // working when the clinical data moves out of this database.
        $myClientIds = app(CareAccessGateway::class)->myPatientIds(ClinicalActor::fromUser($user));

        $numericIds = Client::where('business_id', $businessId)
            ->whereIn('client_id', $myClientIds)
            ->pluck('id', 'client_id');

        $pendingTasks = ServiceDeliveryQueue::whereIn('client_id', $numericIds->values())
            ->where('status', 'pending')
            ->get()
            ->groupBy('item_name');

        $myWards = ClinicalBed::whereIn('current_client_id', $myClientIds)
            ->where('operational_state', ClinicalBed::STATE_OCCUPIED)
            ->with('ward')
            ->get()
            ->groupBy(fn (ClinicalBed $bed) => $bed->ward->ward_name ?? 'Unassigned');

        return view('livewire.clinical.my-patient-tasks-board', [
            'myPatientCount' => count($myClientIds),
            'pendingTasks' => $pendingTasks,
            'myWards' => $myWards,
        ]);
    }
}
