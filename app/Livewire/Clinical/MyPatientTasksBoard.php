<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\PatientWorklistGateway;
use App\Models\Client;
use App\Models\ServiceDeliveryQueue;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\PatientTask;
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

        // The "My Patients" set and each patient's location come from the
        // Clinical Module; the enterprise queue below is Main's own data,
        // filtered down by that set rather than duplicated. That division is
        // why this board keeps working with the clinical data moved out.
        $worklist = app(PatientWorklistGateway::class)->myPatients(ClinicalActor::fromUser($user));
        $myClientIds = array_map(fn (PatientTask $t) => $t->patient_id, $worklist);

        $numericIds = Client::where('business_id', $businessId)
            ->whereIn('client_id', $myClientIds)
            ->pluck('id', 'client_id');

        $pendingTasks = ServiceDeliveryQueue::whereIn('client_id', $numericIds->values())
            ->where('status', 'pending')
            ->get()
            ->groupBy('item_name');

        // Outpatients have no ward and would otherwise vanish into an
        // "Unassigned" bucket that looks like a data error; group them under a
        // heading that says what they actually are.
        $myWards = collect($worklist)->groupBy(
            fn (PatientTask $t) => $t->is_admitted
                ? ($t->ward_name ?: $t->ward_code ?: 'Unassigned')
                : 'Outpatients'
        );

        return view('livewire.clinical.my-patient-tasks-board', [
            'myPatientCount' => count($worklist),
            'pendingTasks' => $pendingTasks,
            'myWards' => $myWards,
        ]);
    }
}
