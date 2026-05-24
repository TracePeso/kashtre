<div x-show="callState === 'incoming'"
     style="display: none;"
     class="fixed top-6 right-6 z-[100] w-80 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-indigo-500 dark:border-indigo-500 overflow-hidden flex flex-col"
     x-transition:enter="transition ease-out duration-300 transform"
     x-transition:enter-start="opacity-0 translate-x-12 scale-90"
     x-transition:enter-end="opacity-100 translate-x-0 scale-100"
     x-transition:leave="transition ease-in duration-200 transform"
     x-transition:leave-start="opacity-100 translate-x-0 scale-100"
     x-transition:leave-end="opacity-0 translate-x-12 scale-95">

    <div class="h-1.5 w-full bg-indigo-100 dark:bg-indigo-900 relative overflow-hidden">
        <div class="absolute inset-y-0 left-0 bg-indigo-500 w-1/3 animate-[translateX_2s_ease-in-out_infinite] rounded-full"></div>
    </div>

    <div class="p-5 flex flex-col items-center text-center">
        <!-- Avatar with pulsing ring -->
        <div class="relative w-16 h-16 mb-4">
            <div class="absolute inset-0 rounded-full bg-indigo-500 opacity-20 animate-ping"></div>
            <img :src="remoteUser?.photo" class="w-16 h-16 rounded-full border-2 border-white dark:border-slate-800 relative z-10 object-cover shadow-sm" alt="CallerAvatar">
        </div>

        <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100" x-text="remoteUser?.name"></h3>
        <p class="text-sm text-indigo-500 dark:text-indigo-400 font-medium mt-1 mb-5 animate-pulse">Incoming Audio Call...</p>

        <div class="flex justify-center gap-4 w-full px-2">
            <button @click="rejectCall()" class="flex-1 flex items-center justify-center gap-2 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 transition-colors font-medium text-sm">
                <svg class="w-4 h-4 transform rotate-135" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                Decline
            </button>
            <button @click="acceptCall()" class="flex-1 flex items-center justify-center gap-2 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white transition-colors shadow-lg shadow-emerald-500/30 font-medium text-sm animate-bounce">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                Accept
            </button>
        </div>
    </div>
</div>
