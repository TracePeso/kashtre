<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\HandoverRecordGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 9 — the accountable Handover Record: prepare → send →
 * acknowledge, with versioned amendment. Distinct from ShiftHandoverBoard,
 * which renders the older, stateless `GET clinical/handover` ward
 * projection ("what's outstanding right now") — never itself accepted by
 * anyone. This panel is the event: who prepared it, who it was sent to, and
 * whether the receiver actually accepted it.
 */
#[Lazy]
class HandoverRecordsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public ?string $activeHandoverId = null;

    public string $intendedReceiverId = '';

    public string $situation = '';

    public string $plan = '';

    public string $acknowledgeNote = '';

    public string $amendSituation = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $active = null;

        if ($this->activeHandoverId) {
            try {
                $active = app(HandoverRecordGateway::class)->show($this->actor(), $this->activeHandoverId);
            } catch (Exception $e) {
                $this->errorMessage ??= 'Could not refresh this handover — it may still be shown with stale data.';
            }
        }

        return view('livewire.clinical.handover-records-panel', ['active' => $active]);
    }

    public function prepare(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'intendedReceiverId' => ['required', 'string'],
            'situation' => ['required', 'string', 'min:3'],
        ], [], ['intendedReceiverId' => 'intended receiver']);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            $record = app(HandoverRecordGateway::class)->prepare($this->actor(), [
                'patient_id' => $this->clientId,
                'encounter_id' => $this->visitId,
                'intended_receiver_id' => $this->intendedReceiverId,
                'content' => array_filter([
                    'situation' => $this->situation,
                    'plan' => $this->plan ?: null,
                ], fn ($v) => $v !== null),
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->activeHandoverId = (string) ($record['public_id'] ?? $record['id'] ?? '');
        $this->resultMessage = 'Handover prepared as a draft — send it when ready.';
        $this->reset(['intendedReceiverId', 'situation', 'plan']);
    }

    public function send(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        if (! $this->activeHandoverId) {
            return;
        }

        $this->errorMessage = null;

        try {
            app(HandoverRecordGateway::class)->send($this->actor(), $this->activeHandoverId);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        // Sending is not acceptance — the record now waits on the receiver's
        // own acknowledge() before responsibility actually transfers.
        $this->resultMessage = 'Handover sent — awaiting the receiver\'s acknowledgement.';
    }

    public function acknowledge(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        if (! $this->activeHandoverId) {
            return;
        }

        $this->errorMessage = null;

        try {
            app(HandoverRecordGateway::class)->acknowledge($this->actor(), $this->activeHandoverId, $this->acknowledgeNote ?: null);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Acknowledged — clinical responsibility has transferred.';
        $this->reset(['acknowledgeNote']);
    }

    public function amend(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate(['amendSituation' => ['required', 'string', 'min:3']], [], ['amendSituation' => 'updated situation']);

        if (! $this->activeHandoverId) {
            return;
        }

        $this->errorMessage = null;

        try {
            $record = app(HandoverRecordGateway::class)->amend($this->actor(), $this->activeHandoverId, [
                'situation' => $this->amendSituation,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        // A later version supersedes this one — never edited in place — so
        // the panel now follows the new version, not the one just amended.
        $this->activeHandoverId = (string) ($record['public_id'] ?? $record['id'] ?? $this->activeHandoverId);
        $this->resultMessage = 'Amended — a new version now supersedes the original.';
        $this->reset(['amendSituation']);
    }

    public function selectHandover(string $handoverId): void
    {
        $this->activeHandoverId = $handoverId;
        $this->resultMessage = null;
        $this->errorMessage = null;
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
