@props(['cashTraySettings' => null])

@php
    $cashTray = $cashTraySettings ?? \App\Models\KashtreCashTraySetting::resolved();
@endphp

@if($cashTray->isEnabled())
    <div {{ $attributes->merge(['class' => 'w-full border-t border-gray-200 bg-gray-100 text-gray-600 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400']) }}>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 text-center space-y-2">
            @if($cashTray->hasContactDetails())
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                    @if(filled($cashTray->contact_email))
                        <a href="mailto:{{ $cashTray->contact_email }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                            {{ $cashTray->contact_email }}
                        </a>
                    @endif
                    @if(filled($cashTray->contact_phone))
                        <a href="tel:{{ preg_replace('/\s+/', '', $cashTray->contact_phone) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                            {{ $cashTray->contact_phone }}
                        </a>
                    @endif
                    @if(filled($cashTray->contact_address))
                        <span>{{ $cashTray->contact_address }}</span>
                    @endif
                </div>
            @endif

            @if(filled($cashTray->tagline))
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $cashTray->tagline }}</p>
            @endif

            @if($cashTray->normalizedWebsiteLinks() !== [])
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm">
                    @foreach($cashTray->normalizedWebsiteLinks() as $link)
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                           class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif

            <p class="text-xs text-gray-500 dark:text-gray-500">{{ $cashTray->copyrightLine() }}</p>
        </div>
    </div>
@endif
