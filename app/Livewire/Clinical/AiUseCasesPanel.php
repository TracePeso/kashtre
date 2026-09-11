<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\AiUseCaseGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * v6.1 Volume 11 — the API guide's own §3 names this exact gap: no exposed
 * endpoint to register or deactivate/prohibit an AI use case, even though
 * AiUseCaseGovernor already enforces it on the 5 existing AI endpoints
 * (extract-observations, extract-intent, icd11-suggest,
 * summarize-observations, recommend-protocol).
 */
class AiUseCasesPanel extends Component
{
    public string $code = '';

    public string $name = '';

    public string $riskLevel = 'MODERATE';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(in_array('clinical.ai.govern', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $rows = [];

        try {
            $rows = app(AiUseCaseGateway::class)->list($this->actor());
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load AI use cases — Clinical may be unreachable.';
        }

        return view('livewire.clinical.ai-use-cases-panel', ['rows' => $rows]);
    }

    public function register(): void
    {
        $this->validate([
            'code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'riskLevel' => ['required', 'string', 'in:LOW,MODERATE,HIGH,PROHIBITED'],
        ]);

        $this->errorMessage = null;

        try {
            app(AiUseCaseGateway::class)->register($this->actor(), [
                'code' => $this->code,
                'name' => $this->name,
                'risk_level' => $this->riskLevel,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = "Use case [{$this->code}] registered — active.";
        $this->reset(['code', 'name']);
        $this->riskLevel = 'MODERATE';
    }

    public function setStatus(string $code, string $status): void
    {
        $this->errorMessage = null;

        try {
            app(AiUseCaseGateway::class)->setStatus($this->actor(), $code, $status);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = "Use case [{$code}] set to {$status}.";
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
