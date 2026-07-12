<x-mail::message>
# Approval required

@if($order->isInternal())
An **internal order** needs your approval (step {{ $approval->approval_order }}).
@else
An **RFQ** needs your approval (step {{ $approval->approval_order }}).
@endif

**Number:** {{ $order->order_number }}  
**Store:** {{ $order->store?->name }}  
@if($order->isExternal() && $order->supplier)
**Supplier:** {{ $order->supplier->name }}  
@endif
@if($order->isInternal())
**From (supplying):** {{ $order->sourceStore?->name }}  
**To (requesting):** {{ $order->store?->name }}  
@endif
**Submitted by:** {{ $order->createdBy?->name ?? '—' }}

<x-mail::button :url="route('inventory.orders.show', $order)">
Review and approve
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
