<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CareTransitionsGateway;
use App\Contracts\Clinical\WardCensusGateway;
use App\Models\ClinicalCareTransition;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\CareTransitionRecord;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Care Transitions — API_GUIDE_V6.1 §1 (Volume 8), the one v6.1 volume that
 * ships as a fully live endpoint group. Sits alongside ClinicalProcessPanel's
 * older `/clinical/transitions/*` step-execution flow, not in place of it:
 * that flow still does the actual bed allocation, order halting and chart
 * locking; this one adds a readiness gate, attested discharge documents, and
 * observation-plan re-anchoring.
 *
 * There is no list/recheck endpoint for this volume, so `clinical_care_
 * transitions` is Main's own memory of what it started — never the source
 * of truth for status, only enough to survive a page reload. See
 * CareTransitionsGateway's own docblock.
 */
#[Lazy]
class CareTransitionsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    private const TYPES = [
        'INTERNAL_TRANSFER' => 'Internal Transfer',
        'INTERFACILITY_TRANSFER' => 'Interfacility Transfer',
        'DISCHARGE' => 'Discharge',
        'REFERRAL' => 'Referral',
        'TEMPORARY_LEAVE' => 'Temporary Leave',
        'DEATH' => 'Death',
    ];

    public string $clientId;

    public ?string $visitId = null;

    public string $transitionType = '';

    public string $fromWardCode = '';

    public string $toWardCode = '';

    public string $destinationOrganizationId = '';

    public string $plannedAt = '';

    public string $reason = '';

    public ?string $activeTransitionPublicId = null;

    public string $movementId = '';

    public string $effectiveAt = '';

    /** @var array<int, array{code: string, content: string}> */
    public array $sections = [];

    public string $newSectionCode = '';

    public string $newSectionContent = '';

    public ?string $lastDocumentPublicId = null;

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Care Transitions', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $actor = $this->actor();

        $recentTransitions = ClinicalCareTransition::where('business_id', $actor->businessId)
            ->where('client_id', $this->clientId)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $activeTransition = null;

        if ($this->activeTransitionPublicId) {
            try {
                $activeTransition = app(CareTransitionsGateway::class)->show($actor, $this->activeTransitionPublicId);
                $this->mirrorTransition($activeTransition);
            } catch (Exception $e) {
                // Fail-soft, same posture as every other panel here — a read
                // failure should not hide the rest of the chart.
                $this->errorMessage ??= 'Could not refresh this transition — it may still be shown with stale data.';
            }
        }

        $wards = [];

        try {
            $wards = app(WardCensusGateway::class)->wards($actor);
        } catch (Exception $e) {
            // Same fail-soft posture — the ward pickers are optional inputs
            // (from/to client_space is nullable), not a hard requirement.
        }

        return view('livewire.clinical.care-transitions-panel', [
            'types' => self::TYPES,
            'wards' => $wards,
            'recentTransitions' => $recentTransitions,
            'activeTransition' => $activeTransition,
        ]);
    }

    public function start(): void
    {
        // Must be the literal string Clinical checks — see AccessTrait's
        // comment on this permission group for why.
        abort_unless(in_array('clinical.transition.initiate', Auth::user()->permissions ?? []), 403);

        $this->validate(['transitionType' => ['required', 'string']], [], ['transitionType' => 'transition type']);

        $this->errorMessage = null;
        $this->resultMessage = null;
        $actor = $this->actor();

        try {
            $record = app(CareTransitionsGateway::class)->start(
                $actor,
                $this->clientId,
                $this->visitId,
                $this->transitionType,
                $this->resolveClientSpaceId($actor, $this->fromWardCode),
                $this->resolveClientSpaceId($actor, $this->toWardCode),
                $this->destinationOrganizationId ?: null,
                $this->plannedAt ?: null,
                $this->reason ?: null,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->mirrorTransition($record);
        $this->activeTransitionPublicId = $record->publicId;

        $this->resultMessage = $record->isBlocked()
            ? 'Transition started — readiness check is blocking. See the items below.'
            : 'Transition started — ready to proceed.';

        $this->reset(['transitionType', 'fromWardCode', 'toWardCode', 'destinationOrganizationId', 'plannedAt', 'reason']);
    }

    public function selectTransition(string $publicId): void
    {
        $this->activeTransitionPublicId = $publicId;
        $this->resultMessage = null;
        $this->errorMessage = null;
    }

    public function completeInternalTransfer(): void
    {
        abort_unless(in_array('clinical.transition.authorize', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'movementId' => ['required', 'numeric'],
            'effectiveAt' => ['required', 'string'],
        ], [], ['movementId' => 'bed movement id', 'effectiveAt' => 'effective at']);

        if (! $this->activeTransitionPublicId) {
            $this->errorMessage = 'Select a transition first.';

            return;
        }

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            $record = app(CareTransitionsGateway::class)->completeInternalTransfer(
                $this->actor(),
                $this->activeTransitionPublicId,
                (int) $this->movementId,
                $this->effectiveAt,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->mirrorTransition($record);
        $this->resultMessage = 'Internal transfer completed.';
        $this->reset(['movementId', 'effectiveAt']);
    }

    public function addSectionRow(): void
    {
        $this->validate([
            'newSectionCode' => ['required', 'string'],
            'newSectionContent' => ['required', 'string'],
        ], [], ['newSectionCode' => 'section code', 'newSectionContent' => 'content']);

        $this->sections[] = ['code' => $this->newSectionCode, 'content' => $this->newSectionContent];
        $this->reset(['newSectionCode', 'newSectionContent']);
    }

    public function removeSectionRow(int $index): void
    {
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);
    }

    public function issueDischargeDocument(): void
    {
        abort_unless(in_array('clinical.discharge.attest', Auth::user()->permissions ?? []), 403);

        if (! $this->activeTransitionPublicId) {
            $this->errorMessage = 'Select a transition first.';

            return;
        }

        if ($this->sections === []) {
            $this->errorMessage = 'Add at least one section (e.g. HOSPITAL_COURSE, DISCHARGE_MEDICATIONS, FOLLOW_UP).';

            return;
        }

        $this->errorMessage = null;
        $this->resultMessage = null;

        $sectionsObject = collect($this->sections)->pluck('content', 'code')->all();

        try {
            $document = app(CareTransitionsGateway::class)->issueDischargeDocument(
                $this->actor(),
                $this->activeTransitionPublicId,
                $sectionsObject,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->lastDocumentPublicId = $document->publicId;
        $this->resultMessage = "Discharge document issued and attested (version {$document->versionNo}). It is immutable — a correction means issuing a new one.";
        $this->sections = [];
    }

    private function resolveClientSpaceId(ClinicalActor $actor, string $wardCode): ?int
    {
        if ($wardCode === '') {
            return null;
        }

        try {
            $census = app(WardCensusGateway::class)->census($actor, $wardCode);
        } catch (Exception $e) {
            return null;
        }

        // A ward may span several client_spaces; the first bed's space is
        // the same pragmatic best-effort choice WardCensus::spaceIdForOverflow()
        // already makes elsewhere in this codebase for the same problem
        // (adding a bed is keyed by numeric space id; nothing else carries it).
        return $census?->spaceIdForOverflow();
    }

    private function mirrorTransition(CareTransitionRecord $record): void
    {
        $actor = $this->actor();

        ClinicalCareTransition::updateOrCreate(
            ['public_id' => $record->publicId],
            [
                'business_id' => $actor->businessId,
                'branch_id' => $actor->branchId,
                'client_id' => $this->clientId,
                'visit_id' => $this->visitId,
                'transition_type' => $record->transitionType,
                'status' => $record->status,
                'planned_at' => $record->plannedAt,
                'effective_at' => $record->effectiveAt,
                'completed_at' => $record->completedAt,
                'record_version' => $record->recordVersion,
                'created_by_user_id' => $actor->userId,
            ],
        );
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
