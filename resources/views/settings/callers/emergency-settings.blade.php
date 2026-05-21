<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Emergency Settings
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Configure what is spoken over the PA and what is shown on the display board during an emergency.
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

        <form action="{{ route('service-point-callers.save-emergency-settings') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Hidden emergency voice fields -->
            <input type="hidden" name="emergency_tts_voice_id"   id="em_tts_voice_id"   value="{{ $config?->emergency_tts_voice_id }}">
            <input type="hidden" name="emergency_tts_voice_name" id="em_tts_voice_name" value="{{ $config?->emergency_tts_voice_name }}">

            <!-- ── AUDIO ──────────────────────────────────────────────────── -->
            <div class="flex items-center gap-3 pt-2">
                <svg class="w-5 h-5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                </svg>
                <h3 class="text-base font-bold text-gray-700 uppercase tracking-wide">Audio</h3>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <!-- Emergency Voice Picker -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Emergency Voice</h3>
                        <p class="mt-0.5 text-sm text-gray-500">Choose a dedicated voice for emergency announcements, independent of the regular call voice.</p>
                    </div>
                    <button type="button" onclick="loadEmVoices()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">
                        <svg id="em-refresh-icon" class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Refresh
                    </button>
                </div>

                @if($config?->emergency_tts_voice_id)
                    <div class="px-6 py-3 bg-red-50 border-b border-red-100 text-sm text-red-700 flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Current emergency voice: <strong>{{ $config->emergency_tts_voice_name ?? $config->emergency_tts_voice_id }}</strong>
                    </div>
                @endif

                <div id="em-voice-list" class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                    <div class="px-6 py-8 text-center text-sm text-gray-400">
                        Click <strong>Refresh</strong> to load available voices.
                    </div>
                </div>
            </div>

            <!-- Emergency Voice Quality -->
            <div class="bg-white shadow sm:rounded-lg px-6 py-6 space-y-5">
                <h3 class="text-base font-semibold text-gray-900">Emergency Voice Quality</h3>

                <!-- Stability -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Stability &mdash; <span id="em-stability-label">{{ number_format($config?->emergency_tts_stability ?? 0.5, 2) }}</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-0.5 mb-2">Higher = more consistent, lower = more expressive.</p>
                    <input type="range" name="emergency_tts_stability" id="em_tts_stability"
                           min="0" max="1" step="0.05"
                           value="{{ $config?->emergency_tts_stability ?? 0.5 }}"
                           oninput="document.getElementById('em-stability-label').textContent = parseFloat(this.value).toFixed(2)"
                           class="w-full accent-red-600">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>More variable</span><span>More stable</span>
                    </div>
                </div>

                <!-- Similarity Boost -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Clarity &amp; Similarity &mdash; <span id="em-similarity-label">{{ number_format($config?->emergency_tts_similarity_boost ?? 0.75, 2) }}</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-0.5 mb-2">How closely the output resembles the original voice.</p>
                    <input type="range" name="emergency_tts_similarity_boost" id="em_tts_similarity_boost"
                           min="0" max="1" step="0.05"
                           value="{{ $config?->emergency_tts_similarity_boost ?? 0.75 }}"
                           oninput="document.getElementById('em-similarity-label').textContent = parseFloat(this.value).toFixed(2)"
                           class="w-full accent-red-600">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>Low</span><span>High</span>
                    </div>
                </div>

                <!-- Speed -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Speed &mdash; <span id="em-speed-label">{{ number_format($config?->emergency_tts_speed ?? 1.0, 2) }}&times;</span>
                    </label>
                    <input type="range" name="emergency_tts_speed" id="em_tts_speed"
                           min="0.7" max="1.2" step="0.05"
                           value="{{ $config?->emergency_tts_speed ?? 1.0 }}"
                           oninput="document.getElementById('em-speed-label').textContent = parseFloat(this.value).toFixed(2) + '×'"
                           class="w-full accent-red-600">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>0.7× (slow)</span><span>1.0× (normal)</span><span>1.2× (fast)</span>
                    </div>
                </div>
            </div>

            <!-- Audio Repeat -->
            <div class="bg-white shadow sm:rounded-lg px-6 py-6">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Audio Repeat</h3>
                <p class="text-sm text-gray-500 mb-4">How many times the emergency audio plays and the pause between each replay.</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="emergency_repeat_count" class="block text-sm font-medium text-gray-700">
                            Repeat count <span class="text-gray-400 text-xs">(1–10)</span>
                        </label>
                        <input type="number" name="emergency_repeat_count" id="emergency_repeat_count"
                               min="1" max="10"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm @error('emergency_repeat_count') border-red-300 @enderror"
                               value="{{ old('emergency_repeat_count', $config?->emergency_repeat_count ?? 3) }}">
                        @error('emergency_repeat_count')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="emergency_repeat_interval" class="block text-sm font-medium text-gray-700">
                            Interval between replays <span class="text-gray-400 text-xs">(seconds, 0–60)</span>
                        </label>
                        <input type="number" name="emergency_repeat_interval" id="emergency_repeat_interval"
                               min="0" max="60"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm @error('emergency_repeat_interval') border-red-300 @enderror"
                               value="{{ old('emergency_repeat_interval', $config?->emergency_repeat_interval ?? 5) }}">
                        @error('emergency_repeat_interval')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- ── VIDEO ──────────────────────────────────────────────────── -->
            <div class="flex items-center gap-3 pt-2">
                <svg class="w-5 h-5 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 10l4.553-2.277A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                </svg>
                <h3 class="text-base font-bold text-gray-700 uppercase tracking-wide">Video</h3>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <!-- Display Settings -->
            <div class="bg-white shadow sm:rounded-lg px-6 py-6 space-y-5">
                <div class="pt-0">
                    <label for="emergency_display_duration" class="block text-sm font-medium text-gray-700">
                        Auto-dismiss after <span class="text-gray-400 text-xs font-normal">(seconds)</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-0.5 mb-2">
                        How long the emergency overlay stays on screen before automatically disappearing.
                        Set to <strong>0</strong> to keep it visible until the emergency is manually resolved.
                    </p>
                    <div class="flex items-center gap-3 max-w-xs">
                        <input type="number" name="emergency_display_duration" id="emergency_display_duration"
                               min="0" max="3600"
                               class="block w-32 border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm @error('emergency_display_duration') border-red-300 @enderror"
                               value="{{ old('emergency_display_duration', $config?->emergency_display_duration ?? 0) }}">
                        <span class="text-sm text-gray-500">seconds &nbsp;(0 = stay until resolved)</span>
                    </div>
                    @error('emergency_display_duration')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-5 border-t border-gray-100">
                    <h4 class="text-sm font-medium text-gray-700 mb-1">Flash sequence</h4>
                    <p class="text-xs text-gray-500 mb-4">
                        The overlay flashes on for a set duration, disappears, then repeats until the auto-dismiss period is reached.
                    </p>
                    <div class="grid grid-cols-2 gap-4 max-w-sm">
                        <div>
                            <label for="emergency_flash_on" class="block text-sm font-medium text-gray-700">
                                Flash on <span class="text-gray-400 text-xs">(seconds)</span>
                            </label>
                            <p class="text-xs text-gray-400 mt-0.5 mb-1">How long the overlay stays visible.</p>
                            <input type="number" name="emergency_flash_on" id="emergency_flash_on"
                                   min="1" max="60" step="1"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm @error('emergency_flash_on') border-red-300 @enderror"
                                   value="{{ old('emergency_flash_on', $config?->emergency_flash_on ?? 3) }}">
                            @error('emergency_flash_on')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="emergency_flash_off" class="block text-sm font-medium text-gray-700">
                                Flash off <span class="text-gray-400 text-xs">(seconds)</span>
                            </label>
                            <p class="text-xs text-gray-400 mt-0.5 mb-1">How long the overlay stays hidden.</p>
                            <input type="number" name="emergency_flash_off" id="emergency_flash_off"
                                   min="1" max="60" step="1"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm @error('emergency_flash_off') border-red-300 @enderror"
                                   value="{{ old('emergency_flash_off', $config?->emergency_flash_off ?? 1) }}">
                            @error('emergency_flash_off')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── BUTTONS ─────────────────────────────────────────────────── -->
            <div class="flex items-center gap-3 pt-2">
                <svg class="w-5 h-5 text-orange-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <h3 class="text-base font-bold text-gray-700 uppercase tracking-wide">Buttons</h3>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <!-- Emergency Buttons -->
            <div class="bg-white shadow sm:rounded-lg px-6 py-6">
                <p class="text-sm text-gray-500 mb-4">
                    Configure up to 2 quick-trigger emergency buttons shown in the top bar.
                    Leave Name or Message blank to hide a button.
                </p>

                @foreach([1,2] as $n)
                @php $fKey = $n === 1 ? 'F10' : 'F11'; @endphp
                <div class="mb-6 {{ $n === 2 ? 'pt-5 border-t border-gray-100' : '' }}">
                    <p class="text-sm font-semibold text-gray-700 mb-3">
                        Button {{ $n }}
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-semibold bg-gray-100 text-gray-500 border border-gray-300">{{ $fKey }}</span>
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name <span class="text-gray-400 text-xs">(max 30 chars)</span></label>
                            <input type="text" name="emergency_button_{{ $n }}_name" maxlength="30"
                                value="{{ old('emergency_button_'.$n.'_name', $config?->{'emergency_button_'.$n.'_name'}) }}"
                                placeholder="e.g. Code Blue"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Color</label>
                            <select name="emergency_button_{{ $n }}_color"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm">
                                @foreach(['red' => 'Red', 'green' => 'Green', 'yellow' => 'Yellow', 'amber' => 'Amber', 'blue' => 'Blue'] as $val => $label)
                                    <option value="{{ $val }}" {{ old('emergency_button_'.$n.'_color', $config?->{'emergency_button_'.$n.'_color'}) === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Audio Message
                                <span class="text-gray-400 text-xs font-normal">(spoken over PA)</span>
                            </label>
                            <input type="text" name="emergency_button_{{ $n }}_message"
                                value="{{ old('emergency_button_'.$n.'_message', $config?->{'emergency_button_'.$n.'_message'}) }}"
                                placeholder="e.g. Code blue — surgery room 2"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-400">Leave blank to hide this button.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Display Message
                                <span class="text-gray-400 text-xs font-normal">(shown on display board)</span>
                            </label>
                            <input type="text" name="emergency_button_{{ $n }}_display_message"
                                value="{{ old('emergency_button_'.$n.'_display_message', $config?->{'emergency_button_'.$n.'_display_message'}) }}"
                                placeholder="Leave blank to use the audio message"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-400">Falls back to audio message if empty.</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Actions -->
            <div class="bg-white shadow sm:rounded-lg px-6 py-4 flex items-center justify-between">
                <button type="button" onclick="previewEmVoice()"
                        id="em-preview-btn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Preview Emergency Voice
                </button>

                @if(in_array('Edit Callers', auth()->user()->permissions ?? []) || in_array('Manage Callers', auth()->user()->permissions ?? []))
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                    Save Emergency Settings
                </button>
                @endif
            </div>

        </form>

        <!-- Hidden audio element for previews -->
        <audio id="em-preview-audio" class="hidden"></audio>

    </div>
</div>

<script>
const EM_VOICES_URL  = "{{ route('service-point-callers.get-voices') }}";
const EM_PREVIEW_URL = "{{ route('service-point-callers.preview-voice') }}";

let emSelectedVoiceId   = document.getElementById('em_tts_voice_id').value;
let emSelectedVoiceName = document.getElementById('em_tts_voice_name').value;

async function loadEmVoices() {
    const list       = document.getElementById('em-voice-list');
    const refreshBtn = document.querySelector('button[onclick="loadEmVoices()"]');

    list.innerHTML = '<div class="px-6 py-8 text-center text-sm text-gray-400">Loading voices…</div>';
    refreshBtn.disabled = true;

    try {
        const res  = await fetch(EM_VOICES_URL);
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
            const isSelected = v.voice_id === emSelectedVoiceId;
            return `
            <label class="flex items-center gap-3 px-6 py-3 cursor-pointer hover:bg-gray-50 ${isSelected ? 'bg-red-50' : ''}" id="em-voice-row-${v.voice_id}">
                <input type="radio" name="_em_voice_radio" value="${v.voice_id}"
                       onchange="selectEmVoice('${v.voice_id}', '${escEmHtml(v.name)}')"
                       ${isSelected ? 'checked' : ''}
                       class="accent-red-600">
                <div class="flex-1 min-w-0">
                    <span class="text-sm font-medium text-gray-900">${escEmHtml(v.name)}</span>
                    ${v.category ? `<span class="ml-2 text-xs text-gray-400">${escEmHtml(v.category)}</span>` : ''}
                </div>
                ${v.preview_url ? `
                <button type="button" onclick="playEmSample(event, '${escEmHtml(v.preview_url)}')"
                        title="Play sample"
                        class="shrink-0 p-1.5 rounded-full text-gray-400 hover:text-red-600 hover:bg-red-50">
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

function selectEmVoice(voiceId, voiceName) {
    emSelectedVoiceId   = voiceId;
    emSelectedVoiceName = voiceName;
    document.getElementById('em_tts_voice_id').value   = voiceId;
    document.getElementById('em_tts_voice_name').value = voiceName;

    document.querySelectorAll('[id^="em-voice-row-"]').forEach(row => {
        row.classList.toggle('bg-red-50', row.id === 'em-voice-row-' + voiceId);
        row.classList.remove('bg-white');
    });
}

function playEmSample(event, url) {
    event.preventDefault();
    event.stopPropagation();
    const audio = document.getElementById('em-preview-audio');
    audio.src   = url;
    audio.play();
}

function previewEmVoice() {
    if (!emSelectedVoiceId) {
        alert('Please select an emergency voice first.');
        return;
    }

    const text = 'Emergency — please remain calm and follow staff instructions.';
    const params = new URLSearchParams({
        voice_id:         emSelectedVoiceId,
        text:             text,
        stability:        document.getElementById('em_tts_stability').value,
        similarity_boost: document.getElementById('em_tts_similarity_boost').value,
        speed:            document.getElementById('em_tts_speed').value,
    });

    const audio = document.getElementById('em-preview-audio');
    const btn   = document.getElementById('em-preview-btn');

    btn.disabled = true;
    btn.querySelector('svg').classList.add('animate-spin');

    audio.src = EM_PREVIEW_URL + '?' + params.toString();
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

function escEmHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
</script>
</x-app-layout>
