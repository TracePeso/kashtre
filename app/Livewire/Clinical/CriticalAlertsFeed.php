<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CriticalAlertsGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The dashboard badge — API Integration Guide §10.5b. "Is anything waiting
 * for me right now", answered facility-wide rather than one chart at a
 * time. Polling only, per the guide's own note (no push channel yet) — this
 * component re-renders on `wire:poll`, not a live connection.
 */
class CriticalAlertsFeed extends Component
{
    public string $scope = CriticalAlertsGateway::SCOPE_MY_PATIENTS;

    public string $wardCode = '';

    /** SRD v6.1 Phase 8 — the alert currently in its review/action/close form. */
    public int|string|null $openAlertId = null;

    public string $openAlertStep = 'review';

    public string $stepInput = '';

    public ?string $stepError = null;

    public function mount(): void
    {
        abort_unless(in_array('View Ward Census', Auth::user()->permissions ?? []), 403);
    }

    public function render()
    {
        $wardCode = $this->wardCode !== '' ? $this->wardCode : null;

        if ($this->scope === CriticalAlertsGateway::SCOPE_WARD && $wardCode === null) {
            return view('livewire.clinical.critical-alerts-feed', ['feed' => null, 'needsWard' => true]);
        }

        try {
            $feed = app(CriticalAlertsGateway::class)->feed(
                $this->actor(),
                $this->scope,
                $wardCode,
            );
        } catch (ClinicalApiException $e) {
            $feed = ['alerts' => [], 'count' => 0, 'by_severity' => [], 'oldest_unacknowledged_at' => null];
        }

        return view('livewire.clinical.critical-alerts-feed', ['feed' => $feed, 'needsWard' => false]);
    }

    public function acknowledge(int|string $alertId): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        try {
            app(CriticalAlertsGateway::class)->acknowledge($this->actor(), $alertId);
        } catch (ClinicalApiException $e) {
            $this->dispatch('critical-alert-error', message: $e->getMessage());
        }
    }

    /**
     * SRD v6.1 Phase 8 — the closed-loop follow-up chain, one step open at a
     * time: review (refused ALERT_NOT_ACKNOWLEDGED if attempted first) →
     * action → close (refused ALERT_NOT_REVIEWED if attempted before review)
     * — each a distinct, separately recorded state, not one flag.
     */
    public function openStep(int|string $alertId, string $step): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);
        abort_unless(in_array($step, ['review', 'action', 'close'], true), 422);

        $this->openAlertId = $alertId;
        $this->openAlertStep = $step;
        $this->stepInput = '';
        $this->stepError = null;
    }

    public function closeStep(): void
    {
        $this->openAlertId = null;
    }

    public function submitStep(): void
    {
        abort_unless(in_array('Manage Ward Census', Auth::user()->permissions ?? []), 403);

        $label = match ($this->openAlertStep) {
            'review' => 'review notes',
            'action' => 'the action taken',
            'close' => 'a closure reason',
            default => 'a value',
        };

        $this->validate(['stepInput' => ['required', 'string', 'min:3']], [], ['stepInput' => $label]);

        if ($this->openAlertId === null) {
            return;
        }

        $gateway = app(CriticalAlertsGateway::class);
        $this->stepError = null;

        try {
            match ($this->openAlertStep) {
                'review' => $gateway->review($this->actor(), $this->openAlertId, $this->stepInput),
                'action' => $gateway->action($this->actor(), $this->openAlertId, $this->stepInput),
                'close' => $gateway->close($this->actor(), $this->openAlertId, $this->stepInput),
            };
        } catch (ClinicalApiException $e) {
            $this->stepError = $e->getMessage();

            return;
        }

        $this->openAlertId = null;
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
