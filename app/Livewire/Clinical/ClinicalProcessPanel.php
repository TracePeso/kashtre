<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\ClinicalDictionaryGateway;
use App\Contracts\Clinical\ClinicalSettingsGateway;
use App\Contracts\Clinical\ProcessExecutionGateway;
use App\Contracts\Clinical\WardCensusGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD §4.2/§4.3: major clinical transitions.
 *
 * Talks only to ProcessExecutionGateway / ClinicalSettingsGateway /
 * ClinicalDictionaryGateway / WardCensusGateway, so this works unchanged
 * under either CLINICAL_DRIVER.
 *
 * Three distinct "this step doesn't apply" paths, not one:
 *   Complete → the ordinary path, records effects (bed allocation etc).
 *   Skip     → records SKIPPED instead, no effects. Blocked the same way
 *              Complete can be, if the step is mandatory.
 *   Abandon  → closes the whole instance, not just one step.
 * Completing or skipping a step can be refused with PROCESS_STEP_BLOCKED
 * (wrong role, mandatory-and-skipped, or an unmet completion_rule) — that
 * refusal names why and accepts an audited override, same pattern as a
 * CDSS block elsewhere in this module.
 */
#[Lazy]
class ClinicalProcessPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    /**
     * Confirmed against Clinical's own TransitionStepEffects source
     * 2026-08-22 — the only step codes whose completion payload takes a
     * bed_id at all. Every other step code ignores it entirely.
     */
    private const BED_STEP_CODES = ['BED_ALLOCATION', 'TRANSFER_REQUEST', 'BED_CUSTODY_TRANSFER'];

    public string $clientId;

    public ?string $visitId = null;

    public string $selectedProcessCode = '';

    public string $initiationNote = '';

    public string $stepNotes = '';

    public string $selectedBedId = '';

    /** Ward picked before a bed can be chosen for a bed-related step — mirrors admitTargetWardCode's pattern. */
    public string $stepTargetWardCode = '';

    /** DEATH_CERTIFICATE_SIGN_OFF only — a distinct field from stepNotes/completion_note, see ProcessExecutionGateway. */
    public string $lockNote = '';

    /** REFERRAL_SIGN_OFF only. */
    public string $exportFormat = '';

    public string $abandonReasonCode = '';

    public string $abandonNote = '';

    public bool $showDecisionToAdmit = false;

    public string $admitTargetWardCode = '';

    public string $admitTargetSpecialty = '';

    public string $admitNote = '';

    public string $admitBedId = '';

    /** Set when a step refuses with PROCESS_STEP_BLOCKED, until overridden or cancelled. */
    public ?string $blockedStepCode = null;

    /** @var array<int, string> */
    public array $blockedReasons = [];

    public bool $blockedWasSkip = false;

    public string $overrideReasonCode = '';

    public string $overrideNote = '';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $actor = $this->actor();

        $instances = [];

        try {
            $instances = app(ProcessExecutionGateway::class)->forPatient($actor, $this->clientId);
        } catch (Exception $e) {
            // A history read failing should not hide the rest of the chart —
            // same fail-soft posture as DiagnosesPanel.
        }

        $activeInstance = collect($instances)->first(fn ($i) => $i->isActive());
        $history = collect($instances)->reject(fn ($i) => $i->isActive())->take(10);

        $wards = [];
        $admitWardBeds = collect();

        if ($this->showDecisionToAdmit) {
            try {
                $wards = app(WardCensusGateway::class)->wards($actor);

                if ($this->admitTargetWardCode !== '') {
                    $census = app(WardCensusGateway::class)->census($actor, $this->admitTargetWardCode);
                    $admitWardBeds = collect($census?->beds ?? [])
                        ->filter(fn ($bed) => $bed->operational_state === 'AVAILABLE');
                }
            } catch (Exception $e) {
                // Same fail-soft posture — a ward-list failure should not
                // hide the rest of this panel.
            }
        }

        // The current step's own ward+bed picker — only fetched when the
        // step actually in front of the clinician is one of the three that
        // take a bed_id at all (see BED_STEP_CODES), same reasoning as the
        // Decision-to-Admit picker above but keyed off stepTargetWardCode
        // so the two pickers never fight over the same state.
        $stepWards = [];
        $stepBedOptions = collect();
        $currentStepCode = $activeInstance?->nextStep['step_code'] ?? null;

        if ($currentStepCode && in_array($currentStepCode, self::BED_STEP_CODES, true)) {
            try {
                $stepWards = app(WardCensusGateway::class)->wards($actor);

                if ($this->stepTargetWardCode !== '') {
                    $census = app(WardCensusGateway::class)->census($actor, $this->stepTargetWardCode);
                    $stepBedOptions = collect($census?->beds ?? [])
                        ->filter(fn ($bed) => $bed->operational_state === 'AVAILABLE');
                }
            } catch (Exception $e) {
                // Same fail-soft posture.
            }
        }

        return view('livewire.clinical.clinical-process-panel', [
            'availableProcesses' => collect(
                app(ClinicalSettingsGateway::class)->list($actor, 'settings/process-registry', ['status' => 'ACTIVE'])
            ),
            'activeInstance' => $activeInstance,
            'history' => $history,
            'abandonReasons' => collect(app(ClinicalDictionaryGateway::class)->reasonCodes($actor, 'PROCESS_ABANDONMENT')),
            'overrideReasons' => collect(app(ClinicalDictionaryGateway::class)->reasonCodes($actor, 'PROCESS_OVERRIDE')),
            'wards' => $wards,
            'admitWardBeds' => $admitWardBeds,
            'stepWards' => $stepWards,
            'stepBedOptions' => $stepBedOptions,
        ]);
    }

    public function startProcess(): void
    {
        abort_unless(in_array('Progress Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $this->validate(['selectedProcessCode' => ['required', 'string']]);

        $this->errorMessage = null;

        try {
            app(ProcessExecutionGateway::class)->start(
                $this->actor(),
                $this->selectedProcessCode,
                $this->clientId,
                $this->visitId,
                $this->initiationNote ?: null,
            );

            $this->selectedProcessCode = '';
            $this->initiationNote = '';
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleDecisionToAdmit(): void
    {
        $this->showDecisionToAdmit = ! $this->showDecisionToAdmit;
    }

    public function decisionToAdmit(): void
    {
        abort_unless(in_array('Progress Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $this->validate(['admitTargetWardCode' => ['required', 'string']], [], ['admitTargetWardCode' => 'target ward']);

        if (! $this->visitId) {
            $this->errorMessage = 'No visit is open for this patient.';

            return;
        }

        $this->errorMessage = null;

        try {
            $instance = app(ProcessExecutionGateway::class)->decisionToAdmit(
                $this->actor(),
                $this->clientId,
                $this->visitId,
                $this->admitTargetWardCode,
                $this->admitTargetSpecialty ?: null,
                $this->admitNote ?: null,
                $this->admitBedId !== '' ? (int) $this->admitBedId : null,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = $instance->requiresBedAllocation
            ? 'Admission requested — the receiving ward still needs to find a bed.'
            : "Admission requested — bed {$instance->bedReserved} held.";

        $this->reset(['showDecisionToAdmit', 'admitTargetWardCode', 'admitTargetSpecialty', 'admitNote', 'admitBedId']);
    }

    public function completeStep(int|string $instanceId, string $stepCode): void
    {
        $this->runStep($instanceId, $stepCode, skip: false);
    }

    public function skipStep(int|string $instanceId, string $stepCode): void
    {
        $this->runStep($instanceId, $stepCode, skip: true);
    }

    private function runStep(int|string $instanceId, string $stepCode, bool $skip): void
    {
        abort_unless(in_array('Progress Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $this->errorMessage = null;

        try {
            $instance = app(ProcessExecutionGateway::class)->executeStep(
                $this->actor(),
                $instanceId,
                $stepCode,
                skip: $skip,
                completionNote: $this->stepNotes ?: null,
                bedId: $this->selectedBedId !== '' ? (int) $this->selectedBedId : null,
                lockNote: $this->lockNote ?: null,
                exportFormat: $this->exportFormat ?: null,
            );

            $this->resetStepFields();
            $this->clearBlock();

            // Care Transitions (v6.1 §1)'s "Complete an internal transfer"
            // needs this id as evidence the bed move happened — this step
            // result is the only place it is ever surfaced, so it is shown
            // even though this panel does nothing else with it.
            $movementId = $instance->stepEffects['movement_id'] ?? null;

            $this->statusMessage = match (true) {
                $skip => 'Step skipped.',
                $movementId !== null => "Step completed — bed movement #{$movementId} (needed to complete an internal transfer under Care Transitions).",
                default => 'Step completed.',
            };
        } catch (ClinicalRuleRefusedException $e) {
            if ($e->isProcessStepBlocked()) {
                $this->blockedStepCode = $stepCode;
                $this->blockedReasons = $e->blockedBy();
                $this->blockedWasSkip = $skip;

                return;
            }

            $this->errorMessage = $e->getMessage();
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /** Resends the blocked step with the override reason the clinician supplied. */
    public function overrideAndRetry(int|string $instanceId): void
    {
        abort_unless(in_array('Progress Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $this->validate(['overrideReasonCode' => ['required', 'string']], [], ['overrideReasonCode' => 'override reason']);

        if (! $this->blockedStepCode) {
            return;
        }

        $this->errorMessage = null;
        $wasSkip = $this->blockedWasSkip;

        try {
            app(ProcessExecutionGateway::class)->executeStep(
                $this->actor(),
                $instanceId,
                $this->blockedStepCode,
                skip: $wasSkip,
                completionNote: $this->stepNotes ?: null,
                bedId: $this->selectedBedId !== '' ? (int) $this->selectedBedId : null,
                overrideReasonCode: $this->overrideReasonCode,
                overrideNote: $this->overrideNote ?: null,
                lockNote: $this->lockNote ?: null,
                exportFormat: $this->exportFormat ?: null,
            );

            $this->resetStepFields();
            $this->clearBlock();
            $this->statusMessage = $wasSkip ? 'Step skipped (overridden).' : 'Step completed (overridden).';
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function cancelOverride(): void
    {
        $this->clearBlock();
    }

    private function resetStepFields(): void
    {
        $this->stepNotes = '';
        $this->selectedBedId = '';
        $this->stepTargetWardCode = '';
        $this->lockNote = '';
        $this->exportFormat = '';
    }

    private function clearBlock(): void
    {
        $this->blockedStepCode = null;
        $this->blockedReasons = [];
        $this->blockedWasSkip = false;
        $this->overrideReasonCode = '';
        $this->overrideNote = '';
    }

    public function abandon(int|string $instanceId): void
    {
        abort_unless(in_array('Progress Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $this->validate(['abandonReasonCode' => ['required', 'string']]);

        $this->errorMessage = null;

        try {
            app(ProcessExecutionGateway::class)->abandon(
                $this->actor(),
                $instanceId,
                $this->abandonReasonCode,
                $this->abandonNote ?: null,
            );

            $this->abandonReasonCode = '';
            $this->abandonNote = '';
            $this->statusMessage = 'Process abandoned.';
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
