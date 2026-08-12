@php
    $invoice = $event->invoice;
    $amountDue = $invoice ? (float) $invoice->balance_due : 0;
    $methodLabels = \App\Services\Inventory\InventoryUsagePaymentService::paymentMethodLabels();
    $redirectUrl = $redirectUrl ?? route('inventory.usage.show', $event);
    $collectPaymentUrl = route('inventory.usage.collect-payment', $event);
@endphp

@if($canCollectPayment && ! \App\Support\InventoryBusinessContext::isAdminBrowsing() && $paymentMethods !== [])
    <div class="mt-6 bg-white border border-blue-200 shadow-sm sm:rounded-lg overflow-hidden" id="usage-collect-payment" data-collect-url="{{ $collectPaymentUrl }}" data-redirect-url="{{ $redirectUrl }}">
        <div class="px-4 py-3 border-b border-blue-100 bg-blue-50">
            <h2 class="text-sm font-medium text-blue-900">Collect payment</h2>
            <p class="mt-1 text-xs text-blue-800">
                Postpaid charge for floor stock / crash cart usage. Stock was already deducted — this only collects money for
                @if($invoice)
                    <span class="font-mono">{{ $invoice->invoice_number }}</span>.
                @else
                    the linked invoice.
                @endif
            </p>
        </div>

        <div class="p-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm">
                <span class="text-gray-600">Balance due</span>
                <span class="font-semibold text-gray-900 tabular-nums">
                    {{ strtoupper($invoice->currency ?? 'UGX') }} {{ number_format($amountDue, 2) }}
                </span>
            </div>

            @if($event->client?->is_credit_eligible)
                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                    Credit client — mobile money prompts are skipped. Use <strong>Cash</strong> to settle now, or leave on account via the invoice page.
                </p>
            @endif

            <form id="usage-collect-payment-form" class="space-y-3">
                @csrf
                <div>
                    <label for="usage-payment-method" class="block text-xs font-medium text-gray-600">Payment method</label>
                    <select id="usage-payment-method" name="payment_method" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="" disabled selected>Select payment method</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method }}">{{ $methodLabels[$method] ?? ucfirst(str_replace('_', ' ', $method)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="usage-phone-field">
                    <label for="usage-payment-phone" class="block text-xs font-medium text-gray-600">Mobile money phone</label>
                    <input type="text" id="usage-payment-phone" name="phone_number" value="{{ $defaultPhone ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="+256759000000">
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" id="usage-collect-submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        Collect payment
                    </button>
                    @if($invoice)
                        <a href="{{ route('invoices.show', $invoice) }}" class="text-sm text-gray-600 hover:text-gray-900">Open invoice</a>
                    @endif
                </div>

                <p id="usage-collect-message" class="text-sm hidden"></p>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const root = document.getElementById('usage-collect-payment');
            if (!root) return;

            const form = document.getElementById('usage-collect-payment-form');
            const methodSelect = document.getElementById('usage-payment-method');
            const phoneField = document.getElementById('usage-phone-field');
            const phoneInput = document.getElementById('usage-payment-phone');
            const submitBtn = document.getElementById('usage-collect-submit');
            const messageEl = document.getElementById('usage-collect-message');

            function syncMethodUi() {
                const isMobile = methodSelect.value === 'mobile_money';
                phoneField.classList.toggle('hidden', !isMobile);
                submitBtn.textContent = !methodSelect.value
                    ? 'Collect payment'
                    : (isMobile ? 'Send payment prompt' : 'Record cash payment');
            }

            methodSelect.addEventListener('change', syncMethodUi);
            syncMethodUi();

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                submitBtn.disabled = true;
                messageEl.classList.add('hidden');

                try {
                    const response = await fetch(root.dataset.collectUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            payment_method: methodSelect.value,
                            phone_number: phoneInput.value,
                        }),
                    });

                    const data = await response.json();
                    messageEl.textContent = data.message || (data.success ? 'Done.' : 'Payment failed.');
                    messageEl.classList.remove('hidden');
                    messageEl.classList.toggle('text-emerald-700', !!data.success);
                    messageEl.classList.toggle('text-red-700', !data.success);

                    if (data.success && data.status === 'paid') {
                        setTimeout(() => window.location.href = root.dataset.redirectUrl, 1200);
                    }
                } catch (error) {
                    messageEl.textContent = 'Something went wrong. Try again.';
                    messageEl.classList.remove('hidden');
                    messageEl.classList.add('text-red-700');
                } finally {
                    submitBtn.disabled = false;
                }
            });
        })();
    </script>
@elseif($canCollectPayment && $paymentMethods === [])
    <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        No active payment methods are configured for this business. Enable cash or mobile money under maturation / payment settings.
    </div>
@endif
