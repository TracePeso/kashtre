@props([
    'order',
    'variant' => 'secondary',
])

@if($order->canDownloadRfqPdf())
    @php
        $classes = match ($variant) {
            'primary' => 'inline-flex items-center px-4 py-2 border border-blue-300 rounded-md shadow-sm text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100',
            'compact' => 'inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium text-slate-700 bg-white border border-gray-300 hover:bg-gray-50',
            default => 'inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50',
        };
    @endphp
    <a href="{{ $order->rfqPdfDownloadUrl() }}"
       {{ $attributes->merge(['class' => $classes]) }}>
        {{ $order->rfqPdfDownloadLabel() }}
    </a>
@endif
