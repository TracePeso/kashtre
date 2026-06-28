<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->order_number }} — Request for Quotation</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .text-right { text-align: right; }
        .totals { margin-top: 12px; text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Request for Quotation</h1>
    <p class="muted">{{ $order->order_number }} · {{ $order->statusLabel() }}</p>
    <p><strong>Store:</strong> {{ $order->store?->name }}<br>
       <strong>Business:</strong> {{ $order->business?->name }}<br>
       <strong>Prepared by:</strong> {{ $order->createdBy?->name ?? '—' }}<br>
       <strong>Date:</strong> {{ $order->created_at?->format('d M Y') }}</p>

    @if($order->notes)
        <p><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Code</th>
                <th class="text-right">Qty (SUOM)</th>
                <th class="text-right">Unit price</th>
                <th class="text-right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->lines as $line)
                <tr>
                    <td>{{ $line->item?->name }}</td>
                    <td>{{ $line->item?->code }}</td>
                    <td class="text-right">{{ number_format((float) $line->order_quantity_suom, 0) }}</td>
                    <td class="text-right">{{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="totals">Estimated total: UGX {{ number_format($order->orderTotal(), 2) }}</p>
</body>
</html>
