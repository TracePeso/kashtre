@php
    $documentTitle = 'Inventory Consumption';
    $documentSubtitle = $meta['period_label'];
    $generatedAt = $generatedAt ?? now();
@endphp
@extends('layouts.pdf')

@section('title', 'Consumption — '.$meta['store_name'])

@push('styles')
<style>
    .meta { margin: 12px 0 16px; line-height: 1.45; }
    .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .summary td { border: 1px solid #e5e7eb; padding: 6px 8px; width: 25%; background: #f9fafb; }
    .summary .label { display: block; font-size: 9px; color: #6b7280; text-transform: uppercase; }
    .summary .value { display: block; margin-top: 2px; font-size: 12px; font-weight: bold; }
    table.data th { font-size: 10px; }
    .code { color: #6b7280; font-size: 9px; }
</style>
@endpush

@section('content')
    <div class="meta">
        <strong>Store:</strong> {{ $meta['store_name'] }}<br>
        <strong>Item:</strong> {{ $meta['item_name'] ?? 'All items' }}<br>
        <strong>Period:</strong> {{ $meta['from'] }} → {{ $meta['until'] }}
        ({{ $meta['period_days'] }} day{{ $meta['period_days'] === 1 ? '' : 's' }})
    </div>

    <table class="summary">
        <tr>
            <td>
                <span class="label">Total consumed</span>
                <span class="value">{{ number_format($meta['total_quantity_suom'], 0) }}</span>
            </td>
            <td>
                <span class="label">Items with usage</span>
                <span class="value">{{ number_format($meta['distinct_items']) }}</span>
            </td>
            <td>
                <span class="label">Activity rows</span>
                <span class="value">{{ number_format($meta['item_day_rows']) }}</span>
            </td>
            <td>
                <span class="label">Generated</span>
                <span class="value" style="font-size: 10px;">{{ now()->format('d M Y H:i') }}</span>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 18%;">Date</th>
                <th>Item</th>
                <th style="width: 14%;" class="text-right">Consumed</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->consumption_date)->format('d M Y') }}</td>
                    <td>
                        {{ $row->item_name ?? '—' }}
                        @if(! empty($row->item_code))
                            <div class="code">{{ $row->item_code }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format((float) $row->total_quantity_suom, 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="muted">No consumption in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
