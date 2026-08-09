<?php

namespace App\Livewire\Clinical;

use App\Models\ClinicalBed;
use App\Models\ClinicalProcess;
use App\Models\ClinicalProcessExecution;
use App\Services\Clinical\ClinicalProcessEngine;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * SRD §4.2/§4.3: "Decision to Admit" is just starting the ADMISSION
 * process — there's no separate bridge mechanism, starting the process
 * IS the clinical intent trigger.
 */
class ClinicalProcessPanel extends Component
{
    public string $clientId;

    public ?string $visitId = null;

    public string $selectedProcessCode = '';

    public string $initiationNote = '';

    public ?int $selectedBedId = null;

    public string $stepNotes = '';

    public string $skipReason = '';

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $businessId = Auth::user()->business_id;

        $activeExecution = ClinicalProcessExecution::where('business_id', $businessId)
            ->where('client_id', $this->clientId)
            ->where('status', ClinicalProcessExecution::STATUS_IN_PROGRESS)
            ->with('process', 'currentStep')
            ->first();

        return view('livewire.clinical.clinical-process-panel', [
            'availableProcesses' => ClinicalProcess::query()
                ->where('is_active', true)
                ->where(function ($query) use ($businessId) {
                    $query->where('business_id', $businessId)->orWhereNull('business_id');
                })
                ->get()
                ->unique('process_code'),
            'activeExecution' => $activeExecution,
            'steps' => $activeExecution ? $activeExecution->process->steps : collect(),
            'stepExecutions' => $activeExecution
                ? $activeExecution->stepExecutions()->with('step')->get()->keyBy('step_id')
                : collect(),
            'availableBeds' => ClinicalBed::whereHas('ward', function ($query) use ($businessId) {
                $query->where('business_id', $businessId);
            })->where('operational_state', ClinicalBed::STATE_AVAILABLE)->with('ward')->get(),
            'history' => ClinicalProcessExecution::where('business_id', $businessId)
                ->where('client_id', $this->clientId)
                ->where('status', '!=', ClinicalProcessExecution::STATUS_IN_PROGRESS)
                ->with('process')
                ->orderByDesc('started_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function startProcess(): void
    {
        abort_unless(in_array('Progress Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $this->validate(['selectedProcessCode' => ['required', 'string']]);

        $user = Auth::user();
        $this->errorMessage = null;

        try {
            app(ClinicalProcessEngine::class)->startProcess(
                $this->selectedProcessCode,
                $user->business_id,
                $user->branch_id,
                $this->clientId,
                $this->visitId,
                $user->id,
                $this->initiationNote ?: null,
            );

            $this->selectedProcessCode = '';
            $this->initiationNote = '';
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function completeStep(int $executionId): void
    {
        abort_unless(in_array('Progress Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $execution = ClinicalProcessExecution::where('business_id', Auth::user()->business_id)->findOrFail($executionId);
        $this->assertRoleGateSatisfied($execution);
        $this->errorMessage = null;

        try {
            app(ClinicalProcessEngine::class)->completeStep(
                $execution,
                Auth::id(),
                $this->selectedBedId ? ['bed_id' => $this->selectedBedId] : [],
                $this->stepNotes ?: null,
            );

            $this->selectedBedId = null;
            $this->stepNotes = '';
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function skipStep(int $executionId): void
    {
        abort_unless(in_array('Progress Clinical Process Registry', Auth::user()->permissions ?? []), 403);

        $execution = ClinicalProcessExecution::where('business_id', Auth::user()->business_id)->findOrFail($executionId);
        $this->assertRoleGateSatisfied($execution);
        $this->errorMessage = null;

        try {
            app(ClinicalProcessEngine::class)->skipStep($execution, Auth::id(), $this->skipReason ?: null);
            $this->skipReason = '';
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * clinical_process_steps.required_role ('WARD_NURSE', 'CONSULTANT')
     * has no reliable job-role field to check against in this app (Title/
     * Qualification are free-text) — mapped onto the same User.permissions
     * mechanism every other access check in this module uses. An
     * unrecognized required_role fails closed rather than letting anyone
     * through.
     */
    private function assertRoleGateSatisfied(ClinicalProcessExecution $execution): void
    {
        $requiredRole = $execution->currentStep?->required_role;

        if (! $requiredRole) {
            return;
        }

        $permission = match ($requiredRole) {
            'WARD_NURSE' => 'Act As Ward Nurse (Clinical)',
            'CONSULTANT' => 'Act As Consultant (Clinical)',
            default => null,
        };

        abort_unless(
            $permission && in_array($permission, Auth::user()->permissions ?? []),
            403,
            "This step requires the '{$requiredRole}' role."
        );
    }
}
