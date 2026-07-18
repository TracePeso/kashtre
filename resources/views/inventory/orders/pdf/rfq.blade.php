<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @php
        $documentTitle = $order->isDraft() || $order->isPendingApproval()
            ? 'Purchase Request'
            : 'Request for Quotation';
    @endphp
    <title>{{ $order->order_number }} — {{ $documentTitle }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .text-right { text-align: right; }
        .note { margin-top: 14px; padding: 10px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 11px; }
    </style>
</head>
<body>
    @php($branding = \App\Support\BusinessBranding::for($order->business))
    @if($branding)
        <x-business.document-header
            :branding="$branding"
            :document-title="$documentTitle"
            :document-subtitle="$order->order_number.' · '.$order->statusLabel()"
        />
    @else
        <h1>{{ $documentTitle }}</h1>
        <p class="muted">{{ $order->order_number }} · {{ $order->statusLabel() }}</p>
    @endif
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

    <table>
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
</body>
</html>
