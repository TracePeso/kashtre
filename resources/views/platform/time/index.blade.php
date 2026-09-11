<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mt-1">
            <h1 class="text-xl font-semibold text-gray-900 tracking-tight">Time</h1>
            <p class="mt-1 text-sm text-gray-500">
                Shared clock, IANA catalogue, timezone policies, business dates, schedules, calendars, periods, and device time.
            </p>
            @unless($engineEnabled)
                <p class="mt-2 text-sm text-amber-700">Set <code class="font-mono">SHARED_TIME_ENABLED=true</code> in <code class="font-mono">.env</code> to mark the engine active for consumers.</p>
            @endunless
        </div>

        <div class="mt-4">
            @livewire('platform.time-engine-console')
        </div>
    </div>
</div>
</x-app-layout>
