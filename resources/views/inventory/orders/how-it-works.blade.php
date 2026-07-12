<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">How inventory ordering works</h2>
                <p class="mt-1 text-sm text-gray-500">By period (AF) or by budget (AH → AL), matching the Inventory sheet.</p>
            </div>
            <a href="{{ route('inventory.orders.create') }}"
               class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                Make an order
            </a>
        </div>

        @include('inventory.partials.subnav')

        <div class="mt-6 space-y-6">
            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Method 1 — By period (days)</h3>
                    <p class="text-sm text-gray-500 mt-0.5">You enter <strong>days</strong> (BA6). The system shows the <strong>amount</strong> (Σ AG).</p>
                </div>
                <div class="px-6 py-5 font-mono text-sm text-gray-900 space-y-2">
                    <p>AF = max(0, (BA6 + AB + AD − N) × graduated_MA(N))</p>
                    <p>AG = AF × (F/J)</p>
                </div>
            </section>

            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Method 2 — By budget (UGX)</h3>
                    <p class="text-sm text-gray-500 mt-0.5">You enter <strong>amount</strong>. Excel <strong>BA7</strong> is UGX (“Order by Budget (UGX)”), then AH → AL.</p>
                </div>
                <div class="px-6 py-5 space-y-3 font-mono text-sm text-gray-900">
                    <p><span class="font-sans text-xs font-semibold uppercase text-violet-700">AH</span> Test Amount = 15 × (V or AA if V = 0) × (F/J)</p>
                    <p><span class="font-sans text-xs font-semibold uppercase text-violet-700">AI</span> Gap to Average Days left = AM − (Σ AM ÷ count AM)</p>
                    <p><span class="font-sans text-xs font-semibold uppercase text-violet-700">AJ</span> Order Days = (15 × BA7 ÷ Σ AH) − AI</p>
                    <p><span class="font-sans text-xs font-semibold uppercase text-violet-700">AK</span> Order Qty = AJ × (V or AA if V = 0)</p>
                    <p><span class="font-sans text-xs font-semibold uppercase text-violet-700">AL</span> Order amount = AK × (F/J)</p>
                    <p class="font-sans text-xs text-gray-500 pt-2">Selected items always stay on the order. Items with more than 366 stock days get quantity 0 so they do not skew AVERAGE(AM).</p>
                </div>
            </section>

            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">External procurement flow</h3>
                    <p class="text-sm text-gray-500 mt-0.5">After quantities are set on an external RFQ.</p>
                </div>
                <ol class="px-6 py-5 list-decimal list-inside space-y-2 text-sm text-gray-800">
                    <li>Submit → configured approvers (1–2 as selected) approve in order.</li>
                    <li>On full approve, invited suppliers get a price-hidden RFQ PDF (if email is set).</li>
                    <li>Invite more suppliers and open <strong>Quotation analysis</strong> to compare quotes.</li>
                    <li>Accept one or more suppliers → generate LPOs (entity code prefixes LPO numbers).</li>
                    <li>Issue LPO → create GRN → record QC inspection (must Pass) → approve to post stock.</li>
                </ol>
            </section>

            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Internal transfer flow</h3>
                </div>
                <ol class="px-6 py-5 list-decimal list-inside space-y-2 text-sm text-gray-800">
                    <li>Internal order → approve → stock transfer.</li>
                    <li>Transfer approve moves source stock to <strong>in transit</strong> (available ↓).</li>
                    <li>Destination confirms receipt → in-transit cleared, destination stock ↑.</li>
                </ol>
            </section>
        </div>
    </div>
</div>
</x-app-layout>
