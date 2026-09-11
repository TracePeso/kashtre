<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\ClientSpaceAssignmentGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 1 §4 — client-space assignment governance: who may work
 * in a ward. Live on Clinical's side, but REQUIRE_CLIENT_SPACE_ASSIGNMENT
 * defaults to false there, so this is the primitive, not an enforced gate.
 */
#[Lazy]
class ClientSpaceAssignmentsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $userId = '';

    public string $clientSpaceId = '';

    public string $assignmentType = ClientSpaceAssignmentGateway::TYPE_PERMANENT;

    public string $effectiveStart = '';

    public string $effectiveEnd = '';

    public string $lookupUserId = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $rows = [];

        if ($this->lookupUserId !== '') {
            try {
                $rows = app(ClientSpaceAssignmentGateway::class)->forUser($this->actor(), (int) $this->lookupUserId);
            } catch (Exception $e) {
                $this->errorMessage ??= 'Could not load assignments — Clinical may be unreachable.';
            }
        }

        return view('livewire.clinical.client-space-assignments-panel', [
            'rows' => $rows,
            'types' => [
                ClientSpaceAssignmentGateway::TYPE_PERMANENT => 'Permanent',
                ClientSpaceAssignmentGateway::TYPE_ROSTER_DRIVEN => 'Roster-driven',
                ClientSpaceAssignmentGateway::TYPE_TEMPORARY => 'Temporary',
                ClientSpaceAssignmentGateway::TYPE_ROTATIONAL => 'Rotational',
                ClientSpaceAssignmentGateway::TYPE_RELIEF => 'Relief',
                ClientSpaceAssignmentGateway::TYPE_CROSS_COVER => 'Cross-cover',
                ClientSpaceAssignmentGateway::TYPE_REMOTE_SERVICE => 'Remote service',
                ClientSpaceAssignmentGateway::TYPE_EMERGENCY => 'Emergency',
            ],
        ]);
    }

    public function lookup(): void
    {
        $this->validate(['lookupUserId' => ['required', 'numeric']], [], ['lookupUserId' => 'user id']);
        $this->errorMessage = null;
    }

    public function assign(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'userId' => ['required', 'numeric'],
            'clientSpaceId' => ['required', 'numeric'],
            'effectiveStart' => ['required', 'string'],
        ], [], ['userId' => 'user id', 'clientSpaceId' => 'client space id', 'effectiveStart' => 'effective start']);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(ClientSpaceAssignmentGateway::class)->assign($this->actor(), [
                'user_id' => (int) $this->userId,
                'client_space_id' => (int) $this->clientSpaceId,
                'assignment_type' => $this->assignmentType,
                'effective_start' => $this->effectiveStart,
                'effective_end' => $this->effectiveEnd ?: null,
                'approving_authority' => Auth::user()->name,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Assignment recorded.';
        $this->lookupUserId = $this->userId;
        $this->reset(['userId', 'clientSpaceId', 'effectiveStart', 'effectiveEnd']);
    }

    public function end(string $assignmentId): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $this->errorMessage = null;

        try {
            app(ClientSpaceAssignmentGateway::class)->end($this->actor(), $assignmentId, 'Ended from Client-Space Assignments panel.');
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Assignment ended.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
