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
                New order
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
                    <p>N = M ÷ (V or AA) <span class="font-sans text-xs text-gray-500">— stock days always use the 15-day rate</span></p>
                    <p>AF = max(0, (BA6 + AB + AD − N) × graduated_MA(period))</p>
                    <p class="font-sans text-xs text-gray-500">Period selects V/W/X/Y/Z for AF only. N still uses V/AA.</p>
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
                    <p class="font-sans text-xs text-gray-500 pt-2">N and AM always use V/AA (15-day): N = M ÷ (V or AA). Selected items always stay on the order. Items with more than 366 stock days get quantity 0 so they do not skew AVERAGE(AM) or Σ AH. If peak uplift pushes Σ AL above the UGX budget, quantities are scaled down proportionally.</p>
                </div>
            </section>

            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">External procurement flow</h3>
                    <p class="text-sm text-gray-500 mt-0.5">After quantities are set on an external RFQ.</p>
                </div>
                <ol class="px-6 py-5 list-decimal list-inside space-y-2 text-sm text-gray-800">
                    <li><strong>Purchase request</strong> — select items / budget, review quantities (no supplier yet).</li>
                    <li><strong>Digital approval</strong> — configured approvers (1–2 as selected) approve; Finance is notified.</li>
                    <li><strong>RFQ</strong> — price-hidden PDF; invite and distribute to suppliers.</li>
                    <li><strong>Quotation analysis</strong> — computation sheet; admin accepts one or more suppliers.</li>
                    <li><strong>LPO issuance</strong> — one LPO per accepted quote; entity code prefixes LPO numbers.</li>
                    <li><strong>Transmit</strong> — issue LPO emails supplier (and finance/approvers).</li>
                    <li><strong>Delivery &amp; QC</strong> — GRN with mandatory inspection (pass) before stock posts.</li>
                </ol>
            </section>

            <section class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Internal transfer flow</h3>
                </div>
                <ol class="px-6 py-5 list-decimal list-inside space-y-2 text-sm text-gray-800">
                    <li>Internal order (source → receiving store) → submit → approve.</li>
                    <li>On full approval, a <strong>draft stock transfer</strong> is prepared (or create one manually).</li>
                    <li>Transfer approve moves source stock to <strong>in transit</strong> (available ↓).</li>
                    <li>Destination confirms receipt → in-transit cleared, destination stock ↑.</li>
                    <li>Linked order becomes <strong>partially fulfilled</strong> or <strong>fulfilled</strong>; remaining qty can start another transfer.</li>
                </ol>
            </section>
        </div>
    </div>
</div>
</x-app-layout>
