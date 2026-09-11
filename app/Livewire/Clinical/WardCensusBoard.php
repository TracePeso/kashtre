<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\WardCensusGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\WardCensus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * SRD §5.1-5.3: Real-Time Ward Census Header Widget + interactive bed grid +
 * surge capacity. Reads and writes go through WardCensusGateway, so the same
 * board works against the local tables or the Clinical Module.
 *
 * Every action re-reads the census rather than patching the touched bed into
 * place: the action responses describe one bed, so the four header cards would
 * otherwise drift out of step with the grid.
 */
class WardCensusBoard extends Component
{
    /** Which bed's inline form is open, and whether it is a hold or an arrival. */
    public ?int $actioningBedId = null;

    public string $bedAction = 'assign';

    public string $patientId = '';

    public string $visitId = '';

    public ?string $actionError = null;

    public ?string $actionMessage = null;

    /** @var array<int, array<string, mixed>> */
    public array $skippedBeds = [];

    public function mount(): void
    {
        abort_unless(in_array('View Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $wards = $this->gateway()->wards($this->actor());

        // Sum the per-ward totals rather than recounting the grids: the picker
        // reports counts that are authoritative even where a grid is elided.
        $census = [
            'total' => array_sum(array_map(fn (WardCensus $w) => $w->total, $wards)),
            'occupied' => array_sum(array_map(fn (WardCensus $w) => $w->occupied, $wards)),
            'reserved' => array_sum(array_map(fn (WardCensus $w) => $w->reserved, $wards)),
            'available' => array_sum(array_map(fn (WardCensus $w) => $w->available, $wards)),
        ];

        // The picker carries headline counts but no bed grid, so pull the full
        // census per ward for the interactive part of the board.
        $detailed = [];
        foreach ($wards as $ward) {
            $detailed[] = $this->gateway()->census($this->actor(), $ward->ward_code) ?? $ward;
        }

        return view('livewire.clinical.ward-census-board', [
            'wards' => $detailed,
            'census' => $census,
        ]);
    }

    public function startReserve(int $bedId): void
    {
        $this->openForm($bedId, 'reserve');
    }

    public function startAssign(int $bedId): void
    {
        $this->openForm($bedId, 'assign');
    }

    public function cancelAction(): void
    {
        $this->actioningBedId = null;
        $this->actionError = null;
    }

    public function confirmBedAction(): void
    {
        $this->authorizeManage();

        // Clinical requires visit_id on both transitions — a bed is held or
        // filled for a specific visit, not for a patient in the abstract.
        // Catching it here saves a round trip and a generic 422.
        $this->validate([
            'patientId' => ['required', 'string'],
            'visitId' => ['required', 'string'],
        ], [], ['patientId' => 'patient ID', 'visitId' => 'visit ID']);

        $this->run(function () {
            $bedId = (int) $this->actioningBedId;
            $visitId = $this->visitId;

            if ($this->bedAction === 'reserve') {
                $this->gateway()->reserveBed($this->actor(), $bedId, $this->patientId, $visitId);
            } else {
                $movementId = $this->gateway()->assignBed($this->actor(), $bedId, $this->patientId, $visitId);

                // Care Transitions (v6.1 §1) needs this id as evidence an
                // internal transfer's bed move happened — shown here since
                // this is the only screen that ever sees it.
                if ($movementId !== null) {
                    $this->actionMessage = "Bed assigned — movement #{$movementId} (needed to complete an internal transfer).";
                }
            }

            $this->actioningBedId = null;
        });
    }

    public function releaseBed(int $bedId): void
    {
        $this->authorizeManage();

        $this->run(fn () => $this->gateway()->releaseBed($this->actor(), $bedId));
    }

    /**
     * Releasing a surge bed is the moment to stand it down; leaving it
     * AVAILABLE quietly inflates the ward's capacity figure.
     */
    public function retireBed(int $bedId): void
    {
        $this->authorizeManage();

        $this->run(fn () => $this->gateway()->retireBed($this->actor(), $bedId));
    }

    public function addOverflowBed(int $clientSpaceId): void
    {
        abort_unless(in_array('Add Overflow Beds', Auth::user()->permissions ?? []), 403);

        $this->run(fn () => $this->gateway()->addOverflowBed($this->actor(), $clientSpaceId));
    }

    public function clearSurgeBeds(string $wardCode): void
    {
        $this->authorizeManage();

        $this->run(function () use ($wardCode) {
            $result = $this->gateway()->retireVacantOverflowBeds($this->actor(), $wardCode);

            // A bare "done" would be misleading when a bed was left in place;
            // say which stayed and why.
            $this->actionMessage = $result['message']
                ?? "Retired {$result['retired_count']} overflow bed(s).";
            $this->skippedBeds = $result['skipped'];
        });
    }

    private function openForm(int $bedId, string $action): void
    {
        $this->authorizeManage();

        $this->actioningBedId = $bedId;
        $this->bedAction = $action;
        $this->patientId = '';
        $this->visitId = '';
        $this->actionError = null;
    }

    /**
     * Bed rules are enforced by whichever side owns the data, and its refusal
     * message is written for a clinician — surface it rather than a generic
     * failure.
     *
     * Clinical returns two different 422 shapes and they need opposite
     * handling. A bed-rule refusal puts the readable text in `message` and
     * machine context in `errors` ("Bed [ICU-01] is already occupied." with
     * `current_patient_id: CL-…`), so showing `errors` there would print a
     * patient id at a clinician. A field validation failure inverts it: a
     * generic `message` with the useful text in `errors`. Validation errors
     * are arrays of strings, refusal context is scalar — that is the tell.
     */
    private function run(callable $action): void
    {
        $this->actionError = null;
        $this->actionMessage = null;
        $this->skippedBeds = [];

        try {
            $action();
        } catch (ValidationException $e) {
            $this->actionError = collect($e->errors())->flatten()->first() ?: $e->getMessage();
        } catch (ClinicalApiException $e) {
            $fieldErrors = collect($e->errors())
                ->filter(fn ($value) => is_array($value))
                ->flatten();

            $this->actionError = $fieldErrors->isNotEmpty()
                ? $fieldErrors->first()
                : $e->getMessage();
        } catch (\Throwable $e) {
            $this->actionError = $e->getMessage();
        }
    }

    private function authorizeManage(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);
    }

    private function gateway(): WardCensusGateway
    {
        return app(WardCensusGateway::class);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
