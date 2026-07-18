<div class="w-full min-w-0">
    @php
        $amountCap = $order->effectiveAmountCap();
        $isAmountBudgetCap = $order->budget_mode === \App\Models\InventoryOrder::BUDGET_MODE_AMOUNT;
    @endphp
    @if($order->isInternal())
        <div class="mb-4 rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-950">
            <span class="font-medium">Internal order:</span>
            {{ $order->sourceStore?->selectLabel() ?? '—' }}
            →
            {{ $order->store?->selectLabel() ?? '—' }}
            <span class="block text-xs text-cyan-800 mt-1">No supplier — stock is requested from another store in your network.</span>
        </div>
    @elseif($order->isDraft() && $order->isExternal())
        <div class="mb-4 rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-950">
            <span class="font-medium">Purchase request.</span>
            Review and correct quantities below, then submit for approval.
            <span class="block text-xs text-violet-800 mt-1">After approval this becomes an RFQ — suppliers are invited and selected on the quotation analysis sheet.</span>
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
                            Changing one line redistributes the difference equally across the other lines so the order total stays within the budget cap.
                        @else
                            Changing one line redistributes the difference equally across the other lines so the order total stays at the generated amount.
                        @endif
                    @else
                        Cap is off — edit any line freely without changing the others.
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
    @endif

    @if($showBudgetCapNotice || $capAdjustmentComparison)
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p>
                    {{ $budgetCapNotice !== '' ? $budgetCapNotice : 'Line quantities were updated. Original vs adjusted values are shown below.' }}
                </p>
                @if($capAdjustmentComparison)
                    <button type="button"
                            wire:click="dismissCapAdjustmentComparison"
                            class="text-xs font-medium text-amber-800 underline hover:text-amber-950 shrink-0">
                        Dismiss
                    </button>
                @endif
            </div>

            @if($capAdjustmentComparison)
                @php
                    $cmp = $capAdjustmentComparison;
                    $cmpLines = collect($cmp['lines']);
                    $changedCount = $cmpLines->where('changed', true)->count();
                @endphp
                <div class="mt-3 overflow-x-auto rounded border border-amber-200/80 bg-white">
                    <table class="min-w-full text-xs text-gray-800">
                        <thead class="bg-amber-50/80 text-left text-[11px] uppercase tracking-wide text-amber-900/80">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Item</th>
                                <th class="px-3 py-2 font-semibold">Role</th>
                                <th class="px-3 py-2 font-semibold text-right">Original qty</th>
                                <th class="px-3 py-2 font-semibold text-right">Adjusted qty</th>
                                <th class="px-3 py-2 font-semibold text-right">Qty Δ</th>
                                <th class="px-3 py-2 font-semibold text-right">Original total</th>
                                <th class="px-3 py-2 font-semibold text-right">Adjusted total</th>
                                <th class="px-3 py-2 font-semibold text-right">Amount Δ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            @foreach($cmpLines as $row)
                                @php
                                    $roleLabel = match ($row['role'] ?? '') {
                                        'edited' => 'Edited',
                                        'adjusted' => 'Adjusted',
                                        default => 'Unchanged',
                                    };
                                    $qtyDelta = (float) ($row['qty_delta'] ?? 0);
                                    $totalDelta = (float) ($row['total_delta'] ?? 0);
                                    $changed = (bool) ($row['changed'] ?? false);
                                @endphp
                                <tr @class([
                                    'bg-sky-50/70' => ($row['role'] ?? '') === 'edited',
                                    'bg-amber-50/40' => ($row['role'] ?? '') === 'adjusted',
                                    'opacity-60' => ! $changed,
                                ])>
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-gray-900">{{ $row['item_name'] }}</span>
                                        @if(!empty($row['item_code']))
                                            <span class="block text-[11px] text-gray-500">{{ $row['item_code'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <span @class([
                                            'inline-flex rounded px-1.5 py-0.5 text-[11px] font-medium',
                                            'bg-sky-100 text-sky-800' => ($row['role'] ?? '') === 'edited',
                                            'bg-amber-100 text-amber-900' => ($row['role'] ?? '') === 'adjusted',
                                            'bg-gray-100 text-gray-600' => ($row['role'] ?? '') === 'unchanged',
                                        ])>{{ $roleLabel }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['qty_before'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium">{{ number_format((float) $row['qty_after'], 2) }}</td>
                                    <td @class([
                                        'px-3 py-2 text-right tabular-nums font-medium',
                                        'text-emerald-700' => $qtyDelta > 0,
                                        'text-red-700' => $qtyDelta < 0,
                                        'text-gray-500' => $qtyDelta == 0,
                                    ])>
                                        {{ $qtyDelta > 0 ? '+' : '' }}{{ number_format($qtyDelta, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['total_before'], 0) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium">{{ number_format((float) $row['total_after'], 0) }}</td>
                                    <td @class([
                                        'px-3 py-2 text-right tabular-nums font-medium',
                                        'text-emerald-700' => $totalDelta > 0,
                                        'text-red-700' => $totalDelta < 0,
                                        'text-gray-500' => $totalDelta == 0,
                                    ])>
                                        {{ $totalDelta > 0 ? '+' : '' }}{{ number_format($totalDelta, 0) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-amber-200 bg-amber-50/60">
                            <tr>
                                <td colspan="5" class="px-3 py-2 text-right font-medium text-amber-950">
                                    Order total
                                    <span class="block text-[11px] font-normal text-amber-800/80">
                                        {{ $changedCount }} of {{ $cmpLines->count() }} line(s) changed
                                        @if(!($cmp['capped'] ?? true))
                                            · Cap off (no redistribution)
                                        @endif
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $cmp['order_total_before'], 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ number_format((float) $cmp['order_total_after'], 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-600">
                                    @php $orderDelta = round((float) $cmp['order_total_after'] - (float) $cmp['order_total_before'], 2); @endphp
                                    {{ $orderDelta > 0 ? '+' : '' }}{{ number_format($orderDelta, 0) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
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
            ·
            <a href="{{ route('inventory.orders.calculations', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                View calculation
            </a>
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
