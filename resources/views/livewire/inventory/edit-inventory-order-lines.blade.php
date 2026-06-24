<div class="w-full min-w-0">
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
        <p class="text-sm font-semibold text-gray-900">
            Order total: UGX {{ number_format($order->orderTotal(), 2) }}
        </p>
    </div>
</div>
