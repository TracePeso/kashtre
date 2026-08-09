<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\ClinicalDictionaryGateway;
use App\Contracts\Clinical\MarGateway;
use App\Contracts\Clinical\MedicationOrdersGateway;
use App\Services\Clinical\Api\Exceptions\ClinicalAccessDeniedException;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException;
use App\Services\Clinical\Api\Exceptions\ClinicalSafetyBlockException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Prescribing and administration in one panel — two views onto the same
 * order/dose data.
 *
 * All clinical work goes through MedicationOrdersGateway / MarGateway /
 * ClinicalDictionaryGateway, so this component is identical whether the data
 * lives locally or in the Clinical Module.
 *
 * The two refusals this has to handle are not errors, they are clinical
 * conversations:
 *
 *   CDSS_HARD_BLOCK              show the blocks, take an override reason from
 *                                a senior clinician, resend.
 *   EXTERNAL_FULFILMENT_REQUIRED nothing in the catalogue matched; confirm to
 *                                generate a referral instead of leaving the
 *                                clinician unable to prescribe at all.
 */
class MedicationOrdersPanel extends Component
{
    public string $clientId;

    public ?string $visitId = null;

    public string $drugSearch = '';

    public string $strength = '';

    public string $doseAmount = '';

    public string $routeCode = 'PO';

    public string $frequencyCode = 'BID';

    public string $clinicalIndication = '';

    public bool $isNephrotoxic = false;

    public string $maxMgPerKg = '';

    public string $overrideReason = '';

    /** @var array{hard_blocks: array, warnings: array}|null */
    public ?array $pendingSafety = null;

    /** Set when the catalogue matched nothing and the clinician must confirm a referral. */
    public bool $awaitingExternalConfirmation = false;

    public string $holdReasonCode = '';

    public string $holdNotes = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    /**
     * One key per logical prescription, held across the CDSS-override and
     * external-fulfilment retries — those are continuations of a single
     * clinical decision, not new ones. Regenerated only once an order is
     * actually placed. See API Integration Guide §7.
     */
    public string $idempotencyKey = '';

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Medication Orders', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
        $this->idempotencyKey = (string) Str::uuid();
    }

    public function render()
    {
        $actor = $this->actor();
        $dictionary = app(ClinicalDictionaryGateway::class);

        return view('livewire.clinical.medication-orders-panel', [
            'routes' => collect($dictionary->routes($actor)),
            'frequencies' => collect($dictionary->frequencies($actor)),
            'overrideReasons' => collect($dictionary->reasonCodes($actor, 'CDSS_OVERRIDE')),
            'holdReasons' => collect($dictionary->reasonCodes($actor, 'MAR_WASTAGE')),
            'activeOrders' => collect(app(MedicationOrdersGateway::class)->activeOrders($actor, $this->clientId)),
            'dueDoses' => collect(app(MarGateway::class)->dosesForPatient($actor, $this->clientId, 'DUE')),
        ]);
    }

    public function prescribe(): void
    {
        abort_unless(in_array('Prescribe Medication Orders', Auth::user()->permissions ?? []), 403);

        // Advisory only under the API driver — Clinical enforces the
        // off-premises restriction itself and will refuse with
        // 403 ZTNA_OFFSITE_MUTATION_RESTRICTED regardless. Checking here just
        // gives a better message than a bare 403.
        abort_unless(
            app(CareAccessGateway::class)->canMutateFromCurrentLocation(),
            403,
            'Live medication ordering is prohibited off-premises.'
        );

        $this->validate([
            'drugSearch' => ['required', 'string'],
            'doseAmount' => ['required', 'numeric', 'gt:0'],
            'routeCode' => ['required', 'string'],
            'frequencyCode' => ['required', 'string'],
        ]);

        $this->errorMessage = null;

        $draft = [
            'requested_term' => $this->drugSearch,
            'strength_descriptor' => $this->strength ?: null,
            'dose_amount' => (float) $this->doseAmount,
            'route_code' => $this->routeCode,
            'frequency_code' => $this->frequencyCode,
            'clinical_indication' => $this->clinicalIndication ?: null,
            'is_nephrotoxic' => $this->isNephrotoxic,
            'max_mg_per_kg' => $this->maxMgPerKg !== '' ? (float) $this->maxMgPerKg : null,
        ];

        try {
            $order = app(MedicationOrdersGateway::class)->prescribe(
                actor: $this->actor(),
                patientId: $this->clientId,
                visitId: $this->visitId,
                draft: $draft,
                overrideReasonCode: $this->overrideReason ?: null,
                overrideNote: null,
                confirmExternalFulfilment: $this->awaitingExternalConfirmation,
                idempotencyKey: $this->idempotencyKey,
            );
        } catch (ClinicalSafetyBlockException $e) {
            // Surface the blocks and wait for an override reason. The
            // clinician must hold a senior role to supply one, and the
            // override is written to the immutable audit trail.
            abort_unless(in_array('Override CDSS Safety Block', Auth::user()->permissions ?? []), 403);

            $this->pendingSafety = [
                'hard_blocks' => $e->hardBlocks(),
                'warnings' => $e->warnings(),
            ];

            return;
        } catch (ClinicalRuleRefusedException $e) {
            if ($e->requiresExternalFulfilment()) {
                $this->awaitingExternalConfirmation = true;
                $this->errorMessage = $e->getMessage();

                return;
            }

            $this->errorMessage = $e->getMessage();

            return;
        } catch (ClinicalAccessDeniedException $e) {
            $this->errorMessage = $e->requiresBreakGlass()
                ? $e->getMessage().' Use break-glass if this is an emergency.'
                : $e->getMessage();

            return;
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage()." (reference {$e->requestId()})";

            return;
        }

        $this->resultMessage = $order->is_external
            ? "External referral generated for {$order->drug_display_name} — no internal SKU matched."
            : "Prescribed {$order->drug_display_name} from internal stock.";

        // The decision is committed; the next prescription is a new one.
        $this->idempotencyKey = (string) Str::uuid();

        $this->reset([
            'drugSearch', 'strength', 'doseAmount', 'clinicalIndication',
            'isNephrotoxic', 'maxMgPerKg', 'overrideReason', 'pendingSafety',
            'awaitingExternalConfirmation',
        ]);
    }

    public function administerDose(int|string $doseId): void
    {
        abort_unless(in_array('Administer MAR Doses', Auth::user()->permissions ?? []), 403);
        abort_unless(
            app(CareAccessGateway::class)->canMutateFromCurrentLocation(),
            403,
            'MAR dose administration is prohibited off-premises.'
        );

        try {
            app(MarGateway::class)->administer($this->actor(), $doseId);
        } catch (ClinicalApiException $e) {
            // A Five-Rights mismatch arrives here, naming which right failed.
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->errorMessage = null;
        $this->resultMessage = 'Dose administered.';
    }

    public function holdDose(int|string $doseId): void
    {
        abort_unless(in_array('Administer MAR Doses', Auth::user()->permissions ?? []), 403);

        $this->validate(['holdReasonCode' => ['required', 'string']]);

        try {
            app(MarGateway::class)->hold(
                $this->actor(),
                $doseId,
                $this->holdReasonCode,
                $this->holdNotes ?: null,
            );
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->reset(['holdReasonCode', 'holdNotes']);
        $this->errorMessage = null;
        $this->resultMessage = 'Dose held.';
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
