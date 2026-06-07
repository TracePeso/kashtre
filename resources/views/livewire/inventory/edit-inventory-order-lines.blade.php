<div>
    {{ $this->table }}

    <div class="mt-4 flex justify-end border-t border-gray-200 pt-3">
        <p class="text-sm font-semibold text-gray-900">
            Order total: UGX {{ number_format($order->orderTotal(), 2) }}
        </p>
    </div>
</div>
