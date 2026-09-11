<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\IdentityConfirmationGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 2 — positive patient identification, generalizing MAR's
 * existing 5-Rights check to 7 more action types beyond medication
 * administration.
 */
#[Lazy]
class IdentityConfirmationsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $actionType = 'SPECIMEN_COLLECTION';

    public string $method = 'VERBAL_AND_BAND';

    public string $notes = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    private const ACTION_TYPES = [
        'SPECIMEN_COLLECTION', 'BLOOD_TRANSFUSION', 'PROCEDURE_OR_SURGERY',
        'IMAGING_STUDY', 'DISCHARGE', 'PATIENT_HANDOVER', 'DOCUMENT_RELEASE',
    ];

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $rows = [];

        try {
            $rows = app(IdentityConfirmationGateway::class)->forPatient($this->actor(), $this->clientId);
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load identity confirmations — Clinical may be unreachable.';
        }

        return view('livewire.clinical.identity-confirmations-panel', [
            'rows' => $rows,
            'actionTypes' => self::ACTION_TYPES,
        ]);
    }

    public function confirm(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate(['actionType' => ['required', 'string']]);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(IdentityConfirmationGateway::class)->confirm($this->actor(), $this->clientId, [
                'action_type' => $this->actionType,
                'confirmed_by_user_id' => Auth::id(),
                'method' => $this->method,
                'notes' => $this->notes ?: null,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Identity confirmed and recorded.';
        $this->reset(['notes']);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
