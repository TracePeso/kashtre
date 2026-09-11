<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CareAccessGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * v6.1 Volume 9's genuinely new capability: independent retrospective review
 * of a break-glass episode. Gated separately from "Trigger Break Glass
 * Override" — reviewing is a governance act (Medical Director-style role),
 * not something the clinician who broke glass does to themselves.
 *
 * BreakGlassEpisode has no list/lookup endpoint (confirmed against
 * API_GUIDE_V6.1 §2), so the episode id only ever reaches here as the flash
 * value BreakGlassRequest::grant() leaves right after a grant — this field
 * is pre-filled from that but stays editable for a reviewer who already
 * knows an id from elsewhere (the audit trail, a verbal handoff).
 */
#[Lazy]
class BreakGlassEpisodeReview extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public ?string $episodeId = null;

    public string $outcome = '';

    public string $finding = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(?string $episodeId = null): void
    {
        $this->episodeId = $episodeId ?? '';
    }

    public function render()
    {
        return view('livewire.clinical.break-glass-episode-review');
    }

    public function review(): void
    {
        // Must be the literal string Clinical checks — see AccessTrait's
        // comment on this permission for why.
        abort_unless(in_array('clinical.break_glass.review', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'episodeId' => ['required', 'string'],
            'outcome' => ['required', 'string', 'in:JUSTIFIED,UNJUSTIFIED,INCONCLUSIVE'],
            'finding' => ['required', 'string'],
        ], [], ['episodeId' => 'episode id']);

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(CareAccessGateway::class)->reviewBreakGlass(
                ClinicalActor::fromUser(Auth::user()),
                $this->episodeId,
                $this->outcome,
                $this->finding,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Review recorded.';
        $this->outcome = '';
        $this->finding = '';
    }
}
