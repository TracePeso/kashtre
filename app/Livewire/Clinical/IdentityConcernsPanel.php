<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\IdentityConcernGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.1 Phase 2 — identity-concern reporting. Report only: raises a
 * flag for someone else to investigate, never merges or corrects a
 * patient record itself.
 */
#[Lazy]
class IdentityConcernsPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $concernType = 'DUPLICATE_RECORD_SUSPECTED';

    public string $description = '';

    /** @var array<string, string> concernId => resolution text being drafted */
    public array $resolutionInput = [];

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    private const CONCERN_TYPES = [
        'DUPLICATE_RECORD_SUSPECTED', 'MISMATCHED_DEMOGRAPHICS', 'WRISTBAND_MISMATCH',
        'NAME_SIMILARITY_RISK', 'POSSIBLE_WRONG_PATIENT_EVENT',
    ];

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $rows = [];

        try {
            $rows = app(IdentityConcernGateway::class)->forPatient($this->actor(), $this->clientId);
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load identity concerns — Clinical may be unreachable.';
        }

        return view('livewire.clinical.identity-concerns-panel', [
            'rows' => $rows,
            'concernTypes' => self::CONCERN_TYPES,
        ]);
    }

    public function report(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate(['description' => ['required', 'string', 'min:5']]);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(IdentityConcernGateway::class)->report($this->actor(), $this->clientId, [
                'concern_type' => $this->concernType,
                'description' => $this->description,
                'reported_by_user_id' => Auth::id(),
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Concern reported — this flags it for review, it does not merge or alter the record.';
        $this->reset(['description']);
    }

    public function resolve(string $concernId): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $resolution = trim($this->resolutionInput[$concernId] ?? '');

        if ($resolution === '') {
            $this->errorMessage = 'Enter the resolution first.';

            return;
        }

        $this->errorMessage = null;

        try {
            app(IdentityConcernGateway::class)->resolve($this->actor(), $concernId, $resolution);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        unset($this->resolutionInput[$concernId]);
        $this->resultMessage = 'Concern resolved.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
