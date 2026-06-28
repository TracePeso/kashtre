<div class="w-full min-w-0">
    @php
        $amountCap = $order->effectiveAmountCap();
        $isAmountBudgetCap = $order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT && (float) ($order->budget_value ?? 0) > 0;
    @endphp
    @if($order->isDraft())
        <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <label for="rfq-supplier-id" class="block text-sm font-medium text-gray-900">Supplier</label>
            <p class="text-xs text-gray-500 mt-0.5">One supplier for the entire RFQ, same as when receiving goods on a GRN.</p>
            <select id="rfq-supplier-id"
                    wire:model.live="supplierId"
                    class="mt-2 block w-full max-w-md rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">— Select supplier —</option>
                @foreach($supplierOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            @error('supplierId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @if(! $supplierId)
                <p class="mt-2 text-xs text-amber-700">Select a supplier before submitting this RFQ.</p>
            @elseif($order->lines()->count() === 0)
                <p class="mt-2 text-xs text-amber-700">No items for this supplier at the selected store. Try another supplier or refresh after linking items.</p>
            @endif
        </div>
    @elseif($order->supplier)
        <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
            <span class="text-gray-500">Supplier:</span>
            <span class="font-medium text-gray-900">{{ $order->supplier->name }}</span>
        </div>
    @endif
    @if($order->isDraft() && $amountCap !== null && $amountCap > 0)
        @php
            $orderTotal = $order->orderTotal();
            $budgetUsedPct = round(($orderTotal / $amountCap) * 100, 1);
        @endphp
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900">
                    @if($isAmountBudgetCap)
                        Budget cap (UGX {{ number_format($amountCap, 0) }})
                    @else
                        Order total cap (UGX {{ number_format($amountCap, 0) }})
                    @endif
                </p>
                <p class="text-xs text-gray-600 mt-0.5">
                    @if($budgetCapEnforced)
                        @if($isAmountBudgetCap)
                            Quantities are limited so the order total stays within the budget cap.
                        @else
                            Quantities are limited so the order total stays at or below the generated amount.
                        @endif
                    @else
                        Cap is off — you can increase line amounts beyond the {{ $isAmountBudgetCap ? 'budget' : 'generated total' }}.
                    @endif
                </p>
            </div>
            <label class="inline-flex items-center gap-2 cursor-pointer shrink-0">
                <span class="text-sm font-medium text-gray-700">Cap budget</span>
                <button type="button"
                        wire:click="toggleBudgetCapEnforced"
                        role="switch"
                        aria-checked="{{ $budgetCapEnforced ? 'true' : 'false' }}"
                        @class([
                            'relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
                            'bg-emerald-600' => $budgetCapEnforced,
                            'bg-gray-200' => ! $budgetCapEnforced,
                        ])>
                    <span @class([
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                        'translate-x-5' => $budgetCapEnforced,
                        'translate-x-0' => ! $budgetCapEnforced,
                    ])></span>
                </button>
            </label>
        </div>

        @if($showBudgetCapNotice)
            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                Quantity reduced to stay within the budget cap.
            </div>
        @endif
    @endif

    <div class="fi-ta-ctn w-full overflow-x-auto">
        {{ $this->table }}
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-3">
        <p class="text-xs text-gray-500">
            {{ $order->lines()->count() }} item(s)
            @if($order->isDraft())
                · Edit order quantities, then submit for approval
            @elseif($order->canReceiveGoods())
                · Use <strong>Receive goods</strong> to record deliveries against this order
            @endif
        </p>
        <div class="text-right">
            <p class="text-sm font-semibold text-gray-900">
                Order total: UGX {{ number_format($order->orderTotal(), 2) }}
            </p>
            @if($amountCap !== null && $amountCap > 0)
                @php
                    $footerTotal = $order->orderTotal();
                    $footerPct = round(($footerTotal / $amountCap) * 100, 1);
                @endphp
                <p @class([
                    'text-xs mt-0.5',
                    'text-emerald-700' => $footerPct < 90,
                    'text-amber-700' => $footerPct >= 90 && $footerPct < 100,
                    'text-red-700' => $footerPct >= 100,
                ])>
                    @if($budgetCapEnforced)
                        {{ min(100, $footerPct) }}% of cap
                    @else
                        {{ $footerPct }}% of original cap
                    @endif
                </p>
            @endif
        </div>
    </div>
</div>
