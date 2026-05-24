@php
    $sectionMap = $sections->mapWithKeys(function ($section) {
        return [
            (string) $section->id => [
                'name' => $section->name,
                'callers' => $section->callers->map(fn ($caller) => [
                    'id' => $caller->id,
                    'name' => $caller->name,
                ])->values()->all(),
            ],
        ];
    });
@endphp

<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8" x-data="paConsole()">

        <div class="md:flex md:items-start md:justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Public Address Console</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Announce directly to the caller stations assigned to a section. This console does not load service-point queues.
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <a href="{{ route('pa-sections.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Manage Sections
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif

        @if($sections->isEmpty())
            <div class="bg-white shadow sm:rounded-lg px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15.536 8.464a5 5 0 010 7.072M12 18.364a9 9 0 000-12.728M6.343 15.657a5 5 0 010-7.072"/>
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900">No PA sections configured</h3>
                <p class="mt-1 text-sm text-gray-500">Create a section and assign caller stations before making an announcement.</p>
                <div class="mt-6">
                    <a href="{{ route('pa-sections.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Go to Public Announcements
                    </a>
                </div>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(300px,1fr)]">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="flex items-center gap-2.5 mb-5">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15.536 8.464a5 5 0 010 7.072M12 18.364a9 9 0 000-12.728M6.343 15.657a5 5 0 010-7.072"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Live Announcement</h3>
                            <p class="text-sm text-gray-500">Choose the destination section, then start speaking.</p>
                        </div>
                    </div>

                    <template x-if="errorMsg">
                        <div class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2" x-text="errorMsg"></div>
                    </template>

                    <template x-if="paState === 'busy'">
                        <div class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2"
                             x-text="'PA is currently in use by ' + busyAnnouncer + (busySectionName ? ' on ' + busySectionName + '.' : '.')"></div>
                    </template>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Announce to</label>
                            <select x-model="selectedSectionId"
                                    @change="syncSelection()"
                                    :disabled="isLockedUi"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100">
                                <option value="">Select a section</option>
                                @foreach($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 min-h-[108px]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Target Stations</p>
                            <template x-if="selectedCallers.length">
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="caller in selectedCallers" :key="caller.id">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700" x-text="caller.name"></span>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!selectedCallers.length">
                                <p class="text-sm text-gray-500">
                                    Select a section to see which caller stations will receive the announcement.
                                </p>
                            </template>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <template x-if="paState !== 'broadcasting'">
                                <button @click="startAnnouncement()"
                                        :disabled="!selectedSectionId || paState === 'busy' || paState === 'starting' || paState === 'stopping'"
                                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-300 disabled:cursor-not-allowed shadow-sm transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15.536 8.464a5 5 0 010 7.072M12 18.364a9 9 0 000-12.728M6.343 15.657a5 5 0 010-7.072"/>
                                    </svg>
                                    <span x-text="paState === 'starting' ? 'Starting...' : 'Start Announcement'"></span>
                                </button>
                            </template>

                            <template x-if="paState === 'broadcasting'">
                                <button @click="stopAnnouncement()"
                                        :disabled="paState === 'stopping'"
                                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-md text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:bg-red-300 disabled:cursor-not-allowed shadow-sm animate-pulse transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 10h6v4H9z"/>
                                    </svg>
                                    <span x-text="paState === 'stopping' ? 'Stopping...' : 'Stop Announcement'"></span>
                                </button>
                            </template>

                            <template x-if="paState === 'broadcasting'">
                                <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-700 border border-red-200">
                                    <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                                    LIVE on <span x-text="selectedSectionName"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="bg-white shadow sm:rounded-lg p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Available Sections</h3>
                        <p class="mt-1 text-sm text-gray-500">Each section sends audio to its assigned caller stations.</p>
                        <div class="mt-4 space-y-3">
                            @foreach($sections as $section)
                                <button type="button"
                                        @click="selectSection('{{ $section->id }}')"
                                        :class="selectedSectionId === '{{ $section->id }}' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-white hover:border-indigo-200'"
                                        class="w-full text-left border rounded-lg px-4 py-3 transition-colors">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ $section->name }}</p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ $section->callers->count() }} {{ \Illuminate\Support\Str::plural('station', $section->callers->count()) }}
                                            </p>
                                        </div>
                                        @if($section->callers->isEmpty())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">
                                                No stations
                                            </span>
                                        @endif
                                    </div>
                                    @if($section->callers->isNotEmpty())
                                        <div class="mt-3 flex flex-wrap gap-1.5">
                                            @foreach($section->callers as $caller)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                                    {{ $caller->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-indigo-900">Before You Start</h3>
                        <ul class="mt-2 space-y-2 text-sm text-indigo-900/80">
                            <li>Choose the section whose caller stations should play the announcement.</li>
                            <li>The console locks the PA session before asking for microphone access.</li>
                            <li>Your microphone is streamed live to each assigned caller station over WebRTC.</li>
                        </ul>
                    </div>
                </div>
            </div>
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

function paConsole() {
    return {
        paState: 'idle',
        selectedSectionId: '{{ $sections->first()->id ?? '' }}',
        busyAnnouncer: '',
        busySectionName: '',
        errorMsg: '',
        _sections: @json($sectionMap),
        _stream: null,
        _streamMeter: null,
        _streamMeterTimer: null,
        _pollTimer: null,
        _stopInFlight: false,
        _sessionId: null,
        _peerConnections: {},
        _senderStatsTimers: {},
        _signalChannelBound: false,
        _iceServers: {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
            ],
        },

        init() {
            this.syncSelection();
            this.pollStatus();
            this.ensureSignalChannel();
            window.addEventListener('pagehide', () => this.releaseOnUnload());
        },

        get selectedSectionName() {
            return this._sections[this.selectedSectionId]?.name || '';
        },

        get selectedCallers() {
            return this._sections[this.selectedSectionId]?.callers || [];
        },

        get isLockedUi() {
            return this.paState === 'broadcasting' || this.paState === 'starting' || this.paState === 'stopping';
        },

        syncSelection() {
            this.errorMsg = '';
        },

        selectSection(sectionId) {
            if (this.isLockedUi) return;
            this.selectedSectionId = sectionId;
            this.syncSelection();
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

        async startAnnouncement() {
            if (!this.selectedSectionId || this.paState === 'busy' || this.paState === 'starting') {
                return;
            }

            if (!this.selectedCallers.length) {
                this.errorMsg = 'This section has no caller stations assigned yet.';
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
                this.startLocalMicMeter();
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
                console.log('PA local track state', {
                    callerId: caller.id,
                    kind: track.kind,
                    enabled: track.enabled,
                    muted: track.muted,
                    readyState: track.readyState,
                    settings: typeof track.getSettings === 'function' ? track.getSettings() : {},
                });

                const sender = connection.addTrack(track, this._stream);
                this.startSenderStats(caller.id, sender);
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

        startSenderStats(callerId, sender) {
            if (!sender || this._senderStatsTimers[callerId]) {
                return;
            }

            this._senderStatsTimers[callerId] = setInterval(async () => {
                try {
                    const stats = await sender.getStats();
                    stats.forEach((report) => {
                        if (report.type === 'track') {
                            console.log('PA sender track stats', {
                                callerId,
                                audioLevel: report.audioLevel,
                                totalAudioEnergy: report.totalAudioEnergy,
                                totalSamplesDuration: report.totalSamplesDuration,
                            });
                        }

                        if (
                            report.type === 'outbound-rtp' &&
                            (report.kind === 'audio' || report.mediaType === 'audio')
                        ) {
                            console.log('PA sender stats', {
                                callerId,
                                bytesSent: report.bytesSent,
                                packetsSent: report.packetsSent,
                                mediaType: report.mediaType || report.kind,
                            });
                        }
                    });
                } catch (_) {}
            }, 1000);
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

        decodeSessionDescription(payload) {
            if (payload.sdp_b64) {
                return {
                    type: payload.type,
                    sdp: atob(payload.sdp_b64),
                };
            }

            return payload;
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
            Object.values(this._senderStatsTimers).forEach((timer) => clearInterval(timer));
            this._senderStatsTimers = {};

            Object.values(this._peerConnections).forEach((connection) => {
                try {
                    connection.close();
                } catch (_) {}
            });

            this._peerConnections = {};
        },

        stopStream() {
            if (this._streamMeterTimer) {
                clearInterval(this._streamMeterTimer);
                this._streamMeterTimer = null;
            }

            if (this._streamMeter) {
                try {
                    this._streamMeter.close();
                } catch (_) {}
                this._streamMeter = null;
            }

            if (!this._stream) {
                return;
            }

            this._stream.getTracks().forEach((track) => track.stop());
            this._stream = null;
        },

        startLocalMicMeter() {
            if (!this._stream || !window.AudioContext) {
                return;
            }

            if (this._streamMeterTimer) {
                clearInterval(this._streamMeterTimer);
                this._streamMeterTimer = null;
            }

            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            const meterContext = new AudioCtx();
            const meterSource = meterContext.createMediaStreamSource(this._stream);
            const analyser = meterContext.createAnalyser();
            analyser.fftSize = 2048;
            meterSource.connect(analyser);
            this._streamMeter = meterContext;

            const data = new Uint8Array(analyser.fftSize);
            this._streamMeterTimer = setInterval(() => {
                analyser.getByteTimeDomainData(data);
                let sum = 0;
                for (let i = 0; i < data.length; i++) {
                    const centered = (data[i] - 128) / 128;
                    sum += centered * centered;
                }
                const rms = Math.sqrt(sum / data.length);
                console.log('PA mic level', rms.toFixed(4));
            }, 1000);
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
</script>
</x-app-layout>
