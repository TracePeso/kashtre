<?php

namespace App\Livewire\Clinical;

use App\Services\Clinical\Api\ClinicalApiClient;
use Livewire\Component;

/**
 * A live status pill for the Clinical Module — "is it actually reachable
 * right now", not "is a URL saved in the settings form".
 *
 * Those are different questions and this session is the reason the distinction
 * matters: the encounter webhook and the entitlements call-out were both
 * silently dead for a stretch because CLINICAL_SERVICE_KEY was blank, and
 * nothing on the settings screen said so — the URL field just sat there
 * looking configured. This calls the same GET /api/v1/health endpoint used to
 * distinguish "Clinical is down" from "our key is wrong", and shows it next to
 * the form that configures the connection.
 */
class ClinicalModuleHealth extends Component
{
    public bool $checked = false;

    public bool $ok = false;

    public string $status = 'unreachable';

    /** @var array<string, mixed> */
    public array $checks = [];

    public bool $isConfigured = false;

    public ?string $checkedAt = null;

    /**
     * Deliberately does not call check() here. mount() runs during the
     * initial synchronous page render — a health probe belongs in a follow-up
     * request (see wire:init on the view), not on the critical path of every
     * page this widget sits on. A slow or hanging Clinical Module must not be
     * able to take Main's settings pages down with it.
     */
    public function mount(): void
    {
    }

    public function check(): void
    {
        $client = app(ClinicalApiClient::class);
        $this->isConfigured = $client->isConfigured();

        if (! $this->isConfigured) {
            $this->checked = true;
            $this->ok = false;
            $this->status = 'not configured';
            $this->checks = [];
            $this->checkedAt = now()->toIso8601String();

            return;
        }

        $result = $client->health();

        $this->checked = true;
        $this->ok = $result['ok'];
        $this->status = $result['status'];
        $this->checks = $result['checks'];
        $this->checkedAt = now()->toIso8601String();
    }

    public function render()
    {
        return view('livewire.clinical.clinical-module-health');
    }
}
