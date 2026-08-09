<?php

namespace App\Livewire\Clinical;

use App\Models\ClinicalBed;
use App\Models\ClinicalWard;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * SRD §5.1-5.3: Real-Time Ward Census Header Widget + interactive bed
 * grid + Overflow/Surge bed creation. Clicking an OCCUPIED bed opens the
 * patient's observation chart; clicking AVAILABLE opens an inline
 * occupy form.
 */
class WardCensusBoard extends Component
{
    public ?int $occupyingBedId = null;

    public string $occupyClientId = '';

    public string $occupyVisitId = '';

    public function mount(): void
    {
        abort_unless(in_array('View Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $businessId = Auth::user()->business_id;

        $wards = ClinicalWard::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->with('beds')
            ->orderBy('ward_name')
            ->get();

        $beds = $wards->flatMap->beds;

        $census = [
            'total' => $beds->count(),
            'occupied' => $beds->where('operational_state', ClinicalBed::STATE_OCCUPIED)->count(),
            'reserved' => $beds->where('operational_state', ClinicalBed::STATE_RESERVED)->count(),
            'available' => $beds->where('operational_state', ClinicalBed::STATE_AVAILABLE)->count(),
        ];

        return view('livewire.clinical.ward-census-board', [
            'wards' => $wards,
            'census' => $census,
        ]);
    }

    public function addOverflowBed(int $wardId): void
    {
        abort_unless(in_array('Add Overflow Beds', Auth::user()->permissions ?? []), 403);

        $ward = ClinicalWard::where('business_id', Auth::user()->business_id)->findOrFail($wardId);

        $nextNumber = $ward->beds()->count() + 1;

        ClinicalBed::create([
            'ward_id' => $ward->id,
            'bed_code' => "BED-{$nextNumber}-EXTRA",
            'operational_state' => ClinicalBed::STATE_AVAILABLE,
            'is_overflow' => true,
        ]);
    }

    public function startOccupy(int $bedId): void
    {
        $this->occupyingBedId = $bedId;
        $this->occupyClientId = '';
        $this->occupyVisitId = '';
    }

    public function cancelOccupy(): void
    {
        $this->occupyingBedId = null;
    }

    public function confirmOccupy(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'occupyClientId' => ['required', 'string'],
            'occupyVisitId' => ['nullable', 'string'],
        ]);

        $bed = $this->bedForCurrentBusiness($this->occupyingBedId);

        $bed->update([
            'operational_state' => ClinicalBed::STATE_OCCUPIED,
            'current_client_id' => $this->occupyClientId,
            'current_visit_id' => $this->occupyVisitId ?: null,
        ]);

        $this->occupyingBedId = null;
    }

    /**
     * SRD §5.3 auto-retirement: releasing an overflow bed removes the
     * temporary slot entirely rather than leaving it AVAILABLE, resetting
     * the ward back to its baseline capacity.
     */
    public function releaseBed(int $bedId): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $bed = $this->bedForCurrentBusiness($bedId);

        if ($bed->is_overflow) {
            $bed->delete();

            return;
        }

        $bed->update([
            'operational_state' => ClinicalBed::STATE_AVAILABLE,
            'current_client_id' => null,
            'current_visit_id' => null,
        ]);
    }

    private function bedForCurrentBusiness(int $bedId): ClinicalBed
    {
        return ClinicalBed::whereHas('ward', function ($query) {
            $query->where('business_id', Auth::user()->business_id);
        })->findOrFail($bedId);
    }
}
