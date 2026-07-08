<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <a href="{{ route('inventory.orders.create') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to make an order</a>
                <h2 class="mt-2 text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">Ordering formulas</h2>
                <p class="mt-1 text-sm text-gray-500">Reference for <strong>By period (days)</strong> and <strong>By budget (UGX)</strong>. Excel columns from the Inventory sheet.</p>
            </div>
            <a href="{{ route('inventory.orders.create') }}"
               class="shrink-0 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                Make an order
            </a>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 space-y-6">
            {{-- Base inputs --}}
            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-slate-50">
                    <h3 class="text-lg font-semibold text-gray-900">Base inputs <span class="text-sm font-normal text-gray-500">(both methods)</span></h3>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 w-28">Symbol</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 w-16">Excel</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700">Formula</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-mono text-[13px] text-gray-800">
                                <tr class="bg-white">
                                    <td class="px-4 py-3 font-sans font-medium text-gray-900">M</td>
                                    <td class="px-4 py-3 text-gray-500">M</td>
                                    <td class="px-4 py-3">current stock (SUOM) = AS + purchases − sales ± transfers since last count<br><span class="font-sans text-gray-500 text-xs">If no count: on-hand ledger quantity</span></td>
                                </tr>
                                <tr class="bg-white">
                                    <td class="px-4 py-3 font-sans font-medium text-gray-900">V / AA</td>
                                    <td class="px-4 py-3 text-gray-500">V, AA</td>
                                    <td class="px-4 py-3">daily usage = 15-day MA<br><span class="font-sans text-gray-500 text-xs">If V = 0 → AA (module fixed daily average)</span></td>
                                </tr>
                                <tr class="bg-white">
                                    <td class="px-4 py-3 font-sans font-medium text-gray-900">N</td>
                                    <td class="px-4 py-3 text-gray-500">N</td>
                                    <td class="px-4 py-3">stock days = M ÷ (V or AA)</td>
                                </tr>
                                <tr class="bg-white">
                                    <td class="px-4 py-3 font-sans font-medium text-gray-900">AB</td>
                                    <td class="px-4 py-3 text-gray-500">AB</td>
                                    <td class="px-4 py-3">safety days = item → order → module config</td>
                                </tr>
                                <tr class="bg-white">
                                    <td class="px-4 py-3 font-sans font-medium text-gray-900">AD</td>
                                    <td class="px-4 py-3 text-gray-500">AD</td>
                                    <td class="px-4 py-3">buffer days = item → order → module config</td>
                                </tr>
                                <tr class="bg-slate-50">
                                    <td class="px-4 py-3 font-sans font-medium text-gray-900">AM</td>
                                    <td class="px-4 py-3 text-gray-500">AM</td>
                                    <td class="px-4 py-3 font-semibold">days left to order = N − AB − AD</td>
                                </tr>
                                <tr class="bg-white">
                                    <td class="px-4 py-3 font-sans font-medium text-gray-900">F/J</td>
                                    <td class="px-4 py-3 text-gray-500">F/J</td>
                                    <td class="px-4 py-3">unit price (UGX/SUOM) = latest GRN price → stock cost → item purchase price → default price</td>
                                </tr>
                                <tr class="bg-white">
                                    <td class="px-4 py-3 font-sans font-medium text-gray-900">AG</td>
                                    <td class="px-4 py-3 text-gray-500">AG</td>
                                    <td class="px-4 py-3">line total (UGX) = order qty × (F/J)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- Method 1 --}}
            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Method 1 — By period (days)</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Excel AF, BA6 · Input: <strong>period of order (days)</strong></p>
                </div>
                <div class="px-6 py-5 space-y-5">
                    <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 space-y-3 font-mono text-sm text-gray-900">
                        <p><span class="font-sans text-xs font-semibold uppercase text-blue-700">Step 1 — Coverage gap</span></p>
                        <p class="text-base">coverage = period + AB + AD − N</p>
                        <p class="font-sans text-xs text-gray-600">If coverage ≤ 0 → order qty = 0 (skip line)</p>

                        <p class="pt-2"><span class="font-sans text-xs font-semibold uppercase text-blue-700">Step 2 — Graduated moving average (rate)</span></p>
                        <p>rate = graduated_MA(N)</p>

                        <p class="pt-2"><span class="font-sans text-xs font-semibold uppercase text-blue-700">Step 3 — Base order qty (AF)</span></p>
                        <p class="text-base">base_qty = max(0, coverage × rate)</p>
                        <p class="font-sans text-xs text-gray-600">If rate = 0 → use V or AA instead</p>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700">If stock days N is…</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700">graduated_MA uses</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-mono text-[13px]">
                                <tr><td class="px-4 py-2">N &lt; 15</td><td class="px-4 py-2">15-day MA</td></tr>
                                <tr><td class="px-4 py-2">N &lt; 30</td><td class="px-4 py-2">30-day MA</td></tr>
                                <tr><td class="px-4 py-2">N &lt; 90</td><td class="px-4 py-2">90-day MA</td></tr>
                                <tr><td class="px-4 py-2">N &lt; 180</td><td class="px-4 py-2">180-day MA</td></tr>
                                <tr><td class="px-4 py-2">otherwise</td><td class="px-4 py-2">360-day MA</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Numeric example</p>
                        <div class="mt-3 font-mono text-sm text-gray-800 space-y-1">
                            <p>period = 30, AB = 10, AD = 5, N = 5</p>
                            <p>coverage = 30 + 10 + 5 − 5 = <strong>40</strong></p>
                            <p>rate = 10 SUOM/day (N &lt; 15 → 15-day MA)</p>
                            <p class="text-blue-800 font-semibold">base_qty = 40 × 10 = 400 SUOM</p>
                            <p class="font-sans text-xs text-gray-500 pt-1">line total = 400 × (F/J)</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Peak --}}
            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Peak period uplift <span class="text-sm font-normal text-gray-500">(optional, both methods)</span></h3>
                </div>
                <div class="px-6 py-5">
                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 font-mono text-sm text-gray-900 space-y-2">
                        <p>peak_impact % = peak_period % × consumption_increase % ÷ 100</p>
                        <p class="text-base font-semibold">final_qty = base_qty × (1 + peak_impact ÷ 100)</p>
                    </div>
                </div>
            </section>

            {{-- Method 2 --}}
            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Method 2 — By budget (UGX)</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Input: <strong>budget (UGX)</strong> · Uses Method 1 formulas, then scales to the cap</p>
                </div>
                <div class="px-6 py-5 space-y-5">
                    <div class="rounded-lg bg-violet-50 border border-violet-200 p-4 space-y-4 font-mono text-sm text-gray-900">
                        <div>
                            <p class="font-sans text-xs font-semibold uppercase text-violet-700">Step 1 — Infer period (days)</p>
                            <p class="mt-1">For P = 1 … 366:</p>
                            <p class="mt-1 pl-4">total(P) = Σ<sub>items</sub> AF(P) × (1 + peak_impact÷100) × (F/J)</p>
                            <p class="mt-1">period = largest P where total(P) ≤ budget</p>
                        </div>
                        <div>
                            <p class="font-sans text-xs font-semibold uppercase text-violet-700">Step 2 — Base quantities</p>
                            <p class="mt-1">base_qty<sub>i</sub> = AF(period) × (1 + peak_impact÷100) &nbsp;<span class="font-sans text-xs text-gray-600">per item</span></p>
                        </div>
                        <div>
                            <p class="font-sans text-xs font-semibold uppercase text-violet-700">Step 3 — Scale to budget</p>
                            <p class="mt-1">factor = budget ÷ Σ (base_qty<sub>i</sub> × F/J<sub>i</sub>)</p>
                            <p class="mt-1 text-base font-semibold">order_qty<sub>i</sub> = base_qty<sub>i</sub> × factor</p>
                            <p class="mt-1">line_total<sub>i</sub> = order_qty<sub>i</sub> × F/J<sub>i</sub></p>
                            <p class="font-sans text-xs text-gray-600 mt-1">Scales up or down so Σ line totals = budget exactly</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Numeric example</p>
                        <div class="mt-3 font-mono text-sm text-gray-800 space-y-1">
                            <p>Period order: period = 67 → order total = <strong>44,675 UGX</strong></p>
                            <p>Budget order: budget = 44,675, same store / filters / AB / AD</p>
                            <p>→ inferred period = 67, factor ≈ 1</p>
                            <p class="text-violet-800 font-semibold">order_qty<sub>i</sub> matches the period order line-for-line</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Side by side --}}
            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">At a glance</h3>
                </div>
                <div class="px-6 py-5 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-700"></th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">By period</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">By budget</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-mono text-[13px] text-gray-800">
                            <tr>
                                <td class="px-4 py-3 font-sans font-medium text-gray-900">Input</td>
                                <td class="px-4 py-3">period (days)</td>
                                <td class="px-4 py-3">budget (UGX)</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-sans font-medium text-gray-900">Core formula</td>
                                <td class="px-4 py-3">AF = max(0, (period+AB+AD−N) × rate)</td>
                                <td class="px-4 py-3">AF(period*) then × factor</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-sans font-medium text-gray-900">Order total</td>
                                <td class="px-4 py-3">Σ (qty × F/J)</td>
                                <td class="px-4 py-3">= budget (exact)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p class="text-center text-xs text-gray-500 pb-2">
                Verify M, N, AM on <a href="{{ route('inventory.monitor') }}" class="text-blue-600 hover:text-blue-800">Monitor Stock</a>.
                Refresh a draft order after stock changes.
            </p>

            <div class="flex justify-center pb-4">
                <a href="{{ route('inventory.orders.create') }}"
                   class="inline-flex items-center px-5 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    Make an order
                </a>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
