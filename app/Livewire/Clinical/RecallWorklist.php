<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\RecallGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Chronic-disease and post-discharge recall worklist — §10.8. Recalls are
 * system-generated (a confirmed diabetes diagnosis schedules review at 90
 * and 365 days, per the recall_rules dictionary); this only reads the
 * worklist and closes an entry once it is actioned.
 */
class RecallWorklist extends Component
{
    public string $status = 'DUE';

    public string $completeNotes = '';

    public ?string $errorMessage = null;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        abort_unless(in_array('View Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        return view('livewire.clinical.recall-worklist', [
            'result' => app(RecallGateway::class)->worklist($this->actor(), $this->status !== '' ? $this->status : null),
        ]);
    }

    public function complete(int|string $recallId): void
    {
        $this->errorMessage = null;

        try {
            app(RecallGateway::class)->complete($this->actor(), $recallId, $this->completeNotes ?: null);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->completeNotes = '';
        $this->statusMessage = 'Recall completed.';
    }

    public function cancel(int|string $recallId): void
    {
        $this->errorMessage = null;

        try {
            app(RecallGateway::class)->cancel($this->actor(), $recallId, 'Cancelled from the recall worklist.');
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = 'Recall cancelled.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
