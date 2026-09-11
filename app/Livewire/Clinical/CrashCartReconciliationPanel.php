<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CrashCartReconciliationGateway;
use App\Models\Item;
use App\Models\Store;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException;
use App\Support\Clinical\ClinicalActor;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * SRD v6.0 §13.2 — Emergency Crash Cart Post-Consumption Reconciliation.
 *
 * A separate, larger workflow from RecordConsumption's general
 * point-of-care floor-stock capture (see that gateway's own docblock,
 * which explicitly excludes this one): this is a *single* submission
 * covering every item used during one resuscitation, tied to a specific
 * crash cart, that charts the items, decrements that cart's stock, and
 * bills the patient's account in one step — not one item at a time after
 * the fact.
 */
#[Lazy]
class CrashCartReconciliationPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $resuscitationEventId = '';

    public string $crashCartStoreId = '';

    public string $narrative = '';

    /** @var array<int, array{inventory_sku: string, quantity_used: string}> */
    public array $lines = [];

    public string $newItemCode = '';

    public string $newItemQty = '';

    public ?string $resultMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
        $this->resuscitationEventId = 'CODE-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
    }

    public function render()
    {
        $actor = $this->actor();

        return view('livewire.clinical.crash-cart-reconciliation-panel', [
            'crashCarts' => Store::where('business_id', $actor->businessId)
                ->where('is_crash_cart', true)
                ->orderBy('name')
                ->get(['id', 'name', 'crash_cart_status']),
            'stockedItems' => Item::where('business_id', $actor->businessId)
                ->orderBy('name')
                ->limit(300)
                ->get(['name', 'code']),
        ]);
    }

    public function addLine(): void
    {
        $this->validate([
            'newItemCode' => ['required', 'string'],
            'newItemQty' => ['required', 'numeric', 'gt:0'],
        ], [], ['newItemCode' => 'item', 'newItemQty' => 'quantity']);

        $item = Item::where('business_id', $this->actor()->businessId)
            ->where('name', $this->newItemCode)
            ->first();

        if (! $item) {
            $this->addError('newItemCode', "\"{$this->newItemCode}\" does not match a store item.");

            return;
        }

        $this->lines[] = [
            'inventory_sku' => $item->code,
            'display_name' => $item->name,
            'quantity_used' => $this->newItemQty,
        ];

        $this->reset(['newItemCode', 'newItemQty']);
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function reconcile(): void
    {
        abort_unless(in_array('Add Clinical Observations', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'resuscitationEventId' => ['required', 'string'],
            'crashCartStoreId' => ['required', 'string'],
        ], [], ['crashCartStoreId' => 'crash cart']);

        // A clinician who fills the Item/Qty row and goes straight to
        // Reconcile — without a separate click on Add Item — is the natural
        // reading of this form, not a mistake. Folding a still-pending row
        // in here means that flow charts what was actually typed instead of
        // silently discarding it and refusing with "add at least one item"
        // while the item they added is sitting right there.
        if ($this->newItemCode !== '' && $this->newItemQty !== '') {
            $this->addLine();
        }

        if ($this->lines === []) {
            $this->errorMessage = 'Add at least one item that was used.';

            return;
        }

        $this->errorMessage = null;
        $this->resultMessage = null;

        try {
            app(CrashCartReconciliationGateway::class)->reconcile(
                actor: $this->actor(),
                resuscitationEventId: $this->resuscitationEventId,
                patientId: $this->clientId,
                visitId: $this->visitId,
                crashCartStoreId: $this->crashCartStoreId,
                items: array_map(fn (array $line) => [
                    'inventory_sku' => $line['inventory_sku'],
                    'quantity_used' => (float) $line['quantity_used'],
                ], $this->lines),
                narrative: $this->narrative ?: null,
            );
        } catch (ClinicalRuleRefusedException $e) {
            // fieldErrors() names the actual rejected field (e.g. a missing
            // visit_id) instead of Laravel's generic "The given data was
            // invalid." — the same gap MaternityPanel::recordBirth() already
            // closed for its own validation errors.
            $fieldErrors = collect($e->fieldErrors())->filter(fn ($v) => is_array($v))->flatten();
            $this->errorMessage = $fieldErrors->isNotEmpty() ? $fieldErrors->first() : $e->getMessage();

            return;
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->resultMessage = count($this->lines).' item(s) reconciled — charted, stock decremented, and billed to the patient\'s account.';

        $this->reset(['lines', 'narrative', 'crashCartStoreId']);
        $this->resuscitationEventId = 'CODE-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
