<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $po->po_number }} — Local Purchase Order</title>
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
    <h1>Local Purchase Order</h1>
    <p class="muted">{{ $po->po_number }} · {{ $po->statusLabel() }}</p>
    <p><strong>Supplier:</strong> {{ $po->supplier?->name }}<br>
       <strong>Store:</strong> {{ $po->store?->name }}<br>
       <strong>RFQ:</strong> {{ $po->inventoryOrder?->order_number }}<br>
       <strong>Business:</strong> {{ $po->business?->name }}<br>
       @if($po->issued_at)
           <strong>Issued:</strong> {{ $po->issued_at->format('d M Y H:i') }} by {{ $po->issuedBy?->name ?? '—' }}<br>
       @endif
    </p>

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
</body>
</html>
