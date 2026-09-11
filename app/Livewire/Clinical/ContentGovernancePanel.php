<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\ContentGovernanceGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * v6.1 Volume 14 — controlled clinical content (protocols, order sets,
 * educational material) versioning and validation. Promotion to a live
 * environment is not wired here — see ContentGovernanceController's
 * docblock (no shipped Action creates the PromotionPackage it needs).
 */
class ContentGovernancePanel extends Component
{
    public string $contentType = '';

    public string $contentCode = '';

    public string $versionNo = '1';

    public string $payloadJson = '{}';

    public ?string $lastVersionId = null;

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(in_array('clinical.content.create', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $rows = [];

        try {
            $rows = app(ContentGovernanceGateway::class)->list($this->actor(), $this->contentType ?: null);
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load content versions — Clinical may be unreachable.';
        }

        return view('livewire.clinical.content-governance-panel', ['rows' => $rows]);
    }

    public function createVersion(): void
    {
        $this->validate([
            'contentType' => ['required', 'string'],
            'contentCode' => ['required', 'string'],
            'versionNo' => ['required', 'numeric'],
            'payloadJson' => ['required', 'string'],
        ]);

        $payload = json_decode($this->payloadJson, true);

        if (! is_array($payload)) {
            $this->addError('payloadJson', 'Must be valid JSON.');

            return;
        }

        $this->errorMessage = null;

        try {
            $version = app(ContentGovernanceGateway::class)->createVersion($this->actor(), [
                'content_type' => $this->contentType,
                'content_code' => $this->contentCode,
                'version_no' => (int) $this->versionNo,
                'payload' => $payload,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->lastVersionId = $version['public_id'] ?? null;
        $this->resultMessage = 'Content version created (DRAFT).';
    }

    public function validateVersion(string $versionId): void
    {
        $this->errorMessage = null;

        try {
            $run = app(ContentGovernanceGateway::class)->validateVersion($this->actor(), $versionId);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Validation run: '.($run['overall_outcome'] ?? 'unknown').'.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
