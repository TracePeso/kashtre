@props([
    'branding',
    'documentTitle' => null,
    'documentSubtitle' => null,
    'branchName' => null,
    'layout' => 'inline',
    'generatedAt' => null,
])

@php
    /** @var \App\Support\BusinessBranding $branding */
    $logoDataUri = $branding->logoDataUri();
    $generatedAt = $generatedAt ?? now();
    $address = $branding->address();
    $phone = $branding->phone();
    $email = $branding->email();
    $timestampLabel = 'Generated: '.$generatedAt->format('d M Y H:i');
@endphp

@if($layout === 'centered')
    <div style="text-align: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="{{ $branding->name() }}" style="max-height: 72px; max-width: 220px; margin: 0 auto 12px; display: block;">
        @endif
        <div style="font-size: 18px; font-weight: bold; color: #2c3e50;">{{ $branding->name() }}</div>
        @if($address)
            <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">{{ $address }}</div>
        @endif
        @if($branchName)
            <div style="font-size: 12px; color: #495057; margin-top: 2px;"><strong>Branch:</strong> {{ $branchName }}</div>
        @endif
        @if($phone)
            <div style="font-size: 12px; color: #6c757d; margin-top: 2px;">Tel: {{ $phone }}</div>
        @endif
        @if($email)
            <div style="font-size: 12px; color: #6c757d; margin-top: 2px;">Email: {{ $email }}</div>
        @endif
        <div style="font-size: 11px; color: #6b7280; margin-top: 8px;">{{ $timestampLabel }}</div>
        @if($documentTitle)
            <div style="font-size: 24px; font-weight: bold; margin-top: 16px; color: #333;">{{ $documentTitle }}</div>
        @endif
        @if($documentSubtitle)
            <div style="font-size: 12px; color: #666; margin-top: 4px;">{{ $documentSubtitle }}</div>
        @endif
    </div>
@else
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
        <div style="display: flex; gap: 16px; align-items: flex-start;">
            @if($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="{{ $branding->name() }}" style="max-height: 64px; max-width: 120px; object-fit: contain;">
            @endif
            <div>
                <div style="font-size: 18px; font-weight: bold; color: #2c3e50; margin-bottom: 4px;">{{ $branding->name() }}</div>
                @if($address)
                    <div style="font-size: 13px; color: #6c757d; margin-bottom: 2px;">{{ $address }}</div>
                @endif
                @if($branchName)
                    <div style="font-size: 13px; color: #495057; margin-bottom: 4px;"><strong>Branch:</strong> {{ $branchName }}</div>
                @endif
                @if($phone)
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 2px;">Tel: {{ $phone }}</div>
                @endif
                @if($email)
                    <div style="font-size: 12px; color: #6c757d; margin-bottom: 2px;">Email: {{ $email }}</div>
                @endif
            </div>
        </div>

        <div style="text-align: right; min-width: 160px;">
            @if($documentTitle)
                <div style="font-size: 16px; font-weight: bold; color: #2c3e50;">{{ $documentTitle }}</div>
            @endif
            @if($documentSubtitle)
                <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">{{ $documentSubtitle }}</div>
            @endif
            <div style="font-size: 11px; color: #6b7280; margin-top: {{ ($documentTitle || $documentSubtitle) ? '8px' : '0' }};">{{ $timestampLabel }}</div>
        </div>
    </div>
@endif
