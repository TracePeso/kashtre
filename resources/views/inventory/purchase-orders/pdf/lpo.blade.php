@php
    $branding = $branding ?? \App\Support\BusinessBranding::for($po->business);
    $documentTitle = 'Local Purchase Order';
    $documentSubtitle = $po->po_number.' · '.$po->statusLabel();
    $generatedAt = $generatedAt ?? now();
@endphp
@extends('layouts.pdf')

@section('title', $po->po_number.' — '.$documentTitle)

@push('styles')
<style>
    .totals { margin-top: 12px; text-align: right; font-weight: bold; }
</style>
@endpush

@section('content')
    <p><strong>Supplier:</strong> {{ $po->supplier?->name }}<br>
       <strong>Store:</strong> {{ $po->store?->name }}<br>
       <strong>RFQ:</strong> {{ $po->inventoryOrder?->order_number }}<br>
       @if($po->issued_at)
           <strong>Issued:</strong> {{ $po->issued_at->format('d M Y H:i') }} by {{ $po->issuedBy?->name ?? '—' }}<br>
       @endif
    </p>

    <table style="margin-top: 16px;">
        <thead>
            <tr>
                <th>Item</th>
                <th>Code</th>
                <th class="text-right">Quantity (sale units)</th>
                <th class="text-right">Purchase price</th>
                <th class="text-right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->lines as $line)
                <tr>
                    <td>{{ $line->item?->name }}</td>
                    <td>{{ $line->item?->code }}</td>
                    <td class="text-right">{{ number_format((float) $line->quantity_suom, 0) }}</td>
                    <td class="text-right">{{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="totals">LPO total: UGX {{ number_format((float) $po->total_amount, 2) }}</p>
@endsection
