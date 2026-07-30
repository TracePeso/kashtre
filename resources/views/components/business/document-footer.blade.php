@props([
    'branding',
    'extraLines' => [],
    'showDisclaimer' => true,
    'showKashtreCredit' => true,
])

@php
    /** @var \App\Support\BusinessBranding $branding */
    $extraLines = is_array($extraLines) ? $extraLines : [$extraLines];
@endphp

<div style="margin-top: 28px; padding-top: 12px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 10px; color: #6b7280; line-height: 1.5;">
    <div style="font-weight: bold; color: #374151; font-size: 11px;">{{ $branding->name() }}</div>

    @if($branding->address())
        <div>{{ $branding->address() }}</div>
    @endif

    @foreach(array_filter($extraLines) as $line)
        <div style="margin-top: 4px;">{{ $line }}</div>
    @endforeach

    @if($showDisclaimer)
        <div style="margin-top: 8px; font-style: italic;">This is a system-generated document.</div>
    @endif

    @if($showKashtreCredit)
        @include('partials.kashtre-document-credit')
    @endif
</div>
