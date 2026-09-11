<?php

namespace App\Livewire\Clinical;

use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\ClinicalBusinessContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Creates a clinical transition (process) and its steps.
 *
 * A dedicated component rather than a manifest entry in ClinicalDictionaries:
 * Clinical's process-registry API is two endpoints, not one — POST creates the
 * header, a separate PUT wholesale-replaces the step list — and, confirmed by
 * probing directly, there is no way to edit a header field or delete a process
 * once created (PATCH/PUT/DELETE on the header all 404; the steps PUT silently
 * ignores description/is_active if sent alongside it). The generic
 * single-resource create/update form this screen uses for every other
 * dictionary cannot represent that honestly, so this exists instead.
 */
class ProcessRegistryForm extends Component
{
    public string $processCode = '';

    public string $processName = '';

    public string $description = '';

    public bool $isActive = true;

    /** @var array<int, array<string, mixed>> */
    public array $steps = [];

    public ?string $errorMessage = null;

    public ?string $statusMessage = null;

    /** @var array<string, string> */
    public array $fieldErrors = [];

    /**
     * Roles Main can actually attribute an action to — see
     * ClinicalRequestContext::ROLE_MAP. A required_role outside this set locks
     * every step behind a role nobody here can ever hold.
     */
    public const KNOWN_ROLES = [
        'CONSULTANT', 'WARD_NURSE', 'SENIOR_CLINICIAN',
        'PRESCRIBER', 'ADMINISTERING_NURSE', 'DUTY_RESIDENT',
    ];

    public function mount(): void
    {
        $this->addStep();
    }

    public function addStep(): void
    {
        $this->steps[] = [
            'step_code' => '',
            'step_name' => '',
            'step_order' => count($this->steps) + 1,
            'is_mandatory' => true,
            'required_role' => '',
        ];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);

        foreach ($this->steps as $i => &$step) {
            $step['step_order'] = $i + 1;
        }
    }

    public function create(): void
    {
        abort_unless(
            ClinicalBusinessContext::isKashtreAdmin()
                || in_array('Manage Clinical Dictionaries', Auth::user()->permissions ?? [], true),
            403
        );

        $this->fieldErrors = [];
        $this->errorMessage = null;
        $this->statusMessage = null;

        if (ClinicalBusinessContext::requiresSelection()) {
            $this->errorMessage = 'Choose a facility above before creating a process.';

            return;
        }

        if ($this->processCode === '') {
            $this->fieldErrors['processCode'] = 'Process code is required.';
        }
        if ($this->processName === '') {
            $this->fieldErrors['processName'] = 'Process name is required.';
        }

        // A blank row left over from addStep() is not a step someone typed —
        // only rows with something in them are sent.
        $steps = array_values(array_filter(
            $this->steps,
            fn ($s) => $s['step_code'] !== '' || $s['step_name'] !== ''
        ));

        foreach ($steps as $i => $step) {
            if ($step['step_code'] === '') {
                $this->fieldErrors["steps.{$i}.step_code"] = 'Required.';
            }
            if ($step['step_name'] === '') {
                $this->fieldErrors["steps.{$i}.step_name"] = 'Required.';
            }
            if ($step['required_role'] === '') {
                $this->fieldErrors["steps.{$i}.required_role"] = 'Required.';
            }
        }

        if ($this->fieldErrors !== []) {
            return;
        }

        $businessId = ClinicalBusinessContext::effectiveBusinessId();
        $client = app(ClinicalApiClient::class);

        try {
            $created = $client->post('settings/process-registry', array_filter([
                'process_code' => strtoupper($this->processCode),
                'process_name' => $this->processName,
                'description' => $this->description ?: null,
                'is_active' => $this->isActive,
            ], fn ($v) => $v !== null), ['business_id' => $businessId]);

            $id = $created['id'] ?? null;

            if ($id && $steps !== []) {
                $client->put("settings/process-registry/{$id}/steps", [
                    'steps' => array_map(fn ($s) => [
                        'step_code' => strtoupper($s['step_code']),
                        'step_name' => $s['step_name'],
                        'step_order' => (int) $s['step_order'],
                        'is_mandatory' => (bool) $s['is_mandatory'],
                        'required_role' => $s['required_role'],
                    ], $steps),
                ], ['business_id' => $businessId]);
            }
        } catch (ClinicalApiException $e) {
            $fieldErrors = collect($e->errors())->filter(fn ($v) => is_array($v))->flatten();
            $this->errorMessage = $fieldErrors->isNotEmpty() ? $fieldErrors->first() : $e->getMessage();

            return;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $processCode = $created['process_code'] ?? strtoupper($this->processCode);
        $stepCount = count($steps);

        $this->reset(['processCode', 'processName', 'description', 'steps']);
        $this->isActive = true;
        $this->addStep();
        $this->statusMessage = "Process {$processCode} created with {$stepCount} step(s).";

        // The parent owns the row list; tell it to refresh rather than
        // duplicate that fetch here.
        $this->dispatch('process-registry-updated');
    }

    public function render()
    {
        return view('livewire.clinical.process-registry-form');
    }
}
