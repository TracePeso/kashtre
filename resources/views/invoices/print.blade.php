<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #333;
            background: white;
            margin: 0;
            padding: 20px;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .invoice-header {
            background: #f8f9fa;
            padding: 30px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .invoice-number {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .company-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .company-details h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .company-details p {
            margin-bottom: 3px;
            color: #6c757d;
        }
        
        .invoice-details {
            text-align: right;
        }
        
        .invoice-details h4 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .invoice-details p {
            margin-bottom: 5px;
        }
        
        .status-badges {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-confirmed { background: #d1ecf1; color: #0c5460; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-partial { background: #f8d7da; color: #721c24; }
        
        .invoice-body {
            padding: 30px;
        }
        
        .billing-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .billing-section h4 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .billing-section p {
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .items-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .items-table .item-name {
            font-weight: 500;
        }
        
        .totals-section {
            margin-left: auto;
            width: 300px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .totals-row:last-child {
            border-bottom: none;
            background: #f8f9fa;
            font-weight: bold;
            font-size: 16px;
        }
        
        .totals-label {
            color: #6c757d;
        }
        
        .totals-amount {
            font-weight: 500;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
        }
        
        .payment-methods {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .payment-methods h5 {
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .payment-method-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #007bff;
            color: white;
            border-radius: 4px;
            font-size: 11px;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        @media print {
            body {
                padding: 0;
                background: white;
            }
            
            .invoice-container {
                border: none;
                box-shadow: none;
                border-radius: 0;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-number">{{ $invoice->invoice_number }}</div>
            
            <div class="company-info">
                @php($branding = \App\Support\BusinessBranding::for($invoice->business))
                @if($branding)
                    <x-business.document-header
                        :branding="$branding"
                        :branch-name="$invoice->branch?->name"
                    />
                @else
                    <div class="company-details">
                        <h3>Business Name</h3>
                    </div>
                @endif
                
                <div class="invoice-details">
                    <h4>Invoice Details</h4>
                    <p><strong>Date:</strong> {{ $invoice->created_at->format('M d, Y') }}</p>
                    <p><strong>Time:</strong> {{ $invoice->created_at->format('H:i') }}</p>
                    @if($invoice->confirmed_at)
                        <p><strong>Confirmed:</strong> {{ $invoice->confirmed_at->format('M d, Y H:i') }}</p>
                    @endif
                    @if($invoice->visit_id)
                        <p><strong>Visit ID:</strong> {{ $invoice->visit_id }}</p>
                    @endif
                    
                    <div class="status-badges">
                        <span class="badge badge-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
                        <span class="badge badge-{{ $invoice->payment_status }}">{{ ucfirst($invoice->payment_status) }}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Invoice Body -->
        <div class="invoice-body">
            <!-- Billing Information -->
            <div class="billing-info">
                <div class="billing-section">
                    <h4>Bill To:</h4>
                    <p><strong>{{ $invoice->client_name }}</strong></p>
                    <p>{{ $invoice->client_phone }}</p>
                    @if($invoice->payment_phone && $invoice->payment_phone !== $invoice->client_phone)
                        <p><strong>Payment Phone:</strong> {{ $invoice->payment_phone }}</p>
                    @endif
                </div>
                
                <div class="billing-section">
                    <h4>Created By:</h4>
                    <p>{{ $invoice->createdBy->name ?? 'N/A' }}</p>
                    <p>{{ $invoice->createdBy->email ?? '' }}</p>
                </div>
            </div>
            
            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @if($invoice->items && is_array($invoice->items))
                        @foreach($invoice->items as $item)
                        @php
                            // Get the actual Item model to use display_name attribute
                            $itemModel = \App\Models\Item::find($item['id'] ?? $item['item_id'] ?? null);
                            $displayName = $itemModel ? $itemModel->name : ($item['name'] ?? 'N/A');
                        @endphp
                        <tr>
                            <td class="item-name">{{ $displayName }}</td>
                            <td class="text-right">UGX {{ number_format($item['price'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ $item['quantity'] ?? 0 }}</td>
                            <td class="text-right">UGX {{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 0), 2) }}</td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" style="text-align: center; color: #6c757d;">No items found</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            
            <!-- Totals Section -->
            <div class="totals-section">
                <div class="totals-row">
                    <span class="totals-label">Subtotal 1:</span>
                    <span class="totals-amount">UGX {{ number_format($invoice->subtotal, 2) }}</span>
                </div>
                
                @if($invoice->package_adjustment != 0)
                <div class="totals-row">
                    <span class="totals-label">Package Adjustment:</span>
                    <span class="totals-amount">UGX {{ number_format($invoice->package_adjustment, 2) }}</span>
                </div>
                @endif
                
                @if($invoice->account_balance_adjustment != 0)
                <div class="totals-row">
                    <span class="totals-label">Account Balance(A/c) Adjustment:</span>
                    <span class="totals-amount">UGX {{ number_format($invoice->account_balance_adjustment, 2) }}</span>
                </div>
                @endif
                
                <div class="totals-row">
                    <span class="totals-label">Subtotal 2:</span>
                    <span class="totals-amount">UGX {{ number_format(max(0, $invoice->subtotal - ($invoice->package_adjustment ?? 0) - ($invoice->account_balance_adjustment ?? 0)), 2) }}</span>
                </div>
                
                @if($invoice->service_charge > 0)
                <div class="totals-row">
                    <span class="totals-label">Service Charge:</span>
                    <span class="totals-amount">UGX {{ number_format($invoice->service_charge, 2) }}</span>
                </div>
                @endif
                
                <div class="totals-row" style="border-bottom: none; background: #f8f9fa; font-weight: bold; font-size: 16px;">
                    <span class="totals-label">Total:</span>
                    <span class="totals-amount">UGX {{ number_format($invoice->total_amount, 2) }}</span>
                </div>
                
                <div class="totals-row">
                    <span class="totals-label">Amount Paid:</span>
                    <span class="totals-amount">UGX {{ number_format($invoice->amount_paid, 2) }}</span>
                </div>
                
                <div class="totals-row">
                    <span class="totals-label">Balance Due:</span>
                    <span class="totals-amount" style="color: {{ $invoice->balance_due > 0 ? '#dc3545' : '#28a745' }}">
                        UGX {{ number_format($invoice->balance_due, 2) }}
                    </span>
                </div>
            </div>
            
            <!-- Payment Methods -->
            @if($invoice->payment_methods && is_array($invoice->payment_methods))
            <div class="payment-methods">
                <h5>Payment Methods:</h5>
                @php
                    // Check if payment_methods has amounts
                    $hasAmounts = false;
                    foreach($invoice->payment_methods as $method) {
                        if (is_array($method) && isset($method['method']) && isset($method['amount'])) {
                            $hasAmounts = true;
                            break;
                        }
                    }
                @endphp
                
                @if($hasAmounts)
                    @foreach($invoice->payment_methods as $methodData)
                        @if(is_array($methodData) && isset($methodData['method']) && isset($methodData['amount']))
                            <div style="margin-bottom: 8px;">
                                <span class="payment-method-badge">{{ ucwords(str_replace('_', ' ', $methodData['method'])) }}</span>
                                <span style="margin-left: 10px; color: #6c757d;">UGX {{ number_format($methodData['amount'], 2) }}</span>
                            </div>
                        @endif
                    @endforeach
                @else
                    {{-- Fallback for old format --}}
                    @foreach($invoice->payment_methods as $method)
                        <span class="payment-method-badge">{{ ucwords(str_replace('_', ' ', $method)) }}</span>
                    @endforeach
                    @if($invoice->amount_paid > 0)
                        <div style="margin-top: 8px; color: #6c757d;">
                            Total Paid: UGX {{ number_format($invoice->amount_paid, 2) }}
                        </div>
                    @endif
                @endif
            </div>
            @endif
            
            <!-- Notes -->
            @if($invoice->notes)
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h5 style="margin-bottom: 10px; color: #2c3e50;">Notes:</h5>
                <p style="color: #6c757d;">{{ $invoice->notes }}</p>
            </div>
            @endif
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Generated on {{ now()->format('M d, Y H:i') }}</p>
        </div>
    </div>
    
    <!-- Print Controls (hidden when printing) -->
    <div class="no-print" style="text-align: center; margin: 20px 0;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;">
            Print Invoice
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Close
        </button>
    </div>
    
    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>
