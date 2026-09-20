@component('mail::message')
# Payment Received Notification

Dear {{ $business->name ?? 'Business' }} Team,

A payment has been successfully received for one of your invoices.

## Transaction Details

**Invoice Number:** {{ $invoice->invoice_number }}  
**Payment Date:** {{ $invoice->created_at->inOperationalTimezone()->format('F d, Y \a\t g:i A') }}  
**Payment Method:** {{ ucfirst(implode(', ', $invoice->payment_methods ?? ['Cash'])) }}  
**Amount Received:** UGX {{ number_format(max(0, $invoice->subtotal - ($invoice->package_adjustment ?? 0) - ($invoice->account_balance_adjustment ?? 0)), 2) }}

## Client Information
**Client Name:** {{ $client->name ?? 'N/A' }}  
**Client ID:** {{ $client->client_id ?? 'N/A' }}  
**Phone:** {{ $client->phone_number ?? 'N/A' }}  
@if($client->email)
**Email:** {{ $client->email }}  
@endif

## Revenue Summary
**Subtotal 2:** UGX {{ number_format(max(0, $invoice->subtotal - ($invoice->package_adjustment ?? 0) - ($invoice->account_balance_adjustment ?? 0)), 2) }}  
**Amount Received:** UGX {{ number_format(max(0, $invoice->subtotal - ($invoice->package_adjustment ?? 0) - ($invoice->account_balance_adjustment ?? 0)), 2) }}  
**Outstanding Balance:** UGX 0.00

@if($invoice->notes)
## Client Notes
{{ $invoice->notes }}
@endif

---

**Payment Status:** {{ ucfirst($invoice->payment_status) }}  
**Invoice Status:** {{ ucfirst($invoice->status) }}

Best regards,  
Kashtre Team

---

*This is an automated business notification. Please keep this email for your business records.*
@endcomponent
