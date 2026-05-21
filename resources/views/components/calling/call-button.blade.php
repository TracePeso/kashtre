@props(['user'])

<!--
    The initiating button is rendered outside Alpine context or inside a list.
    We dispatch an event to the global calling component since Alpine components
    can't easily call methods on outer components dynamically.
-->
@if(auth()->check() && auth()->id() !== $user->id && auth()->user()->business_id === $user->business_id)
<button
    title="Audio Call"
    @click="$dispatch('initiate-call', { uuid: '{{ $user->uuid }}' })"
    class="p-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 dark:text-indigo-400 rounded-full shadow-sm transition-colors border border-indigo-100 dark:border-indigo-500/20 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
    </svg>
</button>
@endif
