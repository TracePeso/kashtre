<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\MedicationReconciliationGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 6 — medication reconciliation at admission/transfer/
 * discharge. Separate from the live eMAR pipeline (MedicationOrdersPanel):
 * this records the patient's own source list, decided item by item, before
 * closing the reconciliation — not an order, not an administration.
 */
#[Lazy]
class MedicationReconciliationPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public ?string $activeReconciliationId = null;

    public string $reconciliationType = MedicationReconciliationGateway::TYPE_ADMISSION;

    public string $newItemSource = 'PATIENT_REPORT';

    public string $newItemMedicationName = '';

    public string $newItemDose = '';

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

        if ($this->activeReconciliationId) {
            try {
                $active = app(MedicationReconciliationGateway::class)->show($this->actor(), $this->activeReconciliationId);
            } catch (Exception $e) {
                $this->errorMessage ??= 'Could not refresh this reconciliation — it may still be shown with stale data.';
            }
        }

        return view('livewire.clinical.medication-reconciliation-panel', [
            'types' => [
                MedicationReconciliationGateway::TYPE_ADMISSION => 'Admission',
                MedicationReconciliationGateway::TYPE_TRANSFER => 'Transfer',
                MedicationReconciliationGateway::TYPE_DISCHARGE => 'Discharge',
            ],
            'decisions' => [
                MedicationReconciliationGateway::DECISION_CONTINUE => 'Continue',
                MedicationReconciliationGateway::DECISION_MODIFY => 'Modify',
                MedicationReconciliationGateway::DECISION_HOLD => 'Hold',
                MedicationReconciliationGateway::DECISION_STOP => 'Stop',
                MedicationReconciliationGateway::DECISION_SUBSTITUTE => 'Substitute',
                MedicationReconciliationGateway::DECISION_DEFER_REVIEW => 'Defer review',
                MedicationReconciliationGateway::DECISION_NOT_CURRENT => 'Not current',
            ],
            'active' => $active,
        ]);
    }

    public function start(): void
    {
        abort_unless(in_array('Prescribe Medication Orders', Auth::user()->permissions ?? []), 403);

        $this->validate(['reconciliationType' => ['required', 'string']], [], ['reconciliationType' => 'type']);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            $record = app(MedicationReconciliationGateway::class)->start($this->actor(), $this->clientId, $this->visitId, [
                'reconciliation_type' => $this->reconciliationType,
                'performed_by_user_id' => Auth::id(),
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->activeReconciliationId = (string) ($record['public_id'] ?? $record['id'] ?? '');
        $this->resultMessage = 'Reconciliation started — add the patient\'s source medication list below.';
    }

    public function addItem(): void
    {
        abort_unless(in_array('Prescribe Medication Orders', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'newItemMedicationName' => ['required', 'string'],
            'newItemSource' => ['required', 'string'],
        ], [], ['newItemMedicationName' => 'medication name']);

        if (! $this->activeReconciliationId) {
            $this->errorMessage = 'Start a reconciliation first.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(MedicationReconciliationGateway::class)->addItem($this->actor(), $this->activeReconciliationId, [
                'source' => $this->newItemSource,
                'medication_name' => $this->newItemMedicationName,
                'dose' => $this->newItemDose ?: null,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->reset(['newItemMedicationName', 'newItemDose']);
        $this->resultMessage = 'Item added.';
    }

    public function decideItem(string $itemId, string $decision): void
    {
        abort_unless(in_array('Prescribe Medication Orders', Auth::user()->permissions ?? []), 403);

        $this->errorMessage = null;

        try {
            app(MedicationReconciliationGateway::class)->decideItem($this->actor(), $itemId, $decision, (int) Auth::id());
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Decision recorded.';
    }

    public function complete(): void
    {
        abort_unless(in_array('Prescribe Medication Orders', Auth::user()->permissions ?? []), 403);

        if (! $this->activeReconciliationId) {
            return;
        }

        $this->errorMessage = null;

        try {
            app(MedicationReconciliationGateway::class)->complete($this->actor(), $this->activeReconciliationId);
        } catch (ClinicalApiException $e) {
            // e.g. 422 RECONCILIATION_ITEMS_UNDECIDED — Clinical's own message
            // already names how many items are still undecided.
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Reconciliation completed.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
