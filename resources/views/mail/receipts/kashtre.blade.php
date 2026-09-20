@component('mail::message')
# Complete Transaction Record - KashTre Platform

Dear KashTre Team,

A transaction has been completed on the platform. Below are the complete transaction details for your records.

## Transaction Overview

**Invoice Number:** {{ $invoice->invoice_number }}  
**Transaction Date:** {{ $invoice->created_at->inOperationalTimezone()->format('F d, Y \a\t g:i A') }}  
**Commission Earned:** UGX {{ number_format($chargeAmount, 2) }}  
**Status:** {{ ucfirst($invoice->status) }}

## Client Information
**Client Name:** {{ $client->name ?? 'N/A' }}  
**Client ID:** {{ $client->client_id ?? 'N/A' }}  
**Phone:** {{ $client->phone_number ?? 'N/A' }}  
@if($client->email)
**Email:** {{ $client->email }}  
@endif

## Business Information
**Business Name:** {{ $business->name ?? ($invoice->business->name ?? 'N/A') }}  
**Business ID:** {{ $business->id ?? ($invoice->business->id ?? $invoice->business_id ?? 'N/A') }}  
**Business Email:** {{ $business->email ?? ($invoice->business->email ?? 'N/A') }}

@if(config('app.debug'))
**Debug Info:** Business Object: {{ $business ? 'Loaded' : 'Null' }}, Invoice Business: {{ $invoice->business ? 'Loaded' : 'Null' }}, Business ID: {{ $invoice->business_id }}
@endif

## Complete Item Details

@component('mail::table')
| Item | Quantity | Price Each | Total |
|------|----------|------------|-------|
@foreach($invoice->items as $item)
| {{ $item['name'] ?? 'N/A' }} | {{ $item['quantity'] ?? 1 }} | UGX {{ number_format($item['price'] ?? 0, 2) }} | UGX {{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2) }} |
@endforeach
@endcomponent

## Complete Financial Breakdown
**Subtotal 1:** UGX {{ number_format($invoice->subtotal, 2) }}  
@if($invoice->package_adjustment > 0)
**Package Adjustment:** -UGX {{ number_format($invoice->package_adjustment, 2) }}  
@endif
@if($invoice->account_balance_adjustment > 0)
**Account Balance Adjustment:** -UGX {{ number_format($invoice->account_balance_adjustment, 2) }}  
@endif
**Subtotal 2:** UGX {{ number_format(max(0, $invoice->subtotal - ($invoice->package_adjustment ?? 0) - ($invoice->account_balance_adjustment ?? 0)), 2) }}  
**Service Charge (Kashtre Commission):** UGX {{ number_format($invoice->service_charge, 2) }}  
**Total Amount:** UGX {{ number_format($invoice->total_amount, 2) }}  
**Amount Paid:** UGX {{ number_format($invoice->amount_paid, 2) }}  
**Balance Due:** UGX {{ number_format($invoice->balance_due, 2) }}

@if($invoice->notes)
## Client Notes
{{ $invoice->notes }}
@endif

---

**Payment Status:** {{ ucfirst($invoice->payment_status) }}  
**Invoice Status:** {{ ucfirst($invoice->status) }}

A complete detailed transaction record PDF is attached to this email for your platform records.

Best regards,  
Kashtre System

---

*This is an automated platform notification for KashTre internal records with complete transaction details.*
@endcomponent
