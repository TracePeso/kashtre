<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Call Settings</h2>
            <p class="mt-1 text-sm text-gray-500">
                Voice and announcement settings for your organisation's caller display.
            </p>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('service-point-callers.save-global-call-settings') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Hidden voice fields -->
            <input type="hidden" name="tts_voice_id"   id="tts_voice_id"   value="{{ $config?->tts_voice_id }}">
            <input type="hidden" name="tts_voice_name" id="tts_voice_name" value="{{ $config?->tts_voice_name }}">

            <!-- ── AUDIO ──────────────────────────────────────────────────── -->
            <div class="flex items-center gap-3 pt-2">
                <svg class="w-5 h-5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                </svg>
                <h3 class="text-base font-bold text-gray-700 uppercase tracking-wide">Audio</h3>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <!-- Voice Picker -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Voice</h3>
                        <p class="mt-0.5 text-sm text-gray-500">Choose the voice used for announcements.</p>
                    </div>
                    <button type="button" onclick="loadVoices()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">
                        <svg id="refresh-icon" class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Refresh
                    </button>
                </div>

                @if($config?->tts_voice_id)
                    <div class="px-6 py-3 bg-indigo-50 border-b border-indigo-100 text-sm text-indigo-700 flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Current voice: <strong>{{ $config->tts_voice_name ?? $config->tts_voice_id }}</strong>
                    </div>
                @endif

                <!-- Voice list -->
                <div id="voice-list" class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                    <div class="px-6 py-8 text-center text-sm text-gray-400">
                        Click <strong>Refresh</strong> to load available voices.
                    </div>
                </div>
            </div>

            <!-- Voice Quality Settings -->
            <div class="bg-white shadow sm:rounded-lg px-6 py-6 space-y-5">
                <h3 class="text-base font-semibold text-gray-900">Voice Quality</h3>

                <!-- Stability -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Stability &mdash; <span id="stability-label">{{ number_format($config?->tts_stability ?? 0.5, 2) }}</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-0.5 mb-2">
                        Higher = more consistent, lower = more expressive.
                    </p>
                    <input type="range" name="tts_stability" id="tts_stability"
                           min="0" max="1" step="0.05"
                           value="{{ $config?->tts_stability ?? 0.5 }}"
                           oninput="document.getElementById('stability-label').textContent = parseFloat(this.value).toFixed(2)"
                           class="w-full accent-indigo-600">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>More variable</span><span>More stable</span>
                    </div>
                </div>

                <!-- Similarity Boost -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Clarity &amp; Similarity &mdash; <span id="similarity-label">{{ number_format($config?->tts_similarity_boost ?? 0.75, 2) }}</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-0.5 mb-2">
                        How closely the output resembles the original voice.
                    </p>
                    <input type="range" name="tts_similarity_boost" id="tts_similarity_boost"
                           min="0" max="1" step="0.05"
                           value="{{ $config?->tts_similarity_boost ?? 0.75 }}"
                           oninput="document.getElementById('similarity-label').textContent = parseFloat(this.value).toFixed(2)"
                           class="w-full accent-indigo-600">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>Low</span><span>High</span>
                    </div>
                </div>

                <!-- Speed -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Speed &mdash; <span id="speed-label">{{ number_format($config?->tts_speed ?? 1.0, 2) }}&times;</span>
                    </label>
                    <input type="range" name="tts_speed" id="tts_speed"
                           min="0.7" max="1.2" step="0.05"
                           value="{{ $config?->tts_speed ?? 1.0 }}"
                           oninput="document.getElementById('speed-label').textContent = parseFloat(this.value).toFixed(2) + '×'"
                           class="w-full accent-indigo-600">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>0.7× (slow)</span><span>1.0× (normal)</span><span>1.2× (fast)</span>
                    </div>
                </div>
            </div>

            <!-- Announcement Template -->
            <div class="bg-white shadow sm:rounded-lg px-6 py-6">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Announcement Template</h3>
                <p class="text-sm text-gray-500 mb-3">
                    Use <code class="bg-gray-100 px-1 rounded text-xs">{name}</code> for the client ID and
                    <code class="bg-gray-100 px-1 rounded text-xs">{destination}</code> for the room.
                    Leave blank to use the default.
                </p>
                <textarea
                    id="announcement_message"
                    name="announcement_message"
                    rows="3"
                    placeholder="Now serving {name}. Please proceed to {destination}."
                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                >{{ $config?->announcement_message }}</textarea>
            </div>

            <!-- ── VIDEO ──────────────────────────────────────────────────── -->

            <!-- Actions -->
            <div class="bg-white shadow sm:rounded-lg px-6 py-4 flex items-center justify-between">
                <button type="button" onclick="previewVoice()"
                        id="preview-btn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Preview Voice
                </button>

                @if(in_array('Edit Callers', auth()->user()->permissions ?? []) || in_array('Manage Callers', auth()->user()->permissions ?? []))
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Save Settings
                </button>
                @endif
            </div>

        </form>

        <!-- Hidden audio element for previews -->
        <audio id="preview-audio" class="hidden"></audio>

    </div>
</div>

<script>
const VOICES_URL  = "{{ route('service-point-callers.get-voices') }}";
const PREVIEW_URL = "{{ route('service-point-callers.preview-voice') }}";

let selectedVoiceId   = document.getElementById('tts_voice_id').value;
let selectedVoiceName = document.getElementById('tts_voice_name').value;

async function loadVoices() {
    const list       = document.getElementById('voice-list');
    const refreshBtn = document.querySelector('button[onclick="loadVoices()"]');

    list.innerHTML = '<div class="px-6 py-8 text-center text-sm text-gray-400">Loading voices…</div>';
    refreshBtn.disabled = true;

    try {
        const res  = await fetch(VOICES_URL);
        const data = await res.json();

        if (!res.ok || data.error) {
            list.innerHTML = `<div class="px-6 py-8 text-center text-sm text-red-500">${data.error ?? 'Failed to load voices.'}</div>`;
            return;
        }

        if (!data.length) {
            list.innerHTML = '<div class="px-6 py-8 text-center text-sm text-gray-400">No voices found.</div>';
            return;
        }

        list.innerHTML = data.map(v => {
            const isSelected = v.voice_id === selectedVoiceId;
            return `
            <label class="flex items-center gap-3 px-6 py-3 cursor-pointer hover:bg-gray-50 ${isSelected ? 'bg-indigo-50' : ''}" id="voice-row-${v.voice_id}">
                <input type="radio" name="_voice_radio" value="${v.voice_id}"
                       onchange="selectVoice('${v.voice_id}', '${escHtml(v.name)}')"
                       ${isSelected ? 'checked' : ''}
                       class="accent-indigo-600">
                <div class="flex-1 min-w-0">
                    <span class="text-sm font-medium text-gray-900">${escHtml(v.name)}</span>
                    ${v.category ? `<span class="ml-2 text-xs text-gray-400">${escHtml(v.category)}</span>` : ''}
                </div>
                ${v.preview_url ? `
                <button type="button" onclick="playSample(event, '${escHtml(v.preview_url)}')"
                        title="Play sample"
                        class="shrink-0 p-1.5 rounded-full text-gray-400 hover:text-indigo-600 hover:bg-indigo-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>` : ''}
            </label>`;
        }).join('');

    } catch (err) {
        list.innerHTML = '<div class="px-6 py-8 text-center text-sm text-red-500">Could not reach the voice service.</div>';
    } finally {
        refreshBtn.disabled = false;
    }
}

function selectVoice(voiceId, voiceName) {
    selectedVoiceId   = voiceId;
    selectedVoiceName = voiceName;
    document.getElementById('tts_voice_id').value   = voiceId;
    document.getElementById('tts_voice_name').value = voiceName;

    // Highlight selected row
    document.querySelectorAll('[id^="voice-row-"]').forEach(row => {
        row.classList.toggle('bg-indigo-50', row.id === 'voice-row-' + voiceId);
        row.classList.remove('bg-white');
    });
}

function playSample(event, url) {
    event.preventDefault();
    event.stopPropagation();
    const audio = document.getElementById('preview-audio');
    audio.src   = url;
    audio.play();
}

function previewVoice() {
    if (!selectedVoiceId) {
        alert('Please select a voice first.');
        return;
    }

    const template = document.getElementById('announcement_message').value.trim()
                   || 'Now serving {name}. Please proceed to {destination}.';
    const text     = template.replace(/\{name\}/g, 'Test Client').replace(/\{destination\}/g, 'Room 1');

    const stability      = document.getElementById('tts_stability').value;
    const similarityBoost = document.getElementById('tts_similarity_boost').value;
    const speed          = document.getElementById('tts_speed').value;

    const params = new URLSearchParams({
        voice_id:         selectedVoiceId,
        text:             text,
        stability:        stability,
        similarity_boost: similarityBoost,
        speed:            speed,
    });

    const audio = document.getElementById('preview-audio');
    const btn   = document.getElementById('preview-btn');

    btn.disabled = true;
    btn.querySelector('svg').classList.add('animate-spin');

    audio.src = PREVIEW_URL + '?' + params.toString();
    audio.oncanplay = () => {
        btn.disabled = false;
        btn.querySelector('svg').classList.remove('animate-spin');
        audio.play();
    };
    audio.onerror = () => {
        btn.disabled = false;
        btn.querySelector('svg').classList.remove('animate-spin');
        alert('Preview failed. Check the voice ID and API connection.');
    };
    audio.load();
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
</script>
</x-app-layout>
