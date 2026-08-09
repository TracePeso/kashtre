<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\ClinicalDictionaryGateway;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\CodedOption;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Rendered by ZtnaContextMiddleware when CareRelationshipChecker finds no
 * active relationship — the emergency override path, not a normal
 * navigation link.
 */
class BreakGlassRequest extends Component
{
    public string $clientId;

    public ?string $visitId = null;

    public string $reasonCode = '';

    public string $justificationNote = '';

    public function mount(string $clientId, ?string $visitId = null): void
    {
        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        return view('livewire.clinical.break-glass-request', [
            'reasons' => collect($this->reasons()),
        ]);
    }

    public function grant()
    {
        abort_unless(in_array('Trigger Break Glass Override', Auth::user()->permissions ?? []), 403);

        $this->validate(['reasonCode' => ['required', 'string']]);

        $reason = $this->findReason($this->reasonCode);

        if ($reason?->requires_free_text && empty($this->justificationNote)) {
            $this->addError('justificationNote', 'A justification note is required for this reason.');

            return;
        }

        app(CareAccessGateway::class)->grantBreakGlass(
            ClinicalActor::fromUser(Auth::user()),
            $this->clientId,
            $this->visitId,
            $this->reasonCode,
            $this->justificationNote ?: null,
        );

        return redirect()->route('clinical.observations.show', ['clientId' => $this->clientId, 'visit_id' => $this->visitId]);
    }

    /**
     * @return array<int, CodedOption>
     */
    private function reasons(): array
    {
        return app(ClinicalDictionaryGateway::class)
            ->reasonCodes(ClinicalActor::fromUser(Auth::user()), 'BREAK_GLASS');
    }

    private function findReason(string $reasonCode): ?CodedOption
    {
        foreach ($this->reasons() as $reason) {
            if ($reason->code === $reasonCode) {
                return $reason;
            }
        }

        return null;
    }
}
