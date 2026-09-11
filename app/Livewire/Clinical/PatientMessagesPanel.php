<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\EngagementGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * v6.1 Volume 13 (Engagement) — patient messaging only. See
 * EngagementController's docblock for why virtual care, remote monitoring
 * and proxy access are not built: each needs a real external vendor system
 * (telehealth provider, remote device manufacturer, identity verification
 * source) this deployment has none of.
 */
#[Lazy]
class PatientMessagesPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $body = '';

    public bool $isUrgent = false;

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $messages = [];

        try {
            $messages = app(EngagementGateway::class)->messages($this->actor());
        } catch (Exception $e) {
            $this->errorMessage ??= 'Could not load messages — Clinical may be unreachable.';
        }

        return view('livewire.clinical.patient-messages-panel', ['messages' => $messages]);
    }

    public function send(): void
    {
        abort_unless(in_array('clinical.patient_message.respond', Auth::user()->permissions ?? []), 403);

        $this->validate(['body' => ['required', 'string']]);

        $this->errorMessage = null;

        try {
            app(EngagementGateway::class)->sendMessage($this->actor(), $this->clientId, $this->body, $this->isUrgent);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = 'Message sent — charted regardless of delivery outcome, per the message row itself.';
        $this->reset(['body', 'isUrgent']);
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
