<x-mail::message>
# Request for Quotation

Please find attached RFQ **{{ $order->order_number }}** from {{ $order->business?->name ?? config('app.name') }}.

**Store / delivery point:** {{ $order->store?->name }}  
**Date:** {{ $order->approved_at?->format('d M Y') ?? now()->format('d M Y') }}

Quantities are listed in the PDF. **Purchase prices are not shown** — please return your quotation with pricing.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
