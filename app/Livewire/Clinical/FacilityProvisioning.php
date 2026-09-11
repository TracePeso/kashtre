<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\ProvisioningGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\ClinicalBusinessContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * §10.9 "Before any of this works: provision the facility" — sits above
 * ClinicalDictionaries on the same Settings page, because an empty picker
 * there is otherwise indistinguishable from a real bug. One button:
 * "Provision" on a fresh tenant, "Re-sync" once counts exist — the status
 * read is what decides which to render.
 */
class FacilityProvisioning extends Component
{
    public ?array $status = null;

    public ?string $errorMessage = null;

    public ?string $resultMessage = null;

    public bool $isRunning = false;

    public function mount(): void
    {
        $this->loadStatus();
    }

    #[On('facility-context-changed')]
    public function refreshStatus(): void
    {
        $this->resultMessage = null;
        $this->errorMessage = null;
        $this->loadStatus();
    }

    private function loadStatus(): void
    {
        // Same guard as ClinicalDictionaries: a Kashtre admin who has not
        // picked a facility yet must not silently read tenant "1" (the
        // platform business, via effectiveBusinessId()'s own fallback) —
        // that would look exactly like a facility with nothing provisioned.
        if (! $this->canManage() || ClinicalBusinessContext::requiresSelection()) {
            $this->status = null;

            return;
        }

        try {
            $this->status = app(ProvisioningGateway::class)->status(
                ClinicalBusinessContext::actor(),
                $this->tenantId(),
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();
            $this->status = null;
        }
    }

    /**
     * Synchronous on Clinical's side and genuinely slow (their own guide
     * clocks a real run at 47s) — wire:loading in the view is what tells
     * the operator this is still working, not stuck.
     */
    public function provision(bool $resync = false): void
    {
        abort_unless($this->canManage(), 403);
        abort_if(ClinicalBusinessContext::requiresSelection(), 422, 'Select a facility first.');

        $this->errorMessage = null;
        $this->resultMessage = null;
        $this->isRunning = true;

        try {
            $result = app(ProvisioningGateway::class)->provision(
                ClinicalBusinessContext::actor(),
                $this->tenantId(),
                $resync,
            );

            $this->resultMessage = sprintf(
                '%s: %d row(s) across %d dictionaries (%s).',
                $resync ? 'Re-synced' : 'Provisioned',
                $result['rows_created'] ?? $result['total_rows'] ?? 0,
                count(($this->status['counts'] ?? [])) ?: (21 - count($result['empty_dictionaries'] ?? [])),
                ($result['duration_ms'] ?? null) ? round($result['duration_ms'] / 1000, 1).'s' : 'done',
            );
        } catch (ClinicalApiException $e) {
            // ALREADY_PROVISIONED is the one refusal worth naming
            // specifically — it means the button the operator pressed was
            // simply the wrong one, not that anything failed.
            $this->errorMessage = $e->errorCode() === 'ALREADY_PROVISIONED'
                ? 'This tenant already has clinical dictionaries. Use Re-sync instead.'
                : $e->getMessage();
        } finally {
            $this->isRunning = false;
        }

        $this->loadStatus();
    }

    private function canManage(): bool
    {
        return ClinicalBusinessContext::isKashtreAdmin()
            || in_array('Manage Clinical Module', Auth::user()->permissions ?? []);
    }

    private function tenantId(): string
    {
        return (string) ClinicalBusinessContext::effectiveBusinessId();
    }

    public function render()
    {
        return view('livewire.clinical.facility-provisioning', [
            'canManage' => $this->canManage(),
            'needsSelection' => ClinicalBusinessContext::requiresSelection(),
        ]);
    }
}
