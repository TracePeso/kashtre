<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation - {{ $quotation->quotation_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #333;
            margin: 0;
            font-size: 28px;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .quotation-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .quotation-details, .client-details {
            flex: 1;
        }
        .quotation-details {
            margin-right: 20px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
        .value {
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft { background-color: #f3f4f6; color: #374151; }
        .status-sent { background-color: #dbeafe; color: #1e40af; }
        .status-accepted { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-expired { background-color: #fef3c7; color: #92400e; }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: bold;
            color: #495057;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .summary {
            margin-top: 30px;
            border-top: 2px solid #333;
            padding-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .total-row {
            font-size: 18px;
            font-weight: bold;
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .validity {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .validity h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        
        @media print {
            body { background-color: white; }
            .container { box-shadow: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            @include('partials.document-shell-header')
        </div>

        <!-- Quotation and Client Information -->
        <div class="quotation-info">
            <div class="quotation-details">
                <div class="section-title">Quotation Information</div>
                <div class="info-row">
                    <span class="label">Quotation Number:</span>
                    <span class="value">{{ $quotation->quotation_number }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Date Generated:</span>
                    <span class="value">{{ $quotation->generated_at ? $quotation->generated_at->format('M d, Y H:i') : $quotation->created_at->format('M d, Y H:i') }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Status:</span>
                    <span class="value">
                        <span class="status-badge status-{{ $quotation->status }}">
                            {{ ucfirst($quotation->status) }}
                        </span>
                    </span>
                </div>
                @if($quotation->invoice)
                <div class="info-row">
                    <span class="label">Related Invoice:</span>
                    <span class="value">{{ $quotation->invoice->invoice_number }}</span>
                </div>
                @endif
            </div>

            <div class="client-details">
                <div class="section-title">Client Information</div>
                <div class="info-row">
                    <span class="label">Client Name:</span>
                    <span class="value">{{ $quotation->client_name }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Contact Phone:</span>
                    <span class="value">{{ $quotation->client_phone }}</span>
                </div>
                @if($quotation->payment_phone)
                <div class="info-row">
                    <span class="label">Payment Phone:</span>
                    <span class="value">{{ $quotation->payment_phone }}</span>
                </div>
                @endif
                @if($quotation->visit_id)
                <div class="info-row">
                    <span class="label">Visit ID:</span>
                    <span class="value">{{ $quotation->visit_id }}</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Validity Period -->
        @if($quotation->valid_until)
        <div class="validity">
            <h4>⚠️ Quotation Validity</h4>
            <p>This quotation is valid until: <strong>{{ $quotation->valid_until->format('M d, Y H:i') }}</strong></p>
            @if($quotation->days_until_expiry !== null)
                <p>Days remaining: <strong>{{ $quotation->days_until_expiry > 0 ? $quotation->days_until_expiry : 'Expired' }}</strong></p>
            @endif
        </div>
        @endif

        <!-- Items Table -->
        <div class="section-title">Items</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @if($quotation->items && is_array($quotation->items))
                    @foreach($quotation->items as $item)
                    @php
                        // Get the actual Item model to use display_name attribute
                        $itemModel = \App\Models\Item::find($item['id'] ?? $item['item_id'] ?? null);
                        $displayName = $itemModel ? $itemModel->name : ($item['name'] ?? 'N/A');
                    @endphp
                    <tr>
                        <td>{{ $displayName }}</td>
                        <td>{{ $item['type'] ?? 'N/A' }}</td>
                        <td>UGX {{ number_format($item['price'] ?? 0, 2) }}</td>
                        <td>{{ $item['quantity'] ?? 0 }}</td>
                        <td>UGX {{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 0), 2) }}</td>
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="5" style="text-align: center; color: #666;">No items found</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary">
            <div class="summary-row">
                <span class="label">Subtotal:</span>
                <span class="value">UGX {{ number_format($quotation->subtotal, 2) }}</span>
            </div>
            @if($quotation->package_adjustment != 0)
            <div class="summary-row">
                <span class="label">Package Adjustment:</span>
                <span class="value">UGX {{ number_format($quotation->package_adjustment, 2) }}</span>
            </div>
            @endif
            @if($quotation->account_balance_adjustment != 0)
            <div class="summary-row">
                <span class="label">Account Balance Adjustment:</span>
                <span class="value">UGX {{ number_format($quotation->account_balance_adjustment, 2) }}</span>
            </div>
            @endif
            @if($quotation->service_charge > 0)
            <div class="summary-row">
                <span class="label">Service Charge:</span>
                <span class="value">UGX {{ number_format($quotation->service_charge, 2) }}</span>
            </div>
            @endif
            <div class="summary-row total-row">
                <span class="label">Total Amount:</span>
                <span class="value">UGX {{ number_format($quotation->total_amount, 2) }}</span>
            </div>
        </div>

        <!-- Notes -->
        @if($quotation->notes)
        <div style="margin-top: 30px;">
            <div class="section-title">Notes</div>
            <p style="color: #666; line-height: 1.6;">{{ $quotation->notes }}</p>
        </div>
        @endif

        @include('partials.document-shell-footer')
    </div>

    <script>
        // Auto-print when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>

