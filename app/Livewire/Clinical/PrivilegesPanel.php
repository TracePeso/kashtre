<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\PrivilegeGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 1 §9 — high-risk privileges, the exact 9 named
 * categories, represented separately from ordinary title-derived
 * permissions (CLN-PRIV-001).
 */
#[Lazy]
class PrivilegesPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $userId = '';

    public string $category = PrivilegeGateway::CATEGORY_CONTROLLED_MEDICINE_PRESCRIBING;

    public string $effectiveStart = '';

    public string $effectiveEnd = '';

    public string $credentialReference = '';

    public string $lookupUserId = '';

    public string $suspendReason = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    private const CATEGORIES = [
        PrivilegeGateway::CATEGORY_CONTROLLED_MEDICINE_PRESCRIBING => 'Controlled-medicine prescribing',
        PrivilegeGateway::CATEGORY_CHEMOTHERAPY => 'Chemotherapy verification or administration',
        PrivilegeGateway::CATEGORY_BLOOD_PRODUCT_AUTHORIZATION => 'Blood-product authorization',
        PrivilegeGateway::CATEGORY_INDEPENDENT_PROCEDURE => 'Independent procedure performance',
        PrivilegeGateway::CATEGORY_SEDATION => 'Sedation',
        PrivilegeGateway::CATEGORY_HIGH_ALERT_MEDICATION_OVERRIDE => 'High-alert medication override',
        PrivilegeGateway::CATEGORY_DEATH_VALIDATION => 'Death validation',
        PrivilegeGateway::CATEGORY_SPECIALIST_RESULT_AUTHORIZATION => 'Specialist result authorization',
        PrivilegeGateway::CATEGORY_RECORD_CORRECTION_APPROVAL => 'Clinical record correction approval',
    ];

    public function mount(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $rows = [];

        if ($this->lookupUserId !== '') {
            try {
                $rows = app(PrivilegeGateway::class)->forUser($this->actor(), (int) $this->lookupUserId);
            } catch (Exception $e) {
                $this->errorMessage ??= 'Could not load privileges — Clinical may be unreachable.';
            }
        }

        return view('livewire.clinical.privileges-panel', ['rows' => $rows, 'categories' => self::CATEGORIES]);
    }

    public function lookup(): void
    {
        $this->validate(['lookupUserId' => ['required', 'numeric']], [], ['lookupUserId' => 'user id']);
        $this->errorMessage = null;
    }

    public function grant(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'userId' => ['required', 'numeric'],
            'effectiveStart' => ['required', 'string'],
        ], [], ['userId' => 'user id', 'effectiveStart' => 'effective start']);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(PrivilegeGateway::class)->grant($this->actor(), [
                'user_id' => (int) $this->userId,
                'category' => $this->category,
                'effective_start' => $this->effectiveStart,
                'effective_end' => $this->effectiveEnd ?: null,
                'credential_reference' => $this->credentialReference ?: null,
                'granted_by' => Auth::user()->name,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Privilege granted.';
        $this->lookupUserId = $this->userId;
        $this->reset(['userId', 'effectiveStart', 'effectiveEnd', 'credentialReference']);
    }

    public function suspend(string $privilegeId): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        if (trim($this->suspendReason) === '') {
            $this->errorMessage = 'Enter a reason before suspending.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(PrivilegeGateway::class)->suspend($this->actor(), $privilegeId, $this->suspendReason);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Privilege suspended.';
        $this->reset(['suspendReason']);
    }

    public function reinstate(string $privilegeId): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $this->errorMessage = null;

        try {
            app(PrivilegeGateway::class)->reinstate($this->actor(), $privilegeId);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Privilege reinstated.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
