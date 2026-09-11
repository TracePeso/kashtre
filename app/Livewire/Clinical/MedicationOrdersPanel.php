<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\ClinicalDictionaryGateway;
use App\Contracts\Clinical\MarGateway;
use App\Contracts\Clinical\MedicationOrdersGateway;
use App\Http\Controllers\InvoiceController;
use App\Services\Clinical\Api\Exceptions\ClinicalAccessDeniedException;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException;
use App\Services\Clinical\Api\Exceptions\ClinicalSafetyBlockException;
use App\Services\Clinical\Api\Exceptions\ClinicalUnavailableException;
use App\Models\Client;
use App\Models\Item;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\MedicationOrderRecord;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Lazy;
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
 *
 * Prescribing here is a clinical record only — MedicationOrdersGateway (either
 * driver) tracks it for CDSS/MAR but never touches stock or billing. A real,
 * internally-fulfilled prescription therefore also goes through the exact
 * same order-placement path a direct store order does — InvoiceController::store(),
 * queueing it for the pharmacy and billing the patient's account — so
 * "prescribed" and "ordered from the store" are never two different states
 * for the same drug. An external referral (nothing in the catalogue matched)
 * has no internal stock to bill, so that step is skipped for it.
 */
#[Lazy]
class MedicationOrdersPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $drugSearch = '';

    public string $strength = '';

    public string $strengthUnitId = '';

    public string $doseAmount = '';

    public string $doseUnitId = '';

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

    /**
     * Five Rights verification, keyed by dose id — SRD §9.1's "barcode scan
     * & 5-Rights check". Clinical's FiveRightsVerifier::checkPatient()/
     * checkDrug() both refuse unconditionally when their field is absent
     * (confirmed against that class 2026-08-26) — administerDose() used to
     * call MarGateway::administer() with no verification array at all, so
     * every single administration failed this check, always, regardless of
     * which dose or patient. These two fields are what a USB barcode
     * scanner would type into (they behave as a keyboard, so a real scan of
     * a wristband/drug label lands directly in whichever field has focus);
     * pre-filled in render() with the values that are already known to be
     * correct so the check is meaningful (a wrong scan changes them) without
     * being a hard requirement in an environment with no scanner hardware.
     *
     * @var array<int|string, string>
     */
    public array $verifyPatientBarcode = [];

    /** @var array<int|string, string> */
    public array $verifyDrugBarcode = [];

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

        // A Clinical Module hiccup (ClinicalUnavailableException / any
        // ClinicalApiException) reading these lists should degrade the panel,
        // not crash it — same fail-soft posture ClinicalProcessPanel already
        // takes on its own history read. Previously none of these six calls
        // were guarded at all, so a single unreachable Clinical request threw
        // straight out of render() and took down the whole page (Livewire
        // batches every component on the page into one request), which is
        // exactly what surfaced as a raw 503 ClinicalUnavailableException
        // here instead of a clinician-readable message.
        [$routes, $frequencies, $units, $overrideReasons, $holdReasons, $activeOrders, $dueDoses] = [
            collect(), collect(), collect(), collect(), collect(), collect(), collect(),
        ];

        try {
            $routes = collect($dictionary->routes($actor));
            $frequencies = collect($dictionary->frequencies($actor));
            // The tenant's full unit dictionary also carries lab-result and
            // vital-sign units (e.g. "cells/uL", "g/L") that have nothing to
            // do with a drug's strength or dose — offering the whole list
            // let a clinician pick a haematology unit for a dose amount.
            // Narrowed to the categories that actually describe a
            // strength/dose (UnitsOfMeasureSeeder's own grouping, not
            // invented here).
            $units = collect($dictionary->unitsOfMeasure($actor))
                ->filter(fn ($unit) => in_array($unit->category, [
                    'Mass / Weight',
                    'Volume & Fluid Velocity',
                    'Weight-Normalized Dosing',
                    'Enzyme & Biological Activity',
                ], true))
                ->values();
            $overrideReasons = collect($dictionary->reasonCodes($actor, 'CDSS_OVERRIDE'));
            $holdReasons = collect($dictionary->reasonCodes($actor, 'MAR_WASTAGE'));
            $activeOrders = collect(app(MedicationOrdersGateway::class)->activeOrders($actor, $this->clientId));
            $dueDoses = collect(app(MarGateway::class)->dosesForPatient($actor, $this->clientId, 'DUE'));

            // Pre-fill the Five Rights scan fields with the values a correct
            // scan would produce, per this class's own $verifyPatientBarcode/
            // $verifyDrugBarcode docblock — without this, administerDose()
            // always sent an empty verification array and Clinical's
            // FiveRightsVerifier refused every single dose, unconditionally.
            foreach ($dueDoses as $dose) {
                $this->verifyPatientBarcode[$dose->id] ??= $this->clientId;
                $this->verifyDrugBarcode[$dose->id] ??= $dose->medicationOrder?->drug_code
                    ?: $dose->medicationOrder?->drug_display_name
                    ?: '';
            }
        } catch (ClinicalAccessDeniedException $e) {
            // Clinical is up and answered correctly — it just refused this
            // reader specifically (no care relationship on this patient, or
            // off-premises). Lumping this in with "unreachable" would send a
            // clinician chasing a network problem that does not exist.
            $this->errorMessage ??= $e->requiresBreakGlass()
                ? $e->getMessage().' Use break-glass if this is an emergency.'
                : $e->getMessage();
        } catch (ClinicalApiException $e) {
            $this->errorMessage ??= 'The Clinical Module is unreachable right now — some of this panel may be showing stale or empty data. Try again shortly.';
        }

        return view('livewire.clinical.medication-orders-panel', [
            'routes' => $routes,
            'frequencies' => $frequencies,
            'units' => $units,
            'overrideReasons' => $overrideReasons,
            'holdReasons' => $holdReasons,
            'activeOrders' => $activeOrders,
            'dueDoses' => $dueDoses,
            // Suggestions only — prescribe() still sends whatever text ends up
            // in $drugSearch as requested_term (the only field the API
            // accepts). A plain <select> would make it impossible to type a
            // drug this store doesn't stock, which is exactly the case the
            // external-referral flow above exists for. A <datalist> gets the
            // "pick it from the store" UX the clinician wants without losing
            // that escape hatch.
            'stockedDrugs' => Item::where('business_id', $actor->businessId)
                ->orderBy('name')
                ->limit(300)
                ->get(['name']),
        ]);
    }

    public function prescribe(): void
    {
        abort_unless(in_array('Prescribe Medication Orders', Auth::user()->permissions ?? []), 403);

        // Advisory only, and this is the one spot that had to change to make
        // the ClinicalUnavailableException fallback below actually reachable:
        // ApiCareAccessGateway::canMutateFromCurrentLocation() fails *closed*
        // on any ClinicalApiException — genuinely off-premises and Clinical
        // being unreachable both come back as a plain `false`, indistinguishable
        // from here. Hard-aborting on that used to block this entire action —
        // fallback included — every time Clinical could not be reached, which
        // is exactly the situation the fallback exists for. Clinical still
        // self-enforces the real restriction (403 ZTNA_OFFSITE_MUTATION_RESTRICTED)
        // on the write itself whenever it *is* reachable, so nothing is lost
        // there; only the pre-flight courtesy message is skipped when the
        // answer is unknown rather than a confirmed "no".
        if (! app(CareAccessGateway::class)->canMutateFromCurrentLocation()) {
            Log::info('canMutateFromCurrentLocation() returned false for a prescribe attempt — proceeding, since this cannot distinguish off-premises from Clinical being unreachable.', [
                'user_id' => Auth::id(),
                'client_id' => $this->clientId,
            ]);
        }

        $this->validate([
            'drugSearch' => ['required', 'string'],
            'doseAmount' => ['required', 'numeric', 'gt:0'],
            'routeCode' => ['required', 'string'],
            'frequencyCode' => ['required', 'string'],
        ]);

        $this->errorMessage = null;

        // Clinical's own strength field is a single free-text descriptor
        // (max 128 chars) — there is no separate strength-unit slot on its
        // side, so the picked unit is folded into that string here rather
        // than dropped. Dose has a real dose_uom_id column on both drivers
        // (see LocalMedicationOrdersGateway / clinical_medication_orders),
        // so that one is sent as its own field.
        //
        // This lookup used to be unguarded — a Clinical hiccup here threw
        // straight out of prescribe() *before* reaching the try/catch below
        // that handles exactly that case, so the ClinicalUnavailableException
        // fallback a few lines down never got a chance to run at all. Fixed:
        // a failure here degrades to no unit suffix on the strength text,
        // not a crash.
        $strengthUnitLabel = null;

        if ($this->strengthUnitId !== '') {
            try {
                $strengthUnitLabel = collect(app(ClinicalDictionaryGateway::class)->unitsOfMeasure($this->actor()))
                    ->firstWhere('id', (int) $this->strengthUnitId)?->unit_label;
            } catch (ClinicalApiException $e) {
                // Fall through with no label — the fallback path below still
                // gets a chance to place the order even if this specific
                // lookup failed.
            }
        }

        $draft = [
            'requested_term' => $this->drugSearch,
            'strength_descriptor' => $this->strength !== ''
                ? trim($this->strength.' '.($strengthUnitLabel ?? ''))
                : null,
            'dose_amount' => (float) $this->doseAmount,
            'dose_uom_id' => $this->doseUnitId !== '' ? (int) $this->doseUnitId : null,
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
        } catch (ClinicalUnavailableException $e) {
            // Clinical being down should not also block the store order a
            // clinician would otherwise place directly — that part lives
            // entirely in Main and does not need Clinical at all. This is a
            // conscious trade-off: the CDSS safety check and MAR dose
            // schedule do NOT happen for this prescription (Clinical never
            // saw it), which is why the result message says so plainly
            // rather than quietly proceeding as if nothing were missing.
            $item = $this->resolveStockedItemByName($this->drugSearch);

            if (! $item) {
                $this->errorMessage = "The Clinical Module is unreachable, and \"{$this->drugSearch}\" does not match any store item to order directly instead. (reference {$e->requestId()})";

                return;
            }

            $billingNote = $this->placeCommercialOrderForItem($item);

            $this->resultMessage = "Clinical Module unreachable — {$item->name} was NOT recorded for CDSS/MAR, but it was ordered from the store and billed to the patient's account. Chart this prescription once Clinical is back up."
                .($billingNote ? ' '.$billingNote : '');

            $this->idempotencyKey = (string) Str::uuid();

            $this->reset([
                'drugSearch', 'strength', 'strengthUnitId', 'doseAmount', 'doseUnitId',
                'clinicalIndication', 'isNephrotoxic', 'maxMgPerKg', 'overrideReason',
                'pendingSafety', 'awaitingExternalConfirmation',
            ]);

            return;
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage()." (reference {$e->requestId()})";

            return;
        }

        if ($order->is_external) {
            $this->resultMessage = "External referral generated for {$order->drug_display_name} — no internal SKU matched.";
        } else {
            $this->resultMessage = "Prescribed {$order->drug_display_name} from internal stock.";

            $billingNote = $this->placeCommercialOrder($order);
            if ($billingNote !== null) {
                $this->resultMessage .= ' '.$billingNote;
            }
        }

        // The decision is committed; the next prescription is a new one.
        $this->idempotencyKey = (string) Str::uuid();

        $this->reset([
            'drugSearch', 'strength', 'strengthUnitId', 'doseAmount', 'doseUnitId',
            'clinicalIndication', 'isNephrotoxic', 'maxMgPerKg', 'overrideReason',
            'pendingSafety', 'awaitingExternalConfirmation',
        ]);
    }

    /**
     * The same order-placement path a direct store order goes through
     * (InvoiceController::store()) — a clinical prescription is otherwise
     * invisible to billing and the pharmacy fulfilment queue, since
     * MedicationOrdersGateway only ever records it for CDSS/MAR purposes.
     * Quantity defaults to 1 (one course/pack per prescription — a refill is
     * its own prescription); the invoice is billed to the patient's account
     * rather than collected on the spot (payment_status=pending), same as
     * any other clinical order that isn't a point-of-sale checkout.
     *
     * Failure here does not undo the prescription already placed above — the
     * clinical decision is real regardless of whether billing succeeded —
     * it is surfaced as a note appended to resultMessage instead.
     */
    private function placeCommercialOrder(MedicationOrderRecord $order): ?string
    {
        if (! $order->drug_code) {
            return null;
        }

        $item = Item::where('business_id', $this->actor()->businessId)
            ->where('code', $order->drug_code)
            ->first();

        if (! $item) {
            Log::warning('Prescribed drug has no matching store Item — billing/fulfilment skipped.', [
                'drug_code' => $order->drug_code,
                'business_id' => $this->actor()->businessId,
            ]);

            return 'Not billed — no matching store item was found for this drug.';
        }

        $noteSuffix = ($order->route_code ? " {$order->route_code}" : '').($order->frequency_code ? " {$order->frequency_code}" : '');

        return $this->placeCommercialOrderForItem($item, $noteSuffix);
    }

    /**
     * A store item found by exact (then partial, case-insensitive) name
     * match against $drugSearch — the same pool the Drug field's datalist
     * already suggests from. Used only when Clinical could not resolve the
     * drug itself (it is unreachable), so there is no drug_code to look up
     * by; matching on the free-text name is the best this can do without it.
     */
    private function resolveStockedItemByName(string $drugName): ?Item
    {
        $businessId = $this->actor()->businessId;

        return Item::where('business_id', $businessId)
            ->where('name', $drugName)
            ->first()
            ?? Item::where('business_id', $businessId)
                ->where('name', 'like', '%'.$drugName.'%')
                ->orderBy('name')
                ->first();
    }

    /**
     * The same order-placement path a direct store order goes through
     * (InvoiceController::store()) — quantity defaults to 1 (one course/pack
     * per prescription — a refill is its own prescription); the invoice is
     * billed to the patient's account rather than collected on the spot
     * (payment_status=pending), same as any other clinical order that isn't
     * a point-of-sale checkout.
     *
     * Failure here does not undo the prescription already placed above (when
     * called from there) — it is surfaced as a note appended to
     * resultMessage instead.
     */
    private function placeCommercialOrderForItem(Item $item, string $noteSuffix = ''): ?string
    {
        $actor = $this->actor();

        $client = Client::where('business_id', $actor->businessId)
            ->where('client_id', $this->clientId)
            ->first();

        if (! $client || ! $actor->branchId) {
            // This branch was previously silent — undiagnosable from the
            // logs alone. The one confirmed cause so far: $this->clientId
            // is the route's string client_id (e.g. "ETJFA27HU"), and a
            // near-miss on that string (a typo, or an id copied from
            // somewhere that only had the short form) resolves zero Client
            // rows, not a fuzzy match — silently. branchId comes from the
            // acting user's own account, so it is null only for a user with
            // no branch assigned at all.
            Log::warning('Prescribed order could not be billed — client or branch unresolved.', [
                'route_client_id' => $this->clientId,
                'client_found' => $client !== null,
                'actor_branch_id' => $actor->branchId,
                'business_id' => $actor->businessId,
                'item_code' => $item->code,
            ]);

            return 'Not billed — client or branch could not be resolved.';
        }

        $price = (float) ($item->default_price ?? 0);
        $quantity = 1;
        $subtotal = $price * $quantity;
        $serviceCharge = (float) app(InvoiceController::class)->calculateServiceCharge($actor->businessId, $subtotal);
        $totalAmount = $subtotal + $serviceCharge;

        $invoiceRequest = HttpRequest::create('/invoices', 'POST', [
            'client_id' => $client->id,
            'business_id' => $actor->businessId,
            'branch_id' => $actor->branchId,
            'created_by' => $actor->userId,
            'client_name' => $client->name,
            // 'required|string' on the controller side fails on an empty
            // string, not just null — a client with no phone on file still
            // needs a non-empty placeholder to get past validation.
            'client_phone' => $client->phone_number ?: 'N/A',
            // 'nullable', but InvoiceController::store() reads
            // $validated['payment_phone'] with no ?? fallback (line 623) —
            // omitting the key entirely (as this payload used to) means
            // Laravel's validated() array never contains it at all, so that
            // raw access throws "Undefined array key". Every other read of
            // this same field elsewhere in that controller does fall back to
            // client_phone; sending it explicitly here gets the same result
            // without needing to touch that file.
            'payment_phone' => $client->phone_number ?: null,
            'visit_id' => $this->visitId ?: $client->visit_id,
            'items' => [[
                'id' => $item->id,
                'name' => $item->name,
                'price' => $price,
                'quantity' => $quantity,
                'total_amount' => $subtotal,
            ]],
            'subtotal' => $subtotal,
            'service_charge' => $serviceCharge,
            'total_amount' => $totalAmount,
            'amount_paid' => 0,
            'balance_due' => $totalAmount,
            'payment_methods' => [],
            // Charged to the patient's account for later settlement — a
            // bedside prescription is not a point-of-sale checkout.
            'payment_status' => 'pending',
            'status' => 'confirmed',
            'notes' => "Prescribed via Medications & MAR — {$item->name}{$noteSuffix}",
        ]);

        $response = app(InvoiceController::class)->store($invoiceRequest);
        $body = $response->getData(true);

        if (($body['success'] ?? true) === false) {
            Log::warning('Order billing failed for a prescribed drug.', [
                'item_code' => $item->code,
                'client_id' => $this->clientId,
                'message' => $body['message'] ?? null,
            ]);

            return 'Billing failed: '.($body['message'] ?? 'unknown error').'.';
        }

        // store() only auto-queues a *credit* transaction, a *fully-paid*
        // cash/mobile-money transaction, or a *zero-amount* transaction —
        // confirmed against its own source (InvoiceController::store()
        // "STEP 16"/"Zero-amount transaction" branches). This invoice is
        // none of those (pending, billed to the account, non-zero), so
        // without this it is created but never reaches a service point at
        // all — invisible on the client's own "Ordered Items" board even
        // though the item has a real service-point mapping. Queue it
        // explicitly so a prescribed drug lands exactly where a direct
        // store order of the same item would.
        $invoiceId = $body['invoice']['id'] ?? null;
        $invoice = $invoiceId ? \App\Models\Invoice::find($invoiceId) : null;

        if ($invoice) {
            try {
                app(InvoiceController::class)->queueItemsAtServicePoints($invoice, [[
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'total_amount' => $subtotal,
                ]]);
            } catch (\Throwable $e) {
                Log::warning('Prescribed order was billed but could not be queued at a service point.', [
                    'invoice_id' => $invoice->id,
                    'item_code' => $item->code,
                    'error' => $e->getMessage(),
                ]);

                return 'Billed, but not queued for fulfilment — check the service point mapping for this item.';
            }
        }

        return null;
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
            // Clinical's FiveRightsVerifier::checkPatient()/checkDrug() both
            // refuse unconditionally when their scan field is absent — this
            // used to call administer() with no verification array at all,
            // so every dose failed the check regardless of which one or
            // which patient. These are pre-filled in render() with the
            // correct values (like a barcode scanner landing its read on a
            // focused field); still editable, so a wrong value here still
            // reproduces a genuine Five Rights refusal.
            app(MarGateway::class)->administer($this->actor(), $doseId, [
                'patient_barcode' => $this->verifyPatientBarcode[$doseId] ?? null,
                'drug_barcode' => $this->verifyDrugBarcode[$doseId] ?? null,
            ]);
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
