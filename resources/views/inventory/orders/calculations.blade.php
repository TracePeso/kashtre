<x-app-layout>
@php
    $fmt = fn ($n, $d = 2) => $n === null ? '—' : number_format((float) $n, $d);
    $fmtQty = fn ($n) => $n === null ? '—' : number_format((float) $n, 4);
    $isBudgetDays = ($breakdown['budget_mode'] ?? null) === 'days';
    $isBudgetAmount = ($breakdown['budget_mode'] ?? null) === 'amount';
    $isBudgetAhAl = $isBudgetDays || $isBudgetAmount;
    $budgetBa7ForAj = $isBudgetAmount
        ? ($breakdown['budget_ugx'] ?? $breakdown['budget_value'])
        : ($breakdown['budget_value'] ?? 0);
    $budgetDayPool = $breakdown['ba7_budget_days'] ?? $breakdown['period_days'] ?? 0;
@endphp
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.orders.show', $order) }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to {{ $order->order_number }}</a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Order calculation</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $order->order_number }} · {{ $breakdown['method'] }} ·
                    Separate page showing how each quantity was calculated from
                    <span class="font-mono text-gray-700">KashTre_new.xlsx</span> → Inventory
                </p>
            </div>
            <div class="flex flex-wrap gap-3 items-center">
                <a href="{{ route('inventory.orders.show', $order) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Back to order
                </a>
                <a href="{{ route('inventory.orders.how-it-works') }}"
                   class="text-sm font-medium text-blue-600 hover:text-blue-800">
                    How ordering works →
                </a>
            </div>
        </div>

        @include('inventory.partials.subnav')

        {{-- 1. Entire formula first — collapsed by default --}}
        <section class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden" x-data="{ open: false }">
            <button type="button"
                    @click="open = !open"
                    class="w-full px-6 py-4 border-b border-gray-200 bg-slate-900 flex items-center justify-between gap-3 text-left">
                <div>
                    <h3 class="text-base font-semibold text-white">1. How the order quantity is calculated</h3>
                    @if($isBudgetAhAl)
                        <p class="mt-1 text-sm text-slate-300 font-mono">AH → AI → AJ → AK → AL · BA7 = budget UGX</p>
                    @else
                        <p class="mt-1 text-sm text-slate-300 font-mono">AF = max(0, (BA6 + AB + AD − N) × graduated_MA(period))</p>
                    @endif
                </div>
                <span class="shrink-0 text-slate-300 text-sm" x-text="open ? 'Hide' : 'Show'"></span>
            </button>
            <div x-show="open" x-cloak class="px-6 py-5 space-y-5">
                @if($isBudgetAhAl)
                    <div class="rounded-lg border border-violet-200 bg-violet-50 p-5 space-y-4 text-sm text-gray-800">
                        <p class="font-medium text-violet-950">Excel “Ordering By Budget” — columns AH → AL</p>
                        <ol class="list-decimal list-inside space-y-3 font-mono text-[13px]">
                            <li>
                                <span class="font-sans font-semibold text-violet-800">AH — Test Amount</span><br>
                                AH = 15 × (V or AA if V = 0) × (F/J)
                            </li>
                            <li>
                                <span class="font-sans font-semibold text-violet-800">AI — Gap to Average Days left to Order</span><br>
                                AI = AM − (Σ AM ÷ count of AM)
                            </li>
                            <li>
                                <span class="font-sans font-semibold text-violet-800">AJ — Order Days</span><br>
                                AJ = (15 × BA7 ÷ Σ AH) − AI
                            </li>
                            <li>
                                <span class="font-sans font-semibold text-violet-800">AK — Order Qty</span><br>
                                AK = AJ × (V or AA if V = 0)
                            </li>
                            <li>
                                <span class="font-sans font-semibold text-violet-800">AL — Order amount</span><br>
                                AL = AK × (F/J)
                            </li>
                        </ol>
                        <p class="rounded-md bg-white border border-violet-200 px-4 py-3 font-sans text-sm text-gray-800">
                            @if($isBudgetAmount)
                                Excel <strong>BA7</strong> is your budget
                                <strong>UGX {{ $fmt($breakdown['budget_ugx'] ?? $breakdown['budget_value']) }}</strong>
                                (not days). Order days pool
                                <strong>15 × BA7 ÷ Σ AH ≈ {{ $fmt($breakdown['ba7_budget_days'] ?? $breakdown['period_days'], 1) }}</strong>,
                                then AJ = pool − AI per item.
                            @else
                                <strong>BA7</strong> on this order
                                (= {{ $fmt($breakdown['budget_value'], 0) }} days).
                            @endif
                            Items with days left (AM) below the average get a larger AJ (more urgent).
                            If AJ would be negative, quantity is 0.
                            Selected items always remain on the order; overstocked ones (stock days &gt; 366) get qty 0 and are left out of AVERAGE(AM).
                        </p>
                    </div>
                    <div class="rounded-lg bg-slate-900 text-slate-100 p-5 space-y-2 font-mono text-sm">
                        <p class="font-sans text-xs font-semibold uppercase text-violet-300">This order’s portfolio totals</p>
                        @if($isBudgetAmount)
                            <p>BA7 (budget you entered) = UGX {{ $fmt($breakdown['ba7_budget_ugx'] ?? $breakdown['budget_value']) }}</p>
                        @else
                            <p>BA7 (budget days you entered) = {{ $fmt($breakdown['budget_value'], 0) }} days</p>
                        @endif
                        <p>Σ AH (15-day test spend, items that need stock) = UGX {{ $fmt($breakdown['ah_sum_test_amount']) }}</p>
                        <p>Day pool = 15 × BA7 ÷ Σ AH = {{ ($breakdown['ah_sum_test_amount'] ?? 0) > 0 ? number_format(15 * (float) ($breakdown['ba7_budget_ugx'] ?? $breakdown['budget_value']) / (float) $breakdown['ah_sum_test_amount'], 4) : '—' }}</p>
                        <p>Average AM (same needy items) = {{ $fmt($breakdown['am_average_days_left'], 2) }}</p>
                        @if($isBudgetAmount && ($breakdown['scale_factor'] ?? null) !== null && abs((float) $breakdown['scale_factor'] - 1) >= 0.000001)
                            <p class="font-sans text-amber-300 pt-1">
                                After peak, Σ AL was UGX {{ $fmt($breakdown['unscaled_total']) }} — scaled to
                                UGX {{ $fmt($breakdown['budget_cap_ugx'] ?? $breakdown['order_total']) }}
                                (× {{ number_format((float) $breakdown['scale_factor'], 6) }}) to fit the budget cap.
                            </p>
                        @endif
                    </div>
                @else
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 space-y-4 text-sm text-gray-800">
                    <ol class="list-decimal list-inside space-y-3 text-gray-800">
                        <li>
                            <strong>How many days of stock are we short?</strong>
                            Take the days you want to cover (period) + safety days + buffer days, then subtract how many days of stock you already have.
                            That gap is the “days still needed.”
                        </li>
                        <li>
                            <strong>How fast do we use this item each day?</strong>
                            The system picks a daily usage rate from the period of order you entered.
                            Period under 15 days → 15-day average (V). Under 30 → 30-day (W). Under 90 → 90-day (X). Under 180 → 180-day (Y). Otherwise 360-day (Z).
                            That pick is the graduated moving average.
                        </li>
                        <li>
                            <strong>How much should we order?</strong>
                            Multiply: <em>days still needed × daily usage</em>.
                            That gives the quantity to order.
                        </li>
                        <li>
                            <strong>What does “max(0, …)” mean?</strong>
                            If the answer would be negative (you already have enough stock), order <strong>zero</strong> — never a negative quantity.
                            So “max(0, answer)” simply means: <em>use the answer, or 0 if the answer is below zero</em>.
                        </li>
                    </ol>
                    <p class="rounded-md bg-white border border-emerald-200 px-4 py-3 text-gray-900">
                        <strong>One sentence:</strong>
                        Order enough units to cover the days you are short, at the daily usage rate that matches the period of order you entered — and if you are not short, order nothing.
                    </p>
                </div>

                <div class="rounded-lg bg-slate-900 text-slate-100 p-5 space-y-4 overflow-x-auto">
                    <p class="font-sans text-xs font-semibold uppercase tracking-wide text-emerald-400">Same idea as Excel (column AF)</p>
                    <p class="font-mono text-base sm:text-lg text-white">
                        AF = max( 0, ( BA6 + AB + AD − N ) × graduated_MA(period) )
                    </p>

                    <div class="grid gap-3 font-sans text-sm">
                        <div class="rounded-md bg-slate-800/80 border border-slate-700 px-4 py-3">
                            <p class="text-sky-300 font-medium">max(0, …)</p>
                            <p class="text-slate-300 mt-1">
                                “Don’t go below zero.” If the calculated quantity is negative, show <strong class="text-white">0</strong> instead.
                                You never order a negative amount.
                            </p>
                        </div>
                        <div class="rounded-md bg-slate-800/80 border border-slate-700 px-4 py-3">
                            <p class="text-sky-300 font-medium">BA6 + AB + AD − N</p>
                            <p class="text-slate-300 mt-1">
                                Days you want (period) + safety + buffer − days of stock you already have = <strong class="text-white">days still needed</strong>.
                            </p>
                        </div>
                        <div class="rounded-md bg-slate-800/80 border border-slate-700 px-4 py-3">
                            <p class="text-sky-300 font-medium">graduated_MA(period)</p>
                            <p class="text-slate-300 mt-1">
                                The <strong class="text-white">daily usage rate</strong> chosen from the period of order you entered:
                            </p>
                            <ul class="mt-2 space-y-1 text-slate-300 list-disc list-inside">
                                <li>Period less than 15 days → use last <strong class="text-white">15 days</strong> average (Excel column <strong class="text-white">V</strong>)</li>
                                <li>Period less than 30 days → use <strong class="text-white">30-day</strong> average (<strong class="text-white">W</strong>)</li>
                                <li>Period less than 90 days → use <strong class="text-white">90-day</strong> average (<strong class="text-white">X</strong>)</li>
                                <li>Period less than 180 days → use <strong class="text-white">180-day</strong> average (<strong class="text-white">Y</strong>)</li>
                                <li>Otherwise → use <strong class="text-white">360-day</strong> average (<strong class="text-white">Z</strong>)</li>
                            </ul>
                            <p class="text-slate-400 mt-2 text-xs">
                                Longer order periods use a longer consumption average so the forecast is more stable.
                                This rate is only for AF (order qty). Stock days still use V/AA: N = M ÷ (V or AA).
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-slate-700 pt-3 space-y-3 font-mono text-sm">
                        <p class="font-sans text-xs font-semibold uppercase tracking-wide text-sky-400">Other related formulas</p>
                        <p>N = M ÷ (V or AA) <span class="font-sans text-slate-400 text-xs">— stock days always use the 15-day rate (not the period MA)</span></p>
                        <p>AM = N − AB − AD <span class="font-sans text-slate-400 text-xs">— days left before you hit safety + buffer</span></p>
                        <p>AG = final qty × purchase price <span class="font-sans text-slate-400 text-xs">— line total in UGX</span></p>
                        
                    </div>

                    <div class="rounded-md bg-amber-950/40 border border-amber-700/50 px-4 py-3 font-sans text-sm space-y-2">
                        <p class="text-amber-300 font-medium">Peak period — multiply, do not add</p>
                        <p class="font-mono text-slate-100">peak impact % = peak period % × consumption increase % ÷ 100</p>
                        <p class="font-mono text-slate-100">final qty = AF × (1 + peak impact % ÷ 100)</p>
                        <p class="text-slate-300 text-xs">
                            The two peak percentages are <strong class="text-white">multiplied</strong> (then ÷ 100) to get the impact.
                            Then the order quantity is <strong class="text-white">multiplied</strong> by (1 + impact%).
                            Example: 10% × 20% ÷ 100 = 2% impact → qty × 1.02. Adding 10% + 20% = 30% would be wrong.
                        </p>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-900 mb-2">Quick glossary</p>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-700 w-28">Name</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Meaning</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr><td class="px-3 py-2 font-mono font-medium">BA6</td><td class="px-3 py-2 text-gray-600">Period — “I want stock to last this many more days.”</td></tr>
                                <tr><td class="px-3 py-2 font-mono font-medium">AB</td><td class="px-3 py-2 text-gray-600">Safety days — reserve for supply delays.</td></tr>
                                <tr><td class="px-3 py-2 font-mono font-medium">AD</td><td class="px-3 py-2 text-gray-600">Buffer days — extra cushion for busy periods.</td></tr>
                                <tr><td class="px-3 py-2 font-mono font-medium">M</td><td class="px-3 py-2 text-gray-600">How many units you have on the shelf now.</td></tr>
                                <tr><td class="px-3 py-2 font-mono font-medium">V / AA</td><td class="px-3 py-2 text-gray-600">15-day daily usage (or fixed AA). Used for stock days: N = M ÷ (V or AA).</td></tr>
                                <tr><td class="px-3 py-2 font-mono font-medium">N</td><td class="px-3 py-2 text-gray-600">How many days your current stock will last (always from V/AA, not from period MA).</td></tr>
                                <tr><td class="px-3 py-2 font-mono font-medium">W / X / Y / Z</td><td class="px-3 py-2 text-gray-600">Longer moving averages selected by the entered period for order quantity (AF).</td></tr>
                                <tr><td class="px-3 py-2 font-mono font-medium">AF</td><td class="px-3 py-2 text-gray-600">How many units to order (before peak) = days still needed × graduated_MA(period).</td></tr>
                                <tr><td class="px-3 py-2 font-mono font-medium">Peak</td><td class="px-3 py-2 text-gray-600">Uplift after AF — percentages are multiplied, then qty is multiplied by (1 + impact%).</td></tr>
                                <tr><td class="px-3 py-2 font-mono font-medium">AG</td><td class="px-3 py-2 text-gray-600">Cost of that line (quantity × price).</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </section>

        {{-- 2. This order's inputs --}}
        <section class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-slate-50">
                <h3 class="text-base font-semibold text-gray-900">2. This order’s inputs (plugged into the formula)</h3>
            </div>
            <div class="px-6 py-5" x-data="{ showAhDetail: false, showAmDetail: false }">
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Method</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $breakdown['method'] }}</dd>
                    </div>
                    @if($isBudgetAhAl)
                        @if($isBudgetAmount)
                            <div>
                                <dt class="text-gray-500">BA7 — Budget (UGX)</dt>
                                <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">UGX {{ $fmt($breakdown['budget_ugx'] ?? $breakdown['budget_value']) }}</dd>
                                <dd class="mt-1 text-xs text-gray-500">What you typed on create</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Day pool</dt>
                                <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">{{ $fmt($breakdown['ba7_budget_days'] ?? $breakdown['period_days'], 1) }}</dd>
                                <dd class="mt-1 text-xs text-gray-500">15 × budget ÷ Σ AH</dd>
                            </div>
                        @else
                            <div>
                                <dt class="text-gray-500">BA7</dt>
                                <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">{{ $fmt($breakdown['ba7_budget_days'] ?? $breakdown['budget_value'], 0) }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-gray-500">Σ AH — Test amounts</dt>
                            <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">UGX {{ $fmt($breakdown['ah_sum_test_amount']) }}</dd>
                            <dd class="mt-1 text-xs text-gray-500">Sum of 15-day spend for items that need stock</dd>
                            <button type="button"
                                    @click="showAhDetail = !showAhDetail; if (showAhDetail) showAmDetail = false"
                                    class="mt-1.5 text-xs font-medium text-blue-600 hover:text-blue-800">
                                <span x-text="showAhDetail ? 'Hide detailed calculation' : 'View detailed calculation'"></span>
                            </button>
                        </div>
                        <div>
                            <dt class="text-gray-500">Average days left (AM)</dt>
                            <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">{{ $fmt($breakdown['am_average_days_left'], 2) }}</dd>
                            <dd class="mt-1 text-xs text-gray-500">
                                Average of {{ (int) ($breakdown['am_average_count'] ?? 0) }} item(s) that still need stock
                            </dd>
                            <button type="button"
                                    @click="showAmDetail = !showAmDetail; if (showAmDetail) showAhDetail = false"
                                    class="mt-1.5 text-xs font-medium text-blue-600 hover:text-blue-800">
                                <span x-text="showAmDetail ? 'Hide detailed calculation' : 'View detailed calculation'"></span>
                            </button>
                        </div>
                    @else
                        <div>
                            <dt class="text-gray-500">Period (days)</dt>
                            <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">{{ $fmt($breakdown['period_days'], 0) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500">Safety days</dt>
                        <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">{{ $fmt($breakdown['safety_days'], 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Buffer days</dt>
                        <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">{{ $fmt($breakdown['buffer_days'], 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Peak period %</dt>
                        <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">{{ $fmt($breakdown['peak_period_percent'], 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Peak increase %</dt>
                        <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">{{ $fmt($breakdown['peak_increase_percent'], 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Peak impact %</dt>
                        <dd class="mt-0.5 font-mono font-medium text-gray-900 tabular-nums">{{ $fmt($breakdown['peak_impact_percent'], 4) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Order total</dt>
                        <dd class="mt-0.5 font-mono font-semibold text-gray-900 tabular-nums">UGX {{ $fmt($breakdown['order_total']) }}</dd>
                        <dd class="mt-1 text-xs text-gray-500">Sum of each line: quantity × purchase price</dd>
                    </div>
                </dl>

                @if($isBudgetAhAl)
                    {{-- Test amount (AH) detail --}}
                    <div x-show="showAhDetail" x-cloak class="mt-5 rounded-lg border border-violet-200 bg-violet-50 px-4 py-4 text-sm text-gray-900 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-violet-950">Test amount (AH) — detailed calculation</p>
                                <p class="mt-1 text-gray-700">
                                    “How much would this item cost if we bought <strong>15 days</strong> of usage?”
                                </p>
                            </div>
                            <button type="button" @click="showAhDetail = false" class="shrink-0 text-xs font-medium text-violet-700 hover:text-violet-900">Close</button>
                        </div>
                        <p class="font-mono text-[13px] bg-white border border-violet-100 rounded px-3 py-2">
                            AH = 15 × daily usage × purchase price
                        </p>
                        @if(! empty($breakdown['ah_parts']))
                            <div class="overflow-x-auto rounded border border-violet-100 bg-white">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-violet-100/60 text-violet-900">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium">Item</th>
                                            <th class="px-3 py-2 text-right font-medium">Daily usage</th>
                                            <th class="px-3 py-2 text-right font-medium">Purchase price</th>
                                            <th class="px-3 py-2 text-right font-medium">AH = 15 × usage × price</th>
                                            <th class="px-3 py-2 text-left font-medium">In Σ AH?</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-violet-50">
                                        @foreach($breakdown['ah_parts'] as $part)
                                            <tr @class(['bg-gray-50 text-gray-500' => empty($part['included_in_sum'])])>
                                                <td class="px-3 py-2">{{ $part['item_name'] }}</td>
                                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmtQty($part['v']) }}</td>
                                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmt($part['price']) }}</td>
                                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmt($part['ah']) }}</td>
                                                <td class="px-3 py-2">
                                                    @if(! empty($part['included_in_sum']))
                                                        <span class="text-emerald-700">Yes — needs stock</span>
                                                    @else
                                                        <span class="text-gray-500">No — stock days &gt; 366 (qty 0)</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="border-t border-violet-200 bg-violet-100/40">
                                        <tr>
                                            <td class="px-3 py-2 font-medium" colspan="3">Σ AH (only “Yes” rows)</td>
                                            <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums">UGX {{ $fmt($breakdown['ah_sum_test_amount']) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                        <p class="text-xs text-violet-900/80">
                            Day pool then uses this sum:
                            <span class="font-mono">15 × {{ $fmt($breakdown['budget_ugx'] ?? $breakdown['budget_value']) }} ÷ {{ $fmt($breakdown['ah_sum_test_amount']) }} = {{ $fmt($breakdown['ba7_budget_days'] ?? $breakdown['period_days'], 4) }}</span>
                        </p>
                    </div>

                    {{-- Average AM detail --}}
                    <div x-show="showAmDetail" x-cloak class="mt-5 rounded-lg border border-violet-200 bg-violet-50 px-4 py-4 text-sm text-gray-900 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-violet-950">Average days left (AM) — detailed calculation</p>
                                <p class="mt-1 text-gray-700">
                                    AM = stock days left before you must order (stock days − safety − buffer).
                                    We average only items that <strong>still need stock</strong> (stock days ≤ 366).
                                </p>
                            </div>
                            <button type="button" @click="showAmDetail = false" class="shrink-0 text-xs font-medium text-violet-700 hover:text-violet-900">Close</button>
                        </div>
                        @if(! empty($breakdown['am_parts']))
                            <div class="overflow-x-auto rounded border border-violet-100 bg-white">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-violet-100/60 text-violet-900">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium">Item</th>
                                            <th class="px-3 py-2 text-right font-medium">Days left (AM)</th>
                                            <th class="px-3 py-2 text-left font-medium">In average?</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-violet-50">
                                        @foreach($breakdown['am_parts'] as $part)
                                            <tr @class(['bg-gray-50 text-gray-500' => empty($part['included_in_average'])])>
                                                <td class="px-3 py-2">{{ $part['item_name'] }}</td>
                                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmt($part['am'], 1) }}</td>
                                                <td class="px-3 py-2">
                                                    @if(! empty($part['included_in_average']))
                                                        <span class="text-emerald-700">Yes</span>
                                                    @else
                                                        <span class="text-gray-500">No — stock days &gt; 366</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="border-t border-violet-200 bg-violet-100/40">
                                        <tr>
                                            <td class="px-3 py-2 font-medium">Σ AM (Yes rows only)</td>
                                            <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums">{{ $fmt($breakdown['am_sum_for_average'] ?? 0, 4) }}</td>
                                            <td class="px-3 py-2 text-xs text-gray-600">{{ (int) ($breakdown['am_average_count'] ?? 0) }} item(s)</td>
                                        </tr>
                                        <tr>
                                            <td class="px-3 py-2 font-medium">Average AM = Σ AM ÷ count</td>
                                            <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums">
                                                @if(($breakdown['am_average_count'] ?? 0) > 0)
                                                    {{ $fmt($breakdown['am_sum_for_average'] ?? 0, 4) }} ÷ {{ (int) $breakdown['am_average_count'] }}
                                                    = {{ $fmt($breakdown['am_average_days_left'], 4) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                        <p class="text-gray-700">
                            Then for each item: <span class="font-mono text-[13px]">AI = this item’s AM − average AM</span>.
                            Below average (more urgent) → larger share of the day pool → more quantity.
                            Far above average (already overstocked) → order days 0.
                        </p>
                    </div>
                @endif

                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950 space-y-2">
                    <p class="font-medium">Peak period on this order</p>
                    @if((float) $breakdown['peak_impact_percent'] > 0)
                        <p class="font-mono text-[13px] text-gray-900">
                            peak impact % =
                            {{ $fmt($breakdown['peak_period_percent'], 2) }}
                            × {{ $fmt($breakdown['peak_increase_percent'], 2) }}
                            ÷ 100
                            = <strong>{{ $fmt($breakdown['peak_impact_percent'], 4) }}%</strong>
                        </p>
                        <p class="font-mono text-[13px] text-gray-900">
                            final qty = base qty × (1 + {{ $fmt($breakdown['peak_impact_percent'], 4) }} ÷ 100)
                            = base qty × <strong>{{ number_format(1 + ((float) $breakdown['peak_impact_percent'] / 100), 4) }}</strong>
                        </p>
                        <p class="text-xs text-amber-900">
                            The two peak % values are <strong>multiplied</strong>, not added.
                            Then each line quantity is <strong>multiplied</strong> by that factor (so the order total goes up).
                            Do not add {{ $fmt($breakdown['peak_period_percent'], 0) }}% + {{ $fmt($breakdown['peak_increase_percent'], 0) }}%
                            — that would be {{ $fmt((float) $breakdown['peak_period_percent'] + (float) $breakdown['peak_increase_percent'], 0) }}%, which is wrong for this formula.
                        </p>
                    @else
                        <p class="text-amber-900">
                            Peak period and/or consumption increase is 0 on this order, so there is no peak uplift
                            (peak impact = 0%). Quantities stay at the base amount.
                        </p>
                        <p class="font-mono text-[13px] text-gray-800">
                            peak impact % = peak period % × consumption increase % ÷ 100<br>
                            final qty = base qty × (1 + peak impact % ÷ 100)
                        </p>
                        <p class="text-xs text-amber-900">
                            The two peak % values are <strong>multiplied</strong>, not added. Then quantity is <strong>multiplied</strong> by (1 + impact%).
                        </p>
                    @endif
                </div>

                @if(count($breakdown['lines']) > 0)
                    <div class="mt-5 overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 whitespace-nowrap">Item</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                        M
                                        <span class="block text-[10px] font-normal text-gray-400">Current stock</span>
                                    </th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                        V / AA
                                        <span class="block text-[10px] font-normal text-gray-400">15-day rate</span>
                                    </th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                        N
                                        <span class="block text-[10px] font-normal text-gray-400">M ÷ V/AA</span>
                                    </th>
                                    @if($isBudgetAhAl)
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                            AM
                                            <span class="block text-[10px] font-normal text-gray-400">N − safety − buffer</span>
                                        </th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                            AH
                                            <span class="block text-[10px] font-normal text-gray-400">15 × V × price</span>
                                        </th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                            AI
                                            <span class="block text-[10px] font-normal text-gray-400">AM − avg AM</span>
                                        </th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                            AJ
                                            <span class="block text-[10px] font-normal text-gray-400">Order days</span>
                                        </th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                            AK
                                            <span class="block text-[10px] font-normal text-gray-400">AJ × V</span>
                                        </th>
                                    @else
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                            {{ $breakdown['lines'][0]['ma_excel_column'] ?? 'MA' }}
                                            <span class="block text-[10px] font-normal text-gray-400">Period MA rate</span>
                                        </th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                            Days needed
                                            <span class="block text-[10px] font-normal text-gray-400">BA6 + AB + AD − N</span>
                                        </th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                            AF
                                            <span class="block text-[10px] font-normal text-gray-400">Days needed × period MA</span>
                                        </th>
                                    @endif
                                    <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                        Order qty
                                        <span class="block text-[10px] font-normal text-gray-400">After peak/budget</span>
                                    </th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600">Purchase price</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600 whitespace-nowrap">
                                        {{ $isBudgetAhAl ? 'AL' : 'AG' }}
                                        <span class="block text-[10px] font-normal text-gray-400">Qty × price</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-mono text-[13px]">
                                @foreach($breakdown['lines'] as $row)
                                    <tr @class(['bg-slate-50' => (float) $row['line_total'] <= 0])>
                                        <td class="px-3 py-2 font-sans text-gray-800 min-w-56">{{ $row['item_name'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtQty($row['m_current_stock']) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtQty($row['v_daily_usage']) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['n_stock_days'] !== null ? number_format($row['n_stock_days'], 1) : '—' }}</td>
                                        @if($isBudgetAhAl)
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['am_days_left'] !== null ? $fmt($row['am_days_left'], 2) : '—' }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['ah_test_amount'] ?? null) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['ai_gap_to_average'] ?? null, 4) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtQty($row['aj_order_days_expected'] ?? $row['order_days'] ?? null) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtQty($row['ak_base_qty_stored'] ?? $row['af_base_qty']) }}</td>
                                        @else
                                            <td class="px-3 py-2 text-right tabular-nums">
                                                <span class="font-semibold text-indigo-700">{{ $row['ma_excel_column'] }}</span>
                                                · {{ $fmtQty($row['implied_rate']) }}
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtQty($row['coverage']) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmtQty($row['af_base_qty']) }}</td>
                                        @endif
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtQty($row['order_qty']) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['unit_price']) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['line_total']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-gray-200 bg-slate-50">
                                <tr>
                                    <td class="px-3 py-2 font-sans font-medium text-gray-900" colspan="{{ $isBudgetAhAl ? 11 : 9 }}">
                                        Σ {{ $isBudgetAhAl ? 'AL' : 'AG' }} — Order total
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums text-gray-900">UGX {{ $fmt($breakdown['order_total']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

                <div class="mt-4 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-gray-900 space-y-1">
                    @if($isBudgetAhAl)
                        <p class="font-sans text-gray-700">
                            For every item:
                            <strong>AH</strong> = 15 × V × price,
                            <strong>AI</strong> = AM − {{ $fmt($breakdown['am_average_days_left'], 4) }},
                            @if($isBudgetAmount)
                                <strong>day pool</strong> = 15 × {{ $fmt($budgetBa7ForAj) }} ÷ {{ $fmt($breakdown['ah_sum_test_amount']) }} = {{ $fmt($budgetDayPool, 4) }},
                                <strong>AJ</strong> = day pool − AI,
                            @else
                                <strong>AJ</strong> = (15 × {{ $fmt($budgetBa7ForAj, 0) }} ÷ {{ $fmt($breakdown['ah_sum_test_amount']) }}) − AI,
                            @endif
                            <strong>AK</strong> = AJ × V,
                            <strong>AL</strong> = AK × price.
                            Urgent items (AI negative) get more AJ.
                        </p>
                    @else
                        <p class="font-sans text-gray-700">
                            For every item on this order:
                            <strong>stock days N</strong> = current stock ÷ V (15-day average),
                            then <strong>days still needed</strong> = {{ $fmt($breakdown['period_days'], 0) }} + {{ $fmt($breakdown['safety_days'], 0) }} + {{ $fmt($breakdown['buffer_days'], 0) }} − N.
                            Because the entered period is <strong>{{ $fmt($breakdown['period_days'], 0) }} days</strong>,
                            order quantity multiplies that gap by the matching V/W/X/Y/Z moving average (not by V unless period &lt; 15).
                            If there is no shortage, it orders <strong>0</strong>.
                        </p>
                    @endif
                </div>
            </div>
        </section>

        {{-- 3. Per-line explained walkthrough --}}
        <div class="mt-6" x-data="{ expandAll: false }">
            <div class="flex flex-wrap items-center justify-between gap-3 px-1 mb-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">3. Line-by-line explanation</h3>
                    <p class="mt-1 text-sm text-gray-500">Click a line to expand. Values were stored when the order was generated.</p>
                </div>
                <button type="button"
                        @click="expandAll = !expandAll; $dispatch('toggle-lines', { open: expandAll })"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    <span x-text="expandAll ? 'Collapse all' : 'Expand all'"></span>
                </button>
            </div>

            <div class="space-y-3">
                @forelse($breakdown['lines'] as $i => $row)
                    <section class="bg-white shadow sm:rounded-lg overflow-hidden"
                             id="line-{{ $row['item_id'] }}"
                             x-data="{ open: false, showAiDetail: false }"
                             @toggle-lines.window="open = $event.detail.open">
                        <button type="button"
                                @click="open = !open"
                                class="w-full px-6 py-4 flex flex-wrap items-center justify-between gap-3 text-left hover:bg-slate-50">
                            <div class="min-w-0 flex items-start gap-3">
                                <span class="mt-1 text-gray-400 text-xs font-mono w-4" x-text="open ? '▾' : '▸'"></span>
                                <div>
                                    <h4 class="text-base font-semibold text-gray-900">
                                        {{ $i + 1 }}. {{ $row['item_name'] }}
                                    </h4>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        @if($row['item_code']){{ $row['item_code'] }} · @endif
                                        {{ $row['suom'] ?? 'Sale unit' }}
                                        · V {{ $fmtQty($row['v_daily_usage']) }}
                                        · N {{ $row['n_stock_days'] !== null ? number_format($row['n_stock_days'], 1) : '—' }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-mono text-lg font-semibold tabular-nums text-gray-900">{{ $fmtQty($row['order_qty']) }}</p>
                                <p class="text-xs text-gray-500">qty · {{ $isBudgetAhAl ? 'AL' : 'AG' }} UGX {{ $fmt($row['line_total']) }}</p>
                            </div>
                        </button>

                        <div x-show="open" x-cloak class="px-6 py-5 space-y-4 border-t border-gray-200">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Step A — Inputs for this item</p>
                                <div class="overflow-x-auto rounded-lg border border-gray-200">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-medium text-gray-600">Symbol</th>
                                                <th class="px-3 py-2 text-left font-medium text-gray-600">What it is</th>
                                                <th class="px-3 py-2 text-left font-medium text-gray-600">How we got this value</th>
                                                <th class="px-3 py-2 text-right font-medium text-gray-600">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 text-[13px]">
                                            <tr>
                                                <td class="px-3 py-2 font-mono font-medium">M</td>
                                                <td class="px-3 py-2 text-gray-600">Current stock on hand</td>
                                                <td class="px-3 py-2 text-gray-600">
                                                    Stock at the receiving store when the order was made
                                                    (physical count + purchases − sales ± transfers since last count; or ledger if no count).
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmtQty($row['m_current_stock']) }}</td>
                                            </tr>
                                            <tr class="bg-indigo-50/40">
                                                <td class="px-3 py-2 font-mono font-medium">V / AA</td>
                                                <td class="px-3 py-2 text-gray-600">How many units you use per day (for stock days)</td>
                                                <td class="px-3 py-2 text-gray-600">
                                                    <strong>V</strong> = average daily consumption over the last <strong>15 days</strong>
                                                    (total units used in 15 days ÷ 15).<br>
                                                    <strong>AA</strong> = fixed daily average from inventory settings — used only if V is 0
                                                    (no recent consumption).<br>
                                                    Stock days always use this 15-day rate:
                                                    <span class="font-mono">N = M ÷ (V or AA)</span>.
                                                    @if((float) $row['v_daily_usage'] > 0)
                                                        Here <strong>V was used</strong> (15-day average &gt; 0), so AA was not needed.
                                                    @else
                                                        Here V was 0, so <strong>AA (fixed daily average)</strong> would be used.
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmtQty($row['v_daily_usage']) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 font-mono font-medium">N</td>
                                                <td class="px-3 py-2 text-gray-600">How many days current stock will last</td>
                                                <td class="px-3 py-2 text-gray-600">
                                                    N = M ÷ (V or AA)<br>
                                                    <span class="font-mono text-xs">
                                                        {{ $fmtQty($row['m_current_stock']) }}
                                                        ÷ {{ $fmtQty($row['v_daily_usage']) }}
                                                        = {{ $row['n_stock_days'] !== null ? number_format($row['n_stock_days'], 1) : '—' }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['n_stock_days'] !== null ? number_format($row['n_stock_days'], 1) : '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 font-mono font-medium">AM</td>
                                                <td class="px-3 py-2 text-gray-600">Days left after safety + buffer</td>
                                                <td class="px-3 py-2 text-gray-600">
                                                    AM = N − AB − AD<br>
                                                    <span class="font-mono text-xs">
                                                        {{ $row['n_stock_days'] !== null ? number_format($row['n_stock_days'], 1) : '—' }}
                                                        − {{ $fmt($breakdown['safety_days'], 0) }}
                                                        − {{ $fmt($breakdown['buffer_days'], 0) }}
                                                        = {{ $row['am_days_left'] !== null ? number_format($row['am_days_left'], 1) : '—' }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['am_days_left'] !== null ? number_format($row['am_days_left'], 1) : '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 font-mono font-medium">F/J</td>
                                                <td class="px-3 py-2 text-gray-600">Purchase price per sale unit (UGX)</td>
                                                <td class="px-3 py-2 text-gray-600">
                                                    Purchase cost per sale unit, from the latest GRN, stock cost, or item purchase price.
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmt($row['unit_price']) }}</td>
                                            </tr>
                                            @if($isBudgetAhAl)
                                                <tr class="bg-violet-50/60">
                                                    <td class="px-3 py-2 font-mono font-medium">AH</td>
                                                    <td class="px-3 py-2 text-gray-600">Test amount</td>
                                                    <td class="px-3 py-2 text-gray-600 font-mono text-xs">
                                                        15 × {{ $fmtQty($row['v_daily_usage']) }} × {{ $fmt($row['unit_price']) }}
                                                        = {{ $fmt($row['ah_test_amount'] ?? null) }}
                                                    </td>
                                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmt($row['ah_test_amount'] ?? null) }}</td>
                                                </tr>
                                                <tr class="bg-violet-50/60">
                                                    <td class="px-3 py-2 font-mono font-medium">AI</td>
                                                    <td class="px-3 py-2 text-gray-600">Gap to average AM</td>
                                                    <td class="px-3 py-2 text-gray-600 font-mono text-xs">
                                                        AM − (Σ AM ÷ {{ (int) ($breakdown['am_average_count'] ?? 0) }})<br>
                                                        = {{ $row['am_days_left'] !== null ? number_format($row['am_days_left'], 4) : '—' }}
                                                        − {{ $fmt($breakdown['am_average_days_left'], 4) }}
                                                        = {{ $fmt($row['ai_gap_to_average'] ?? null, 4) }}<br>
                                                        <span class="font-sans text-[11px] text-gray-500">
                                                            Σ AM = {{ $fmt($breakdown['am_sum_for_average'] ?? 0, 4) }}
                                                            · avg = {{ $fmt($breakdown['am_sum_for_average'] ?? 0, 4) }} ÷ {{ (int) ($breakdown['am_average_count'] ?? 0) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmt($row['ai_gap_to_average'] ?? null, 4) }}</td>
                                                </tr>
                                                <tr class="bg-violet-50/60">
                                                    <td class="px-3 py-2 font-mono font-medium">AJ</td>
                                                    <td class="px-3 py-2 text-gray-600">Order days</td>
                                                    <td class="px-3 py-2 text-gray-600 font-mono text-xs">
                                                        @if($isBudgetAmount)
                                                            day pool − AI =
                                                            {{ $fmt($budgetDayPool, 4) }}
                                                        @else
                                                            (15 × {{ $fmt($budgetBa7ForAj, 0) }}
                                                            ÷ {{ $fmt($breakdown['ah_sum_test_amount']) }})
                                                        @endif
                                                        − ({{ $fmt($row['ai_gap_to_average'] ?? null, 4) }})
                                                        = {{ $fmtQty($row['aj_order_days_expected'] ?? null) }}
                                                        @if(isset($row['aj_order_days_stored']))
                                                            · stored {{ $fmtQty($row['aj_order_days_stored']) }}
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmtQty($row['aj_order_days_expected'] ?? $row['order_days']) }}</td>
                                                </tr>
                                                <tr class="bg-violet-50/60">
                                                    <td class="px-3 py-2 font-mono font-medium">AK</td>
                                                    <td class="px-3 py-2 text-gray-600">Order qty (before peak)</td>
                                                    <td class="px-3 py-2 text-gray-600 font-mono text-xs">
                                                        AJ × V =
                                                        {{ $fmtQty($row['aj_order_days_expected'] ?? null) }}
                                                        × {{ $fmtQty($row['v_daily_usage']) }}
                                                        = {{ $fmtQty($row['ak_base_qty_expected'] ?? null) }}
                                                    </td>
                                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmtQty($row['ak_base_qty_stored'] ?? $row['af_base_qty']) }}</td>
                                                </tr>
                                                <tr class="bg-violet-50/60">
                                                    <td class="px-3 py-2 font-mono font-medium">AL</td>
                                                    <td class="px-3 py-2 text-gray-600">Order amount</td>
                                                    <td class="px-3 py-2 text-gray-600 font-mono text-xs">
                                                        final qty × price =
                                                        {{ $fmtQty($row['order_qty']) }} × {{ $fmt($row['unit_price']) }}
                                                    </td>
                                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $fmt($row['line_total']) }}</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-950 space-y-3"
                                     x-data="{ showDays: false }">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="font-medium">Values used for V (15-day average) — stock days N</p>
                                        <button type="button"
                                                @click="showDays = !showDays"
                                                class="text-xs font-medium text-indigo-700 hover:text-indigo-900">
                                            <span x-text="showDays ? 'Hide daily values' : 'Show daily values'"></span>
                                        </button>
                                    </div>
                                    <p class="text-xs text-indigo-800">
                                        @if($isBudgetAhAl)
                                            Budget path uses V/AA (15-day) for N, AM, AH, and AK.
                                            <span class="font-mono">N = M ÷ (V or AA)</span>.
                                        @else
                                            Stock days always use V (or AA if V = 0): <span class="font-mono">N = M ÷ (V or AA)</span>.
                                            This is separate from the period-based moving average used for order quantity.
                                        @endif
                                    </p>
                                    <p>
                                        Formula:
                                        <span class="font-mono">V = (day1 + day2 + … + day15) ÷ 15</span>
                                        · days with no sales count as <strong>0</strong>
                                    </p>
                                    <p>
                                        Window:
                                        <strong class="font-mono">{{ $row['v_window_from'] ?? '—' }}</strong>
                                        →
                                        <strong class="font-mono">{{ $row['v_window_to'] ?? '—' }}</strong>
                                        · stored V = <strong class="font-mono">{{ $fmtQty($row['v_daily_usage']) }}</strong>
                                    </p>

                                    <div x-show="showDays" x-cloak class="space-y-3">
                                    @if(! empty($row['v_day_values']))
                                        <div class="overflow-x-auto rounded-md border border-indigo-200 bg-white">
                                            <table class="min-w-full text-xs">
                                                <thead class="bg-indigo-100/60">
                                                    <tr>
                                                        <th class="px-2 py-1.5 text-left font-medium text-indigo-900">Date</th>
                                                        <th class="px-2 py-1.5 text-right font-medium text-indigo-900">Units used that day</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-indigo-100 font-mono">
                                                    @foreach($row['v_day_values'] as $day)
                                                        <tr>
                                                            <td class="px-2 py-1 text-gray-700">{{ $day['date'] }}</td>
                                                            <td class="px-2 py-1 text-right tabular-nums {{ (float) $day['quantity'] <= 0 ? 'text-gray-400' : 'text-gray-900' }}">
                                                                {{ number_format((float) $day['quantity'], 4) }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="border-t border-indigo-200 bg-indigo-50">
                                                    <tr>
                                                        <td class="px-2 py-1.5 font-sans font-medium text-indigo-950">Total (15 days)</td>
                                                        <td class="px-2 py-1.5 text-right font-mono font-semibold tabular-nums">{{ $fmtQty($row['v_day_total'] ?? 0) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="px-2 py-1.5 font-sans font-medium text-indigo-950">÷ 15 = V from these days</td>
                                                        <td class="px-2 py-1.5 text-right font-mono font-semibold tabular-nums">{{ $fmtQty($row['v_day_average'] ?? 0) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="px-2 py-1.5 font-sans font-medium text-indigo-950">V stored on this order line</td>
                                                        <td class="px-2 py-1.5 text-right font-mono font-semibold tabular-nums">{{ $fmtQty($row['v_daily_usage']) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        @if(abs((float) ($row['v_day_average'] ?? 0) - (float) $row['v_daily_usage']) >= 0.01)
                                            <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                                                The order line stored V = <strong class="font-mono">{{ $fmtQty($row['v_daily_usage']) }}</strong>
                                                from the stock record’s 15-day average at generation time
                                                (that equals about <strong class="font-mono">{{ $fmt((float) $row['v_daily_usage'] * 15, 2) }}</strong> units over 15 days ÷ 15).
                                                Recalculating from consumption dates above now gives
                                                <strong class="font-mono">{{ $fmtQty($row['v_day_average'] ?? 0) }}</strong>.
                                                Refresh the order if you want quantities based on the latest consumption.
                                            </p>
                                        @endif
                                    @endif
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Step B — Apply the formula
                                    
                                </p>

                                @if($isBudgetAhAl)
                                <div class="font-mono text-sm text-gray-900 space-y-3">
                                    <p class="font-sans text-xs text-gray-500">AH — Test Amount</p>
                                    <p>
                                        AH = 15 × (V or AA) × (F/J)
                                        = 15 × {{ $fmtQty($row['v_daily_usage']) }} × {{ $fmt($row['unit_price']) }}
                                        = <strong>{{ $fmt($row['ah_test_amount'] ?? null) }}</strong>
                                    </p>

                                    <p class="font-sans text-xs text-gray-500 pt-1">AI — Gap to Average Days left to Order</p>
                                    <div class="space-y-2">
                                        <p>
                                            AI = AM − (Σ AM ÷ count AM)
                                            = {{ $row['am_days_left'] !== null ? number_format($row['am_days_left'], 4) : '—' }}
                                            − {{ $fmt($breakdown['am_average_days_left'], 4) }}
                                            = <strong>{{ $fmt($row['ai_gap_to_average'] ?? null, 4) }}</strong>
                                        </p>
                                        <button type="button"
                                                @click="showAiDetail = !showAiDetail"
                                                class="font-sans text-xs font-medium text-violet-700 hover:text-violet-900">
                                            <span x-text="showAiDetail ? 'Hide AI breakdown' : 'Show AI breakdown (AM, Σ AM, average)'"></span>
                                        </button>
                                        <div x-show="showAiDetail" x-cloak class="rounded-md border border-violet-200 bg-violet-50 px-3 py-3 space-y-3 font-sans text-sm text-gray-900">
                                            <div>
                                                <p class="font-medium text-violet-950">1. This item’s AM</p>
                                                <p class="font-mono text-[13px] mt-1">
                                                    AM = N − AB − AD
                                                    = {{ $row['n_stock_days'] !== null ? number_format($row['n_stock_days'], 4) : '—' }}
                                                    − {{ $fmt($breakdown['safety_days'], 0) }}
                                                    − {{ $fmt($breakdown['buffer_days'], 0) }}
                                                    = <strong>{{ $row['am_days_left'] !== null ? number_format($row['am_days_left'], 4) : '—' }}</strong>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="font-medium text-violet-950">2. Average AM — each item included</p>
                                                <p class="text-xs text-gray-600 mt-0.5">
                                                    Only items with stock days ≤ 366 are included (same as Σ AH).
                                                </p>
                                                @if(! empty($breakdown['am_parts']))
                                                    <div class="mt-2 overflow-x-auto rounded border border-violet-100 bg-white">
                                                        <table class="min-w-full text-xs">
                                                            <thead class="bg-violet-100/60 text-violet-900">
                                                                <tr>
                                                                    <th class="px-2 py-1.5 text-left font-medium">Item</th>
                                                                    <th class="px-2 py-1.5 text-right font-medium">N</th>
                                                                    <th class="px-2 py-1.5 text-right font-medium">AM</th>
                                                                    <th class="px-2 py-1.5 text-left font-medium">In avg?</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-violet-50 font-mono">
                                                                @foreach($breakdown['am_parts'] as $amPart)
                                                                    <tr @class([
                                                                        'bg-violet-100/50 font-semibold' => (int) ($amPart['item_id'] ?? 0) === (int) $row['item_id'],
                                                                        'bg-gray-50 text-gray-500' => empty($amPart['included_in_average']),
                                                                    ])>
                                                                        <td class="px-2 py-1 font-sans text-gray-800">
                                                                            {{ $amPart['item_name'] }}
                                                                            @if((int) ($amPart['item_id'] ?? 0) === (int) $row['item_id'])
                                                                                <span class="text-violet-700">(this line)</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-2 py-1 text-right tabular-nums">
                                                                            {{ $amPart['n'] !== null ? number_format($amPart['n'], 1) : '—' }}
                                                                        </td>
                                                                        <td class="px-2 py-1 text-right tabular-nums">{{ $fmt($amPart['am'], 4) }}</td>
                                                                        <td class="px-2 py-1 font-sans">
                                                                            @if(! empty($amPart['included_in_average']))
                                                                                <span class="text-emerald-700">Yes</span>
                                                                            @else
                                                                                <span class="text-gray-500">No</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                            <tfoot class="border-t border-violet-200 bg-violet-50">
                                                                <tr>
                                                                    <td class="px-2 py-1.5 font-sans font-medium" colspan="2">Σ AM (Yes rows)</td>
                                                                    <td class="px-2 py-1.5 text-right font-semibold tabular-nums">{{ $fmt($breakdown['am_sum_for_average'] ?? 0, 4) }}</td>
                                                                    <td class="px-2 py-1.5 font-sans text-gray-600">{{ (int) ($breakdown['am_average_count'] ?? 0) }} item(s)</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="px-2 py-1.5 font-sans font-medium" colspan="2">Σ AM ÷ count = average AM</td>
                                                                    <td class="px-2 py-1.5 text-right font-semibold tabular-nums" colspan="2">
                                                                        @if(($breakdown['am_average_count'] ?? 0) > 0)
                                                                            {{ $fmt($breakdown['am_sum_for_average'] ?? 0, 4) }}
                                                                            ÷ {{ (int) $breakdown['am_average_count'] }}
                                                                            = {{ $fmt($breakdown['am_average_days_left'], 4) }}
                                                                        @else
                                                                            —
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <p class="font-medium text-violet-950">3. AI for this item</p>
                                                <p class="font-mono text-[13px] mt-1">
                                                    AI = this AM − average AM
                                                    = {{ $row['am_days_left'] !== null ? number_format($row['am_days_left'], 4) : '—' }}
                                                    − {{ $fmt($breakdown['am_average_days_left'], 4) }}
                                                    = <strong>{{ $fmt($row['ai_gap_to_average'] ?? null, 4) }}</strong>
                                                </p>
                                                <p class="text-xs text-violet-900 mt-1">
                                                    Negative AI → more urgent than average → more order days (AJ).
                                                    Positive AI → less urgent → fewer order days.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="font-sans text-xs text-gray-500 pt-1">AJ — Order Days</p>
                                    <p>
                                        @if($isBudgetAmount)
                                            day pool = 15 × BA7 ÷ Σ AH
                                            = 15 × {{ $fmt($budgetBa7ForAj) }} ÷ {{ $fmt($breakdown['ah_sum_test_amount']) }}
                                            = <strong>{{ $fmt($budgetDayPool, 4) }}</strong><br>
                                            AJ = day pool − AI
                                            = {{ $fmt($budgetDayPool, 4) }}
                                        @else
                                            AJ = (15 × BA7 ÷ Σ AH) − AI
                                            = (15 × {{ $fmt($budgetBa7ForAj, 0) }}
                                            ÷ {{ $fmt($breakdown['ah_sum_test_amount']) }})
                                        @endif
                                        − ({{ $fmt($row['ai_gap_to_average'] ?? null, 4) }})
                                        = <strong>{{ $fmtQty($row['aj_order_days_expected'] ?? $row['order_days']) }}</strong>
                                        @if(isset($row['aj_order_days_stored']))
                                            <span class="font-sans text-xs text-gray-500">(stored {{ $fmtQty($row['aj_order_days_stored']) }})</span>
                                        @endif
                                    </p>

                                    <p class="font-sans text-xs text-gray-500 pt-1">AK — Order Qty</p>
                                    <p>
                                        AK = AJ × (V or AA)
                                        = {{ $fmtQty($row['aj_order_days_expected'] ?? $row['order_days']) }}
                                        × {{ $fmtQty($row['v_daily_usage']) }}
                                        = <strong>{{ $fmtQty($row['ak_base_qty_stored'] ?? $row['af_base_qty']) }}</strong>
                                    </p>

                                    @if($row['peak_impact_percent'] > 0)
                                        <p class="font-sans text-xs text-gray-500 pt-1">Peak uplift (after AK)</p>
                                        <p>
                                            {{ $fmtQty($row['ak_base_qty_stored'] ?? $row['af_base_qty']) }}
                                            × (1 + {{ $fmt($row['peak_impact_percent'], 4) }} ÷ 100)
                                            = <strong>{{ $fmtQty($row['qty_after_peak']) }}</strong>
                                        </p>
                                    @endif

                                    @if($breakdown['scale_factor'] !== null && abs($breakdown['scale_factor'] - 1) >= 0.000001)
                                        <p class="font-sans text-xs text-gray-500 pt-1">Fit to budget cap</p>
                                        <p>
                                            {{ $fmtQty($row['qty_after_peak']) }} × {{ number_format($breakdown['scale_factor'], 6) }}
                                            = <strong>{{ $fmtQty($row['order_qty']) }}</strong>
                                        </p>
                                        <p class="font-sans text-xs text-amber-800">
                                            Quantities were scaled so Σ AL does not exceed the UGX budget cap.
                                        </p>
                                    @endif

                                    <p class="font-sans text-xs text-gray-500 pt-1">AL — Order amount</p>
                                    <p>
                                        AL = order qty × (F/J)
                                        = {{ $fmtQty($row['order_qty']) }} × {{ $fmt($row['unit_price']) }}
                                        = <strong>UGX {{ $fmt($row['line_total']) }}</strong>
                                    </p>

                                    @if($row['skipped_reason'])
                                        <p class="font-sans text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2 text-sm">
                                            {{ $row['skipped_reason'] }}
                                        </p>
                                    @endif
                                    @if(! empty($row['matches_ah_ak'] ?? $row['matches_ah_al'] ?? false))
                                        <p class="font-sans text-xs text-emerald-700">✓ Matches AH→AK from stored snapshots</p>
                                    @endif
                                    @if(($breakdown['scale_factor'] ?? null) !== null && abs((float) $breakdown['scale_factor'] - 1) >= 0.000001)
                                        <p class="font-sans text-xs text-amber-700">Final qty/AL may differ after budget-cap scaling (see above).</p>
                                    @endif
                                </div>
                                @else
                                <div class="font-mono text-sm text-gray-900 space-y-2">
                                    <p class="font-sans text-xs text-gray-500">1. Days still needed</p>
                                    <p>
                                        ( period + safety + buffer − stock days )
                                        = {{ $fmt($breakdown['period_days'], 0) }}
                                        + {{ $fmt($breakdown['safety_days'], 0) }}
                                        + {{ $fmt($breakdown['buffer_days'], 0) }}
                                        − {{ $row['n_stock_days'] !== null ? number_format($row['n_stock_days'], 1) : '0' }}
                                        = <strong>{{ $fmtQty($row['coverage']) }}</strong>
                                    </p>

                                    <p class="font-sans text-xs text-gray-500 pt-1">2. Which daily usage rate — and why?</p>
                                    <div class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2.5 space-y-1.5">
                                        <p class="font-sans text-sm text-indigo-950">
                                            Used: <strong class="font-mono">{{ $row['ma_excel_column'] }}</strong>
                                            — {{ $row['graduated_ma_window'] }}
                                            @if($row['implied_rate'] !== null)
                                                · <strong class="font-mono">{{ $fmtQty($row['implied_rate']) }}</strong> units per day
                                            @endif
                                        </p>
                                        <p class="font-sans text-sm text-indigo-900">
                                            <span class="font-medium">Why:</span> {{ $row['ma_reason'] }}
                                        </p>
                                        <p class="font-sans text-xs text-indigo-800">
                                            The entered period selects the average used for <strong>order quantity</strong>:
                                            under 15 → V, under 30 → W, under 90 → X, under 180 → Y, otherwise → Z.
                                            Stock days still use V/AA only: <span class="font-mono">N = M ÷ (V or AA)</span>.
                                        </p>
                                        @if(! empty($row['rate_source_note']))
                                            <p class="font-sans text-xs text-indigo-800">{{ $row['rate_source_note'] }}</p>
                                        @endif
                                        @if($row['ma_excel_column'] === 'V')
                                            <p class="font-sans text-xs text-indigo-700">
                                                15-day / fixed daily usage on this line (V / AA) = <span class="font-mono">{{ $fmtQty($row['v_daily_usage']) }}</span>
                                            </p>
                                        @endif
                                    </div>

                                    @if(! empty($row['excel_expected_rate']) && empty($row['rate_matches_excel']))
                                        <p class="font-sans text-sm text-red-800 bg-red-50 border border-red-200 rounded px-3 py-2">
                                            The selected {{ $row['graduated_ma_window'] }} rate should be <strong class="font-mono">{{ $fmtQty($row['excel_expected_rate']) }}</strong>,
                                            so quantity should be <strong class="font-mono">{{ $fmtQty($row['excel_expected_base_qty']) }}</strong>.
                                            This order still has an older rate (<strong class="font-mono">{{ $fmtQty($row['implied_rate']) }}</strong>) — refresh the order to recalculate.
                                        </p>
                                    @endif

                                    <p class="font-sans text-xs text-gray-500 pt-1">3. Quantity to order</p>
                                    @if($row['skipped_reason'])
                                        <p class="font-sans text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                                            Order quantity = <strong>0</strong> — you already have enough stock (no shortage).
                                            <span class="block text-xs mt-1 text-amber-700">That is what max(0, …) does: never order a negative amount.</span>
                                        </p>
                                    @else
                                        <p class="font-sans text-sm text-gray-700 mb-1">
                                            Days still needed × daily usage. If that would be negative, use 0 instead (max):
                                        </p>
                                        <p>
                                            {{ $fmtQty($row['coverage']) }} × {{ $fmtQty($row['implied_rate']) }}
                                            = <strong>{{ $fmtQty($row['af_base_qty']) }}</strong>
                                            @if($row['matches_af'])
                                                <span class="font-sans text-xs text-emerald-700 ml-1">✓</span>
                                            @endif
                                        </p>
                                    @endif

                                    <p class="font-sans text-xs text-gray-500 pt-1">4. Peak uplift (multiply, do not add)</p>
                                    @if($row['peak_impact_percent'] > 0)
                                        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 space-y-1.5 font-sans text-sm text-amber-950">
                                            <p>
                                                Impact =
                                                {{ $fmt($breakdown['peak_period_percent'], 2) }}%
                                                × {{ $fmt($breakdown['peak_increase_percent'], 2) }}%
                                                ÷ 100
                                                = <strong class="font-mono">{{ $fmt($row['peak_impact_percent'], 4) }}%</strong>
                                            </p>
                                            <p class="font-mono text-[13px] text-gray-900">
                                                {{ $fmtQty($row['af_base_qty']) }}
                                                × (1 + {{ $fmt($row['peak_impact_percent'], 4) }} ÷ 100)
                                                = <strong>{{ $fmtQty($row['qty_after_peak']) }}</strong>
                                            </p>
                                            <p class="text-xs text-amber-800">
                                                Multiply the two peak %, then multiply the quantity. Adding the percentages would be wrong.
                                            </p>
                                        </div>
                                    @else
                                        <p class="font-sans text-sm text-gray-600">No peak uplift → quantity stays as above</p>
                                    @endif

                                    @if($breakdown['scale_factor'] !== null && abs($breakdown['scale_factor'] - 1) >= 0.000001)
                                        <p class="font-sans text-xs text-gray-500 pt-1">5. Fit to budget</p>
                                        <p>
                                            {{ $fmtQty($row['qty_after_peak']) }} × {{ number_format($breakdown['scale_factor'], 6) }}
                                            = <strong>{{ $fmtQty($row['order_qty']) }}</strong>
                                        </p>
                                    @endif

                                    <p class="font-sans text-xs text-gray-500 pt-1">Line cost</p>
                                    <p>
                                        {{ $fmtQty($row['order_qty']) }} × {{ $fmt($row['unit_price']) }}
                                        = <strong>UGX {{ $fmt($row['line_total']) }}</strong>
                                    </p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </section>
                @empty
                    <div class="bg-white shadow sm:rounded-lg px-6 py-8 text-center text-sm text-gray-500">
                        No lines on this order.
                    </div>
                @endforelse
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-gray-500 pb-6">
            Values are snapshots from when the order was generated or last refreshed.
            <a href="{{ route('inventory.orders.show', $order) }}" class="text-blue-600 hover:text-blue-800">Return to order</a>
        </p>
    </div>
</div>
</x-app-layout>
