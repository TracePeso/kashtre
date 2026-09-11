<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\PermissionCatalogGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 1 §6 — the published 163-code atomic permission
 * catalogue. Atomic-code catalogue only: Clinical does not assign these
 * to users, Main registers/assigns on its own side (CLN-OWN-011).
 */
#[Lazy]
class PermissionCatalogPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $search = '';

    public string $riskTierFilter = '';

    public string $newCode = '';

    public string $newDescription = '';

    public string $newRiskTier = 'MODERATE';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(in_array('Manage Clinical Module', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $data = ['data' => [], 'meta' => []];

        try {
            $data = app(PermissionCatalogGateway::class)->list($this->actor(), array_filter([
                'search' => $this->search ?: null,
                'risk_tier' => $this->riskTierFilter ?: null,
            ]));
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load the permission catalogue — Clinical may be unreachable.';
        }

        return view('livewire.clinical.permission-catalog-panel', [
            'rows' => $data['data'] ?? [],
            'meta' => $data['meta'] ?? [],
        ]);
    }

    public function register(): void
    {
        abort_unless(in_array('Manage Clinical Module', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'newCode' => ['required', 'string', 'regex:/^clinical\./'],
            'newDescription' => ['required', 'string'],
            'newRiskTier' => ['required', 'string'],
        ], [], ['newCode' => 'code (must start with clinical.)']);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(PermissionCatalogGateway::class)->register($this->actor(), [
                'code' => $this->newCode,
                'description' => $this->newDescription,
                'risk_tier' => $this->newRiskTier,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = "Registered [{$this->newCode}].";
        $this->reset(['newCode', 'newDescription']);
    }

    public function deactivate(string $permissionId): void
    {
        abort_unless(in_array('Manage Clinical Module', Auth::user()->permissions ?? []), 403);

        $this->errorMessage = null;

        try {
            app(PermissionCatalogGateway::class)->deactivate($this->actor(), $permissionId);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Retired — it stays resolvable for historical audit, just never newly assigned again.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
