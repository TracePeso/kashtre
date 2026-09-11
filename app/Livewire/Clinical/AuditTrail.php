<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\AuditTrailGateway;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Lean audit polish, mirroring ImagingAuditService/ListImagingAuditLog —
 * surfaces the two most compliance-sensitive event streams already being
 * recorded (break-glass grants, major-transition step executions) rather
 * than building a new unified event-log table: every insert-only,
 * attributed row this module already writes (CdeObservation,
 * ClinicalConsumptionEvent, ClinicalMedicationOrder, ...) already IS an
 * audit trail by construction — this view just makes the two most
 * security-relevant ones reviewable in one place.
 *
 * Talks only to AuditTrailGateway, so it works unchanged under either
 * CLINICAL_DRIVER. Clinical's own trail is a single hash-chained stream that
 * already covers both event kinds the local driver tracks separately.
 */
#[Lazy]
class AuditTrail extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public function mount(string $clientId): void
    {
        abort_unless(in_array('View Clinical Audit Trail', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
    }

    public function render()
    {
        return view('livewire.clinical.audit-trail', [
            'entries' => collect(
                app(AuditTrailGateway::class)->forPatient($this->actor(), $this->clientId)
            ),
        ]);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
