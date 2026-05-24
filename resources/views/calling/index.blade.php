<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        @if(!$caller)
            {{-- ── CALLER SELECTION SCREEN ─────────────────────────────────────────── --}}
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Select Caller</h2>
                <p class="mt-1 text-sm text-gray-500">Choose the caller station you are operating.</p>
            </div>

            @if($callers->isEmpty())
                <div class="bg-white shadow sm:rounded-lg px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M11 5.882A2 2 0 009.117 4H6a2 2 0 00-2 2v6a2 2 0 002 2h3.117M11 5.882l6.553 3.894a1 1 0 010 1.724L11 15.118"/>
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-gray-900">No callers configured</h3>
                    <p class="mt-1 text-sm text-gray-500">Ask your administrator to create caller stations in Settings → Callers.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($callers as $caller)
                        <form action="{{ route('calling.select') }}" method="POST">
                            @csrf
                            <input type="hidden" name="caller_id" value="{{ $caller->id }}">
                            <button type="submit"
                                    class="w-full text-left bg-white shadow sm:rounded-lg px-6 py-5 hover:shadow-md hover:border-indigo-300 border border-transparent transition-all group">
                                <div class="flex items-center gap-4">
                                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M11 5.882A2 2 0 009.117 4H6a2 2 0 00-2 2v6a2 2 0 002 2h3.117M11 5.882l6.553 3.894a1 1 0 010 1.724L11 15.118"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-base font-semibold text-gray-900 group-hover:text-indigo-700 truncate">{{ $caller->name }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            {{ $caller->service_points_count }}
                                            {{ Str::plural('service point', $caller->service_points_count) }}
                                        </p>
                                    </div>
                                </div>
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif

        @else
            {{-- ── QUEUE VIEW ───────────────────────────────────────────────────────── --}}

            <!-- Header: caller name + switch button -->
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M11 5.882A2 2 0 009.117 4H6a2 2 0 00-2 2v6a2 2 0 002 2h3.117M11 5.882l6.553 3.894a1 1 0 010 1.724L11 15.118"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">{{ $caller->name }}</h2>
                            <p class="text-sm text-gray-500">Calling queue — all service points for this caller.</p>
                        </div>
                    </div>
                </div>
                <form action="{{ route('calling.deselect') }}" method="POST" class="mt-4 md:mt-0">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-600 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Switch Caller
                    </button>
                </form>
            </div>

            <!-- ── PUBLIC ADDRESS PANEL ──────────────────────────────────────────── -->
        @if($paSections->isNotEmpty() && in_array('Broadcast Announcements', (array) auth()->user()->permissions))
        <div class="mb-6" x-data="paPanelWebRtc()">

            <!-- Collapsed toggle bar -->
            <div @click="expanded = !expanded"
                 class="bg-white shadow sm:rounded-lg px-5 py-3 flex items-center justify-between cursor-pointer select-none hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15.536 8.464a5 5 0 010 7.072M12 18.364a9 9 0 000-12.728M6.343 15.657a5 5 0 010-7.072"/>
                    </svg>
                    <span class="text-sm font-semibold text-gray-800">Public Address</span>
                    <template x-if="paState === 'broadcasting'">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 animate-pulse">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                            LIVE
                        </span>
                    </template>
                    <template x-if="paState === 'busy'">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                            In use
                        </span>
                    </template>
                </div>
                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>

            <!-- Expanded panel -->
            <div x-show="expanded" x-collapse class="bg-white shadow sm:rounded-b-lg border-t border-gray-100 px-5 py-4">

                <!-- Error / busy notice -->
                <template x-if="errorMsg">
                    <div class="mb-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2" x-text="errorMsg"></div>
                </template>

                <!-- Section selector -->
                <div class="flex flex-wrap items-end gap-3 mb-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Announce to</label>
                        <select x-model="selectedSectionId" :disabled="paState === 'broadcasting'"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100">
                            <option value="">— Select a section —</option>
                            @foreach($paSections as $section)
                                <option value="{{ $section->id }}">{{ $section->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Start / Stop button -->
                    <template x-if="paState === 'idle'">
                        <button @click="startAnnouncement()"
                                :disabled="!selectedSectionId"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-300 disabled:cursor-not-allowed shadow-sm transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15.536 8.464a5 5 0 010 7.072M12 18.364a9 9 0 000-12.728M6.343 15.657a5 5 0 010-7.072"/>
                            </svg>
                            Start Announcement
                        </button>
                    </template>

                    <template x-if="paState === 'broadcasting'">
                        <button @click="stopAnnouncement()"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium text-white bg-red-600 hover:bg-red-700 shadow-sm animate-pulse transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 10h6v4H9z"/>
                            </svg>
                            Stop Announcement
                        </button>
                    </template>

                    <template x-if="paState === 'busy'">
                        <button disabled class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium text-white bg-gray-400 cursor-not-allowed shadow-sm">
                            PA in Use
                        </button>
                    </template>
                </div>

                <!-- Broadcasting status bar -->
                <template x-if="paState === 'broadcasting'">
                    <div class="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-ping inline-block shrink-0"></span>
                        Broadcasting live to <strong x-text="selectedSectionName"></strong> &mdash; speak clearly into your microphone.
                    </div>
                </template>

                <template x-if="paState === 'busy'">
                    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2"
                       x-text="'PA is currently in use by ' + busyAnnouncer + '.'"></p>
                </template>

            </div>
        </div>
        @endif
        <!-- ── END PUBLIC ADDRESS PANEL ──────────────────────────────────────── -->

        @if($servicePoints->isEmpty())
                <div class="bg-white shadow sm:rounded-lg px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-gray-900">No service points assigned</h3>
                    <p class="mt-1 text-sm text-gray-500">Ask your administrator to attach service points to this caller.</p>
                </div>
            @else
                <div class="space-y-6">
                    @foreach($servicePoints as $servicePoint)
                        <div class="bg-white shadow sm:rounded-lg overflow-hidden">

                            <!-- Service point header -->
                            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                    <span class="text-sm font-semibold text-gray-700">{{ $servicePoint->name }}</span>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $servicePoint->pendingDeliveryQueues->count() > 0 ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $servicePoint->pendingDeliveryQueues->count() }} waiting
                                </span>
                            </div>

                            @if($servicePoint->pendingDeliveryQueues->isNotEmpty())
                                {{-- Next client call bar --}}
                                @php
                                    $nextQueue = $servicePoint->pendingDeliveryQueues->first();
                                    $nextName  = trim(optional($nextQueue->client)->first_name . ' ' . optional($nextQueue->client)->surname) ?: 'Next Client';
                                @endphp
                                <div class="px-5 py-2 bg-indigo-50 border-b border-indigo-100 flex items-center justify-between">
                                    <span class="text-xs text-indigo-600 font-medium">Next: <strong>{{ $nextName }}</strong></span>
                                    <button type="button"
                                            onclick="callClient('{{ addslashes($nextName) }}', '{{ addslashes($servicePoint->name) }}', {{ $nextQueue->client_id ?? 'null' }}, {{ $servicePoint->id }}, '{{ addslashes($nextQueue->item_name ?? '') }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15.536 8.464a5 5 0 010 7.072M12 18.364a9 9 0 000-12.728M6.343 15.657a5 5 0 010-7.072"/>
                                        </svg>
                                        Call Next
                                    </button>
                                </div>

                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Queued At</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waiting</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Call</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        @foreach($servicePoint->pendingDeliveryQueues as $i => $queue)
                                            @php
                                                $clientName = trim(optional($queue->client)->first_name . ' ' . optional($queue->client)->surname) ?: 'Client';
                                            @endphp
                                            <tr class="hover:bg-gray-50 {{ $i === 0 ? 'bg-indigo-50/30' : '' }}">
                                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500">{{ $i + 1 }}</td>
                                                <td class="px-5 py-3 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ $clientName }}</div>
                                                    @if(optional($queue->client)->client_id)
                                                        <div class="text-xs text-gray-400">{{ $queue->client->client_id }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-600">
                                                    {{ $queue->item_name ?? '—' }}
                                                    @if($queue->quantity && $queue->quantity > 1)
                                                        <span class="text-xs text-gray-400">×{{ $queue->quantity }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $queue->queued_at ? \Carbon\Carbon::parse($queue->queued_at)->format('H:i') : '—' }}
                                                </td>
                                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $queue->formatted_waiting_time ?? '—' }}
                                                </td>
                                                <td class="px-5 py-3 whitespace-nowrap">
                                                    @php
                                                        $priorityColors = [
                                                            'urgent' => 'bg-red-100 text-red-800',
                                                            'high'   => 'bg-orange-100 text-orange-800',
                                                            'normal' => 'bg-gray-100 text-gray-700',
                                                            'low'    => 'bg-green-100 text-green-700',
                                                        ];
                                                        $color = $priorityColors[$queue->priority ?? 'normal'] ?? 'bg-gray-100 text-gray-700';
                                                    @endphp
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }}">
                                                        {{ ucfirst($queue->priority ?? 'normal') }}
                                                    </span>
                                                </td>
                                                <td class="px-5 py-3 whitespace-nowrap">
                                                    <button type="button"
                                                            onclick="callClient('{{ addslashes($clientName) }}', '{{ addslashes($servicePoint->name) }}', {{ $queue->client_id ?? 'null' }}, {{ $servicePoint->id }}, '{{ addslashes($queue->item_name ?? '') }}')"
                                                            class="w-10 h-10 rounded-full bg-indigo-100 hover:bg-indigo-200 flex items-center justify-center transition-colors"
                                                            title="Call {{ $clientName }}">
                                                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                  d="M15.536 8.464a5 5 0 010 7.072M12 18.364a9 9 0 000-12.728M6.343 15.657a5 5 0 010-7.072"/>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="px-5 py-8 text-center text-sm text-gray-400">
                                    No clients currently waiting at this service point.
                                </div>
                            @endif

                        </div>
                    @endforeach
                </div>
            @endif

        @endif
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const CURRENT_USER_ID = document.querySelector('meta[name="user-id"]')?.content;
const PA_START_URL = '{{ route('pa.start') }}';
const PA_STATUS_URL = '{{ route('pa.status') }}';
const PA_STOP_URL = '{{ route('pa.stop') }}';
const PA_SIGNAL_CALLER_URL = '{{ route('pa.signal.caller') }}';

function paPanelWebRtc() {
    return {
        expanded: false,
        paState: 'idle',
        selectedSectionId: '',
        busyAnnouncer: '',
        busySectionName: '',
        errorMsg: '',
        _sections: @json($paSections->pluck('name', 'id')),
        _stream: null,
        _pollTimer: null,
        _stopInFlight: false,
        _sessionId: null,
        _peerConnections: {},
        _signalChannelBound: false,
        _iceServers: {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
            ],
        },

        init() {
            this.pollStatus();
            this.ensureSignalChannel();
            window.addEventListener('pagehide', () => this.releaseOnUnload());
        },

        get selectedSectionName() {
            return this._sections[this.selectedSectionId] || '';
        },

        pollStatus() {
            fetch(PA_STATUS_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(async (response) => {
                    if (!response.ok) {
                        return null;
                    }

                    return response.json();
                })
                .then((data) => {
                    if (!data) {
                        return;
                    }

                    if (data.active && !data.is_mine) {
                        this.paState = 'busy';
                        this.busyAnnouncer = data.announcer_name || '';
                        this.busySectionName = data.section_name || '';
                    } else if (data.active && data.is_mine) {
                        if (this.paState !== 'starting' && this.paState !== 'stopping') {
                            this.paState = 'broadcasting';
                        }
                        this._sessionId = data.session_id || this._sessionId;
                        this.busyAnnouncer = '';
                        this.busySectionName = '';
                        if (data.section_id) {
                            this.selectedSectionId = String(data.section_id);
                        }
                    } else if (!data.active && this.paState !== 'starting' && this.paState !== 'stopping') {
                        this.paState = 'idle';
                        this._sessionId = null;
                        this.busyAnnouncer = '';
                        this.busySectionName = '';
                    }
                })
                .catch(() => {})
                .finally(() => {
                    this._pollTimer = setTimeout(() => this.pollStatus(), 10000);
                });
        },

        ensureSignalChannel() {
            if (this._signalChannelBound || !window.Echo || !CURRENT_USER_ID) {
                return;
            }

            window.Echo.private(`user.${CURRENT_USER_ID}`)
                .listen('PaWebRtcSignalToUser', async (payload) => {
                    if (!this._sessionId || payload.session_id !== this._sessionId) {
                        return;
                    }

                    const connection = this._peerConnections[payload.caller_id];
                    if (!connection) {
                        return;
                    }

                    if (payload.type === 'answer' && payload.data) {
                        const description = this.decodeSessionDescription(payload.data);
                        await connection.setRemoteDescription(new RTCSessionDescription(description));
                    } else if (payload.type === 'candidate' && payload.data) {
                        try {
                            await connection.addIceCandidate(new RTCIceCandidate(payload.data));
                        } catch (_) {}
                    }
                });

            this._signalChannelBound = true;
        },

        decodeSessionDescription(payload) {
            if (payload?.sdp_b64) {
                return {
                    type: payload.type,
                    sdp: atob(payload.sdp_b64),
                };
            }

            return payload;
        },

        async startAnnouncement() {
            if (!this.selectedSectionId || this.paState === 'busy' || this.paState === 'starting') {
                return;
            }

            this.errorMsg = '';
            this.paState = 'starting';

            let startResponse;

            try {
                startResponse = await fetch(PA_START_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ section_id: this.selectedSectionId }),
                });
            } catch (_) {
                this.paState = 'idle';
                this.errorMsg = 'Could not contact the server to start the announcement.';
                return;
            }

            if (!startResponse.ok) {
                const err = await startResponse.json().catch(() => ({}));
                this.errorMsg = err.error || 'Could not start announcement.';
                this.paState = startResponse.status === 409 ? 'busy' : 'idle';
                if (startResponse.status === 409) {
                    this.busyAnnouncer = err.announcer_name || '';
                    this.busySectionName = err.section_name || '';
                }
                return;
            }

            const payload = await startResponse.json();
            this._sessionId = payload.session_id || null;

            try {
                this._stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                        sampleRate: 48000,
                        channelCount: 1,
                    },
                });
            } catch (_) {
                await fetch(PA_STOP_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ section_id: this.selectedSectionId }),
                }).catch(() => {});

                this.paState = 'idle';
                this._sessionId = null;
                this.errorMsg = 'Microphone access denied. Please allow mic permissions and try again.';
                return;
            }

            try {
                await this.startPeerBroadcast(payload.target_callers || []);
            } catch (_) {
                this.errorMsg = 'Could not start the live audio stream.';
                await this.stopAnnouncement(true);
                return;
            }

            this.paState = 'broadcasting';
            this.busyAnnouncer = '';
            this.busySectionName = '';
        },

        waitForIceGatheringComplete(connection, timeoutMs = 5000) {
            if (!connection || connection.iceGatheringState === 'complete') {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                let timeoutId = null;

                const cleanup = () => {
                    connection.removeEventListener('icegatheringstatechange', handleStateChange);
                    if (timeoutId) clearTimeout(timeoutId);
                };

                const handleStateChange = () => {
                    if (connection.iceGatheringState === 'complete') {
                        cleanup();
                        resolve();
                    }
                };

                timeoutId = setTimeout(() => {
                    cleanup();
                    resolve();
                }, timeoutMs);

                connection.addEventListener('icegatheringstatechange', handleStateChange);
            });
        },

        async startPeerBroadcast(targetCallers) {
            this.closePeerConnections();

            for (const caller of targetCallers) {
                const connection = await this.createPeerConnection(caller);
                this._peerConnections[caller.id] = connection;

                const offer = await connection.createOffer();
                await connection.setLocalDescription(offer);
                await this.waitForIceGatheringComplete(connection);
                await this.sendSignalToCaller(caller.id, 'offer', {
                    type: connection.localDescription?.type || offer.type,
                    sdp_b64: btoa(connection.localDescription?.sdp || offer.sdp || ''),
                });
            }
        },

        async createPeerConnection(caller) {
            const connection = new RTCPeerConnection(this._iceServers);

            this._stream.getTracks().forEach((track) => {
                connection.addTrack(track, this._stream);
            });

            connection.onicecandidate = async (event) => {
                if (!event.candidate) {
                    return;
                }

                await this.sendSignalToCaller(caller.id, 'candidate', event.candidate.toJSON());
            };

            connection.oniceconnectionstatechange = () => {
                if (['failed', 'disconnected', 'closed'].includes(connection.iceConnectionState) && this.paState === 'broadcasting') {
                    this.errorMsg = `${caller.name} disconnected from the live announcement.`;
                }
            };

            return connection;
        },

        async sendSignalToCaller(callerId, type, data) {
            const response = await fetch(PA_SIGNAL_CALLER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    caller_id: callerId,
                    session_id: this._sessionId,
                    type,
                    data,
                }),
            });

            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err.error || 'Failed to deliver PA signaling message.');
            }
        },

        async stopAnnouncement(isForced = false) {
            if (this._stopInFlight) {
                return;
            }

            this._stopInFlight = true;
            this.paState = 'stopping';

            this.closePeerConnections();
            this.stopStream();

            try {
                const response = await fetch(PA_STOP_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ section_id: this.selectedSectionId }),
                });

                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    if (!isForced) {
                        this.errorMsg = err.error || 'Could not stop the announcement cleanly.';
                    }
                    this.paState = 'broadcasting';
                    return;
                }

                this.paState = 'idle';
                this._sessionId = null;
                this.busyAnnouncer = '';
                this.busySectionName = '';
            } finally {
                this._stopInFlight = false;
            }
        },

        closePeerConnections() {
            Object.values(this._peerConnections).forEach((connection) => {
                try {
                    connection.close();
                } catch (_) {}
            });

            this._peerConnections = {};
        },

        stopStream() {
            if (!this._stream) {
                return;
            }

            this._stream.getTracks().forEach((track) => track.stop());
            this._stream = null;
        },

        releaseOnUnload() {
            if (this.paState !== 'broadcasting') {
                return;
            }

            if (this._pollTimer) {
                clearTimeout(this._pollTimer);
            }

            this.closePeerConnections();
            this.stopStream();

            fetch(PA_STOP_URL, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ section_id: this.selectedSectionId }),
            }).catch(() => {});
        },
    };
}

function callClient(clientName, servicePointName, clientId, servicePointId, itemName) {
    Swal.fire({
        title: 'Calling...',
        html: `<div class="text-center">
                    <div class="text-2xl font-bold text-indigo-700 mt-2">${clientName}</div>
                    <p class="text-gray-500 text-sm mt-2">Please proceed to <strong>${servicePointName}</strong></p>
               </div>`,
        icon: 'info',
        confirmButtonText: 'Done',
        confirmButtonColor: '#4f46e5',
        timer: 8000,
        timerProgressBar: true,
    });

    // Record the call in the caller log
    fetch('{{ route('calling.announce') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            client_id: clientId,
            client_name: clientName,
            service_point_id: servicePointId,
            item_name: itemName || null
        })
    });
}
</script>
</x-app-layout>
