<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\SensitivityRestrictionGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 1 §12 — sensitivity restrictions on this patient's
 * record. Ordinary title/permission/client-space assignment do NOT
 * automatically override one (CLN-CONS-003) — this panel only ever shows
 * that a restriction exists, never its label/reason to an unauthorized
 * viewer (CLN-CONS-004's own no-leak rule, mirrored client-side).
 */
#[Lazy]
class SensitivityRestrictionsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $level = SensitivityRestrictionGateway::LEVEL_CHART;

    public string $label = '';

    public string $reason = '';

    public string $liftReason = '';

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
        $rows = [];

        try {
            $rows = app(SensitivityRestrictionGateway::class)->forPatient($this->actor(), $this->clientId);
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load sensitivity restrictions — Clinical may be unreachable.';
        }

        return view('livewire.clinical.sensitivity-restrictions-panel', [
            'rows' => $rows,
            'levels' => [
                SensitivityRestrictionGateway::LEVEL_CHART => 'Chart',
                SensitivityRestrictionGateway::LEVEL_ENCOUNTER => 'Encounter',
                SensitivityRestrictionGateway::LEVEL_DOCUMENT => 'Document',
                SensitivityRestrictionGateway::LEVEL_SECTION => 'Section',
                SensitivityRestrictionGateway::LEVEL_DATA_ELEMENT => 'Data element',
            ],
        ]);
    }

    public function restrict(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'label' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(SensitivityRestrictionGateway::class)->restrict($this->actor(), $this->clientId, [
                'level' => $this->level,
                'label' => $this->label,
                'reason' => $this->reason,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Restriction applied.';
        $this->reset(['label', 'reason']);
    }

    public function lift(string $restrictionId): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        if (trim($this->liftReason) === '') {
            $this->errorMessage = 'Enter a reason before lifting.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(SensitivityRestrictionGateway::class)->lift($this->actor(), $restrictionId, $this->liftReason);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Restriction lifted.';
        $this->reset(['liftReason']);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
