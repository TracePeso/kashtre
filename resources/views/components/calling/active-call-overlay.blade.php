<div x-show="callState === 'connected'"
     style="display: none;"
     class="fixed bottom-6 right-6 z-[100] w-80 bg-white dark:bg-slate-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-slate-700 overflow-hidden flex flex-col"
     x-transition:enter="transition ease-out duration-300 transform"
     x-transition:enter-start="opacity-0 translate-y-12 scale-90"
     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
     x-transition:leave="transition ease-in duration-200 transform"
     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
     x-transition:leave-end="opacity-0 -translate-y-4 scale-95">

    <!-- Header area -->
    <div class="px-6 py-5 flex items-center gap-4 bg-slate-50 dark:bg-slate-800/80 border-b border-gray-100 dark:border-slate-700">
        <div class="relative">
            <img :src="remoteUser?.photo" class="w-14 h-14 rounded-full object-cover border-2 border-white dark:border-slate-700 shadow-sm" alt="Caller Avatar">
            <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 border-2 border-white dark:border-slate-800 rounded-full"></span>
        </div>
        <div>
            <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate w-40" x-text="remoteUser?.name"></h3>
            <div class="flex items-center gap-1.5 mt-0.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-sm font-medium tracking-tabular-nums" :class="duration > 0 ? 'text-emerald-500' : 'text-slate-500'" x-text="durationFormatted">00:00</span>
            </div>
            <p x-show="mediaConnectionState !== 'connected' && mediaConnectionMessage"
               x-text="mediaConnectionMessage"
               :class="mediaConnectionState === 'failed' ? 'text-rose-500' : 'text-amber-500'"
               class="mt-1 text-xs font-medium"></p>
        </div>
    </div>

    <!-- Controls -->
    <div class="px-6 py-5 flex items-center justify-center gap-6 bg-white dark:bg-slate-800">

        <!-- Mute Button -->
        <button @click="toggleMute()"
                type="button"
                :class="isMuted ? 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'"
                class="w-12 h-12 rounded-full flex items-center justify-center transition-colors">

            <!-- Mic Icon -->
            <svg x-show="!isMuted" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
            <!-- Muted Mic Icon -->
            <svg x-show="isMuted" style="display: none;" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clip-rule="evenodd" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" /></svg>
        </button>

        <!-- End Call Button -->
        <button @click="endCall()"
                type="button"
                class="w-14 h-14 rounded-full bg-rose-500 hover:bg-rose-600 text-white flex items-center justify-center shadow-lg shadow-rose-500/30 transition-transform active:scale-95 group">
            <svg class="w-6 h-6 transform rotate-135 group-hover:rotate-180 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
        </button>

    </div>
</div>

<!-- WebRTC Audio Elements — kept outside the overlay so they are never in a display:none
     subtree. Browsers need the <audio> in a visible DOM context for Acoustic Echo
     Cancellation (AEC) to work correctly against the microphone input. -->
<audio id="remoteAudio" autoplay playsinline preload="auto" aria-hidden="true" class="fixed w-0 h-0 opacity-0 pointer-events-none"></audio>
<audio id="compatibilityRemoteAudio" autoplay playsinline preload="auto" aria-hidden="true" class="fixed w-0 h-0 opacity-0 pointer-events-none"></audio>
