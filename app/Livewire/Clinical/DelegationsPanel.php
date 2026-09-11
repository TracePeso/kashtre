<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\DelegationGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 1 §10 — delegation, acting roles and cross-cover.
 * Explicit, time-limited, scoped, auditable (CLN-DEL-001).
 */
#[Lazy]
class DelegationsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $delegatorUserId = '';

    public string $delegateUserId = '';

    public string $permissionBundle = '';

    public string $scope = 'ASSIGNED_PATIENTS';

    public string $start = '';

    public string $end = '';

    public string $reason = '';

    public string $clientSpaceId = '';

    public string $lookupUserId = '';

    public string $revokeReason = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    private const SCOPES = ['SELF', 'ASSIGNED_PATIENTS', 'CARE_TEAM', 'CLIENT_SPACE', 'DEPARTMENT', 'FACILITY', 'ENTITY'];

    public function mount(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $rows = [];

        if ($this->lookupUserId !== '') {
            try {
                $rows = app(DelegationGateway::class)->forUser($this->actor(), (int) $this->lookupUserId);
            } catch (Exception $e) {
                $this->errorMessage ??= 'Could not load delegations — Clinical may be unreachable.';
            }
        }

        return view('livewire.clinical.delegations-panel', ['rows' => $rows, 'scopes' => self::SCOPES]);
    }

    public function lookup(): void
    {
        $this->validate(['lookupUserId' => ['required', 'numeric']], [], ['lookupUserId' => 'user id']);
        $this->errorMessage = null;
    }

    public function delegate(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'delegatorUserId' => ['required', 'numeric'],
            'delegateUserId' => ['required', 'numeric'],
            'permissionBundle' => ['required', 'string'],
            'start' => ['required', 'string'],
            'end' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:3'],
        ], [], [
            'delegatorUserId' => 'delegator', 'delegateUserId' => 'delegate',
            'permissionBundle' => 'permission bundle',
        ]);

        // CLN-DEL-003: delegated authority cannot outlive the stated
        // expiry — enforced server-side, but caught here early with a
        // clear message rather than a round trip for an obvious mistake.
        if (strtotime($this->end) !== false && strtotime($this->start) !== false && strtotime($this->end) < strtotime($this->start)) {
            $this->errorMessage = 'End must be after start.';

            return;
        }

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(DelegationGateway::class)->delegate($this->actor(), [
                'delegator_user_id' => (int) $this->delegatorUserId,
                'delegate_user_id' => (int) $this->delegateUserId,
                'permission_bundle' => $this->permissionBundle,
                'scope' => $this->scope,
                'start' => $this->start,
                'end' => $this->end,
                'reason' => $this->reason,
                'client_space_id' => $this->clientSpaceId !== '' ? (int) $this->clientSpaceId : null,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Delegation recorded.';
        $this->lookupUserId = $this->delegatorUserId;
        $this->reset(['delegatorUserId', 'delegateUserId', 'permissionBundle', 'start', 'end', 'reason', 'clientSpaceId']);
    }

    public function revoke(string $delegationId): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        if (trim($this->revokeReason) === '') {
            $this->errorMessage = 'Enter a reason before revoking.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(DelegationGateway::class)->revoke($this->actor(), $delegationId, $this->revokeReason);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Delegation revoked.';
        $this->reset(['revokeReason']);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
