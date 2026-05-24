<div x-show="callState === 'calling'"
     style="display: none;"
     class="fixed inset-[auto_auto_32px_32px] z-[90] bg-white dark:bg-slate-800 rounded-2xl shadow-[0_10px_40px_-10px_rgba(0,0,0,0.3)] border border-gray-200 dark:border-slate-700 w-72 overflow-hidden flex flex-col"
     x-transition:enter="transition ease-out duration-300 transform"
     x-transition:enter-start="opacity-0 translate-y-8"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200 transform"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-8">

    <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-700 relative overflow-hidden">
        <div class="absolute inset-y-0 left-0 bg-indigo-500 w-1/3 animate-[translateX_2s_ease-in-out_infinite] rounded-full"></div>
    </div>

    <div class="p-5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img :src="remoteUser?.photo" class="w-12 h-12 rounded-full object-cover shadow-sm" alt="Caller Avatar">
            <div>
                <h4 class="font-semibold text-slate-800 dark:text-slate-100 truncate w-32" x-text="remoteUser?.name"></h4>
                <p class="text-xs text-indigo-500 font-medium">Calling...</p>
            </div>
        </div>

        <button @click="cancelCall()" class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 hover:bg-rose-500 hover:text-white transition-colors flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 transform rotate-135" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
        </button>
    </div>
</div>
