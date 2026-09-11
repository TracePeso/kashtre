<x-mail::message>
# {{ $order->isInternal() ? 'Internal order' : 'RFQ' }} approved

**{{ $order->order_number }}** was fully approved by {{ $approver->name }}.

**Store:** {{ $order->store?->name }}  
@if($order->isExternal() && $order->supplier)
**Supplier:** {{ $order->supplier->name }}  
@endif

@if($order->isExternal())
Next step: record the supplier quotation, then generate and issue the LPO.
@else
Next step: create the stock transfer from the supplying store.
@endif

<x-mail::button :url="route('inventory.orders.show', $order)">
Open order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
