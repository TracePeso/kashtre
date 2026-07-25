@php
    $branding = $branding ?? \App\Support\BusinessBranding::for($order->business);
    $documentTitle = $order->isDraft() || $order->isPendingApproval()
        ? 'Purchase Request'
        : 'Request for Quotation';
    $documentSubtitle = $order->order_number.' · '.$order->statusLabel();
    $generatedAt = $generatedAt ?? now();
@endphp
@extends('layouts.pdf')

@section('title', $order->order_number.' — '.$documentTitle)

@section('content')
    <p><strong>Store:</strong> {{ $order->store?->name }}<br>
       <strong>Prepared by:</strong> {{ $order->createdBy?->name ?? '—' }}<br>
       <strong>Date:</strong> {{ $order->created_at?->format('d M Y') }}</p>

    @if($order->isDraft() || $order->isPendingApproval())
        <p class="muted">Internal purchase request — quantities for review before approval. Pricing is omitted.</p>
    @else
        <p class="muted">Pricing is intentionally omitted. Please return your quotation for the quantities below.</p>
    @endif

    @if($order->notes)
        <p><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <table style="margin-top: 16px;">
        <thead>
            <tr>
                <th>Item</th>
                <th>Code</th>
                <th>Sale unit</th>
                <th class="text-right">Qty requested</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->lines as $line)
                <tr>
                    <td>{{ $line->item?->name }}</td>
                    <td>{{ $line->item?->code }}</td>
                    <td>{{ $line->item?->itemUnit?->name ?? '—' }}</td>
                    <td class="text-right">{{ number_format((float) $line->order_quantity_suom, 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="note">
        Pricing is intentionally omitted on this RFQ. Please return your quotation with purchase prices and totals for the quantities above.
    </p>
@endsection
