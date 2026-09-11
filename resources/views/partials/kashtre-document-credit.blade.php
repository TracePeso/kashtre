@php
    $kashtreSettings = $kashtreSettings ?? \App\Models\KashtreCashTraySetting::resolved();
    $website = $kashtreSettings->primaryWebsiteLink();
    $style = $style ?? 'inline';
@endphp

@if($style === 'preview')
    <div class="mt-2.5 text-[8px] text-gray-400 tracking-wide">
        {{ $kashtreSettings->documentPoweredByLine() }}
    </div>
    @if($website)
        <div class="mt-1 text-[8px]">
            <a href="{{ $website['url'] }}" target="_blank" rel="noopener noreferrer"
               class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                {{ $website['label'] }}
            </a>
        </div>
    @endif
@else
    <div style="margin-top: 10px; font-size: 8px; color: #9ca3af; letter-spacing: 0.02em;">
        {{ $kashtreSettings->documentPoweredByLine() }}
    </div>
    @if($website)
        <div style="margin-top: 4px; font-size: 8px;">
            <a href="{{ $website['url'] }}" style="color: #2563eb; text-decoration: none;">{{ $website['label'] }}</a>
        </div>
    @endif
@endif
