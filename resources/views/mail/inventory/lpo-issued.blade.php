<x-mail::message>
# Local Purchase Order Issued

A new LPO has been issued for **{{ $purchaseOrder->supplier?->name ?? 'supplier' }}**.

**LPO number:** {{ $purchaseOrder->po_number }}  
**RFQ:** {{ $purchaseOrder->inventoryOrder?->order_number }}  
**Store:** {{ $purchaseOrder->store?->name }}  
**Total:** UGX {{ number_format((float) $purchaseOrder->total_amount, 2) }}

The PDF copy is attached to this email. Finance, approvers, and the supplier (when an email is on file) receive this notice.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
