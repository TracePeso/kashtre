@php
    $branding = $branding ?? \App\Support\BusinessBranding::for($order->business);
    $documentTitle = $order->isDraft() || $order->isPendingApproval()
        ? 'Purchase Request'
        : 'Request for Quotation';
    $documentSubtitle = $order->order_number.' · '.$order->statusLabel();
    $generatedAt = $generatedAt ?? now();
    $showReferencePricing = $order->isDraft() || $order->isPendingApproval();
    $documentTotal = (float) $order->lines->sum('line_total');
@endphp
@extends('layouts.pdf')

@section('title', $order->order_number.' — '.$documentTitle)

@push('styles')
<style>
    .rfq-table { font-size: 10px; margin-top: 14px; }
    .rfq-table th,
    .rfq-table td { padding: 5px 6px; vertical-align: top; }
    .rfq-table th { font-size: 9px; text-transform: uppercase; letter-spacing: 0.02em; }
    .rfq-table .col-item { width: 28%; }
    .rfq-table .col-code { width: 10%; }
    .rfq-table .col-packaging { width: 22%; }
    .rfq-table .col-qty { width: 10%; }
    .rfq-table .col-price { width: 15%; }
    .rfq-table .col-amount { width: 15%; }
    .rfq-total { margin-top: 10px; text-align: right; font-size: 11px; font-weight: bold; }
    .supplier-fill { color: #9ca3af; font-style: italic; }
</style>
@endpush

@section('content')
    <p><strong>Store:</strong> {{ $order->store?->name }}<br>
       <strong>Prepared by:</strong> {{ $order->createdBy?->name ?? '—' }}<br>
       <strong>Date:</strong> {{ $order->created_at?->format('d M Y') }}</p>

    @if($showReferencePricing)
        <p class="muted">Internal purchase request — quantities and reference purchase prices for review before approval.</p>
    @else
        <p class="muted">Please complete <strong>unit price</strong> and <strong>amount</strong> for each line and return your quotation for the quantities below. All amounts in UGX.</p>
    @endif

    @if($order->notes)
        <p><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <table class="rfq-table">
        <thead>
            <tr>
                <th class="col-item">Item</th>
                <th class="col-code">Code</th>
                <th class="col-packaging">Packaging</th>
                <th class="col-qty text-right">Units</th>
                <th class="col-price text-right">Price</th>
                <th class="col-amount text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->lines as $line)
                @php
                    $item = $line->item;
                    $usesPack = $item?->usesPackagingUnits() ?? false;
                    $perPack = $usesPack ? (float) ($item->suom_per_ouom ?? 0) : 0;
                    $qty = $usesPack && $line->order_quantity_ouom !== null
                        ? (float) $line->order_quantity_ouom
                        : (float) $line->order_quantity_suom;
                    $qtyDecimals = $usesPack ? 2 : 0;
                    $unitPriceSuom = (float) ($line->unit_price ?? 0);
                    $unitPriceDisplay = $usesPack && $perPack > 0
                        ? round($unitPriceSuom * $perPack, 2)
                        : $unitPriceSuom;
                    $lineTotal = (float) ($line->line_total ?? 0);
                @endphp
                <tr>
                    <td>{{ $item?->name }}</td>
                    <td>{{ $item?->code }}</td>
                    <td>{{ $item?->packagingDescription() ?? '—' }}</td>
                    <td class="text-right">{{ number_format($qty, $qtyDecimals) }}</td>
                    <td class="text-right">
                        @if($showReferencePricing && $unitPriceDisplay > 0)
                            {{ number_format($unitPriceDisplay, 2) }}
                        @else
                            <span class="supplier-fill">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($showReferencePricing && $lineTotal > 0)
                            {{ number_format($lineTotal, 2) }}
                        @else
                            <span class="supplier-fill">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($showReferencePricing && $documentTotal > 0)
        <p class="rfq-total">Reference total: UGX {{ number_format($documentTotal, 2) }}</p>
    @else
        <p class="rfq-total">Quotation total (UGX): ______________________</p>
    @endif

    @unless($showReferencePricing)
        <p class="note">
            Complete unit price and line amount for every row. Use the packaging column to confirm pack size and order unit.
            Return this quotation with your prices and delivery lead time.
        </p>
    @endunless
@endsection
