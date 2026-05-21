const DEFAULT_STUN_URLS = [
    'stun:stun.l.google.com:19302',
    'stun:stun1.l.google.com:19302',
];

function parseIceUrls(rawValue) {
    if (!rawValue) {
        return [];
    }

    return String(rawValue)
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean);
}

function hasTurnServer(iceConfig) {
    return (iceConfig.iceServers || []).some((server) => {
        const urls = Array.isArray(server.urls) ? server.urls : [server.urls];
        return urls.some((url) => String(url || '').startsWith('turn:') || String(url || '').startsWith('turns:'));
    });
}

function buildIceConfiguration() {
    const stunUrls = parseIceUrls(import.meta.env.VITE_WEBRTC_STUN_URLS);
    const turnUrls = parseIceUrls(import.meta.env.VITE_WEBRTC_TURN_URL);
    const turnUsername = String(import.meta.env.VITE_WEBRTC_TURN_USERNAME || '').trim();
    const turnCredential = String(import.meta.env.VITE_WEBRTC_TURN_CREDENTIAL || '').trim();
    const iceTransportPolicy = String(import.meta.env.VITE_WEBRTC_ICE_TRANSPORT_POLICY || 'all').trim() || 'all';

    const iceServers = [];
    const resolvedStunUrls = stunUrls.length > 0 ? stunUrls : DEFAULT_STUN_URLS;

    if (resolvedStunUrls.length > 0) {
        iceServers.push({ urls: resolvedStunUrls });
    }

    if (turnUrls.length > 0) {
        const turnServer = { urls: turnUrls };

        if (turnUsername) {
            turnServer.username = turnUsername;
        }

        if (turnCredential) {
            turnServer.credential = turnCredential;
        }

        iceServers.push(turnServer);
    }

    return {
        iceServers,
        iceTransportPolicy,
    };
}

const WEBRTC_ICE_CONFIGURATION = buildIceConfiguration();
const WEBRTC_HAS_TURN = hasTurnServer(WEBRTC_ICE_CONFIGURATION);
const COMPATIBILITY_AUDIO_START_DELAY_MS = 3000;
const COMPATIBILITY_AUDIO_SEGMENT_MS = 1000;
const COMPATIBILITY_AUDIO_MAX_QUEUE = 6;

export default function callingSystem() {
    return {
        // State
        callState: 'idle', // idle, incoming, calling, connected, ended
        remoteUser: null,
        callId: null,
        isMuted: false,
        duration: 0,
        durationFormatted: '00:00',
        onlineUsers: [],
        mediaConnectionState: 'idle',
        mediaConnectionMessage: '',

        // Internal variables
        peerConnection: null,
        localStream: null,
        remoteStream: null,
        timerInterval: null,
        ringtoneAudio: null,
        outgoingRingtoneAudio: null,
        incomingPollInterval: null,
        callStatusPollInterval: null,
        signalPollInterval: null,
        reconnectAttempts: 0,
        maxReconnectAttempts: 3,
        hasInitiatedWebRTC: false,
        processedSignalIds: [],
        pendingIceCandidates: [],
        audioPlaybackUnlockHandler: null,
        hasTurnConfiguration: WEBRTC_HAS_TURN,
        compatibilityAudioActive: false,
        compatibilityAudioPlaying: false,
        compatibilityFallbackTimer: null,
        compatibilityRecorder: null,
        compatibilityRecorderStopTimer: null,
        compatibilityChunkQueue: [],
        compatibilityChunkSequence: 0,
        compatibilityCurrentChunkUrl: null,

        // ICE servers config
        iceServers: WEBRTC_ICE_CONFIGURATION,

        init() {
            // Read user preference from meta tag
            const ringtonePref = document.querySelector('meta[name="user-p2p-ringtone"]')?.content || 'default';

            // Setup Web Audio API ringtones based on preference
            if (ringtonePref === 'rapid') {
                this.ringtoneAudio = this.createBeepPattern(600, 200, 100);
            } else if (ringtonePref === 'deep') {
                this.ringtoneAudio = this.createBeepPattern(300, 1000, 1000);
            } else if (ringtonePref === 'urgent') {
                this.ringtoneAudio = this.createBeepPattern(800, 400, 200);
            } else {
                this.ringtoneAudio = this.createBeepPattern(440, 800, 400); // default
            }

            this.outgoingRingtoneAudio = this.createBeepPattern(480, 500, 500);

            const userId = document.querySelector('meta[name="user-id"]')?.content;
            const businessId = document.querySelector('meta[name="business-id"]')?.content;

            if (userId && window.Echo) {
                // Private channel for direct call events
                window.Echo.private(`user.${userId}`)
                    .listen('.IncomingCall', (e) => {
                        console.log('Incoming call:', e);
                        if (this.callState !== 'idle') {
                            this.rejectCall(e.call_id, true);
                            return;
                        }
                        this.callId = e.call_id;
                        this.remoteUser = e.caller;
                        this.changeState('incoming');
                        this.startCallStatusPoll();
                    })
                    .listen('.CallAccepted', (e) => {
                        console.log('Call accepted by callee:', e);
                        if (this.callId === e.call_id) {
                            this.changeState('connected');
                            this.startCallStatusPoll();
                            this.initiateWebRTCOnce();
                        }
                    })
                    .listen('.CallRejected', (e) => {
                        console.log('Call rejected:', e);
                        if (this.callId === e.call_id) {
                            this.changeState('ended');
                            setTimeout(() => this.resetCall(), 2000);
                        }
                    })
                    .listen('.CallEnded', (e) => {
                        console.log('Call ended:', e);
                        if (this.callId === e.call_id) {
                            this.changeState('ended');
                            setTimeout(() => this.resetCall(), 2000);
                        }
                    })
                    .listen('.WebRTCSignal', async (e) => {
                        await this.handleWebRtcSignal(e);
                    });

                // Presence channel — real-time online users (no polling)
                if (businessId) {
                    window.Echo.join(`presence-business.${businessId}`)
                        .here((users) => {
                            this.onlineUsers = users.filter(u => String(u.id) !== String(userId));
                        })
                        .joining((user) => {
                            if (!this.onlineUsers.find(u => u.id === user.id)) {
                                this.onlineUsers.push(user);
                            }
                        })
                        .leaving((user) => {
                            this.onlineUsers = this.onlineUsers.filter(u => u.id !== user.id);
                        })
                        .error((error) => {
                            console.error('Presence channel error:', error);
                        });
                }
            }

            this.startIncomingPoll();
        },

        // --- Ringtone helpers (Web Audio API) ---

        createBeepPattern(frequency, onMs, offMs) {
            let ctx = null, interval = null, playing = false;
            return {
                play() {
                    if (playing) return;
                    playing = true;
                    ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const beep = () => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.frequency.value = frequency;
                        osc.type = 'sine';
                        gain.gain.setValueAtTime(0.3, ctx.currentTime);
                        osc.start();
                        osc.stop(ctx.currentTime + onMs / 1000);
                    };
                    beep();
                    interval = setInterval(beep, onMs + offMs);
                },
                stop() {
                    if (!playing) return;
                    playing = false;
                    clearInterval(interval);
                    interval = null;
                    ctx?.close();
                    ctx = null;
                }
            };
        },

        getRemoteAudioElement() {
            return document.getElementById('remoteAudio');
        },

        getCompatibilityAudioElement() {
            return document.getElementById('compatibilityRemoteAudio');
        },

        getPlayableAudioElements() {
            return [
                this.getRemoteAudioElement(),
                this.getCompatibilityAudioElement(),
            ].filter(Boolean);
        },

        attachRemoteStream() {
            const audioElement = this.getRemoteAudioElement();
            if (!audioElement || !this.remoteStream) {
                return;
            }

            audioElement.autoplay = true;
            audioElement.playsInline = true;
            audioElement.muted = false;
            audioElement.volume = 1;

            if (audioElement.srcObject !== this.remoteStream) {
                audioElement.srcObject = this.remoteStream;
            }

            this.ensureAudioElementPlayback(audioElement);
        },

        ensureAudioElementPlayback(audioElement) {
            if (!audioElement || (!audioElement.srcObject && !audioElement.src)) {
                return;
            }

            audioElement.autoplay = true;
            audioElement.playsInline = true;
            audioElement.muted = false;
            audioElement.volume = 1;

            const playPromise = audioElement.play();
            if (typeof playPromise?.catch !== 'function') {
                return;
            }

            playPromise
                .then(() => this.unbindAudioPlaybackUnlock())
                .catch((error) => {
                    console.warn('Remote audio playback is waiting for browser permission:', error);
                    this.bindAudioPlaybackUnlock();
                });
        },

        ensureRemoteAudioPlayback() {
            this.ensureAudioElementPlayback(this.getRemoteAudioElement());
        },

        ensureCompatibilityAudioPlayback() {
            this.ensureAudioElementPlayback(this.getCompatibilityAudioElement());
        },

        bindAudioPlaybackUnlock() {
            if (this.audioPlaybackUnlockHandler) {
                return;
            }

            this.audioPlaybackUnlockHandler = () => {
                this.ensureRemoteAudioPlayback();
                this.ensureCompatibilityAudioPlayback();

                const anyPlaying = this.getPlayableAudioElements().some((audioElement) => !audioElement.paused);
                if (anyPlaying) {
                    this.unbindAudioPlaybackUnlock();
                }
            };

            ['click', 'touchstart', 'keydown'].forEach((eventName) => {
                window.addEventListener(eventName, this.audioPlaybackUnlockHandler);
            });
        },

        unbindAudioPlaybackUnlock() {
            if (!this.audioPlaybackUnlockHandler) {
                return;
            }

            ['click', 'touchstart', 'keydown'].forEach((eventName) => {
                window.removeEventListener(eventName, this.audioPlaybackUnlockHandler);
            });

            this.audioPlaybackUnlockHandler = null;
        },

        async addRemoteIceCandidate(candidateData) {
            const candidate = new RTCIceCandidate(candidateData);

            if (!this.peerConnection?.remoteDescription) {
                this.pendingIceCandidates.push(candidate);
                return;
            }

            await this.peerConnection.addIceCandidate(candidate);
        },

        async flushPendingIceCandidates() {
            if (!this.peerConnection?.remoteDescription || this.pendingIceCandidates.length === 0) {
                return;
            }

            const pendingCandidates = this.pendingIceCandidates.splice(0);
            for (const candidate of pendingCandidates) {
                try {
                    await this.peerConnection.addIceCandidate(candidate);
                } catch (error) {
                    console.error('Failed to apply queued ICE candidate', error);
                }
            }
        },

        serializeSessionDescription(description) {
            if (!description) {
                return null;
            }

            if (typeof description.toJSON === 'function') {
                return description.toJSON();
            }

            return {
                type: description.type,
                sdp: description.sdp,
            };
        },

        serializeIceCandidate(candidate) {
            if (!candidate) {
                return null;
            }

            if (typeof candidate.toJSON === 'function') {
                return candidate.toJSON();
            }

            return {
                candidate: candidate.candidate,
                sdpMid: candidate.sdpMid,
                sdpMLineIndex: candidate.sdpMLineIndex,
                usernameFragment: candidate.usernameFragment,
            };
        },

        waitForIceGatheringComplete(timeoutMs = 5000) {
            if (!this.peerConnection || this.peerConnection.iceGatheringState === 'complete') {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                const peerConnection = this.peerConnection;
                let timeoutId = null;

                const cleanup = () => {
                    if (!peerConnection) {
                        return;
                    }

                    peerConnection.removeEventListener('icegatheringstatechange', handleStateChange);
                    if (timeoutId) {
                        clearTimeout(timeoutId);
                    }
                };

                const handleStateChange = () => {
                    if (peerConnection.iceGatheringState === 'complete') {
                        cleanup();
                        resolve();
                    }
                };

                timeoutId = setTimeout(() => {
                    cleanup();
                    resolve();
                }, timeoutMs);

                peerConnection.addEventListener('icegatheringstatechange', handleStateChange);
            });
        },

        setMediaConnectionState(state, message = '') {
            this.mediaConnectionState = state;
            this.mediaConnectionMessage = message;
        },

        buildMediaFailureMessage() {
            if (this.hasTurnConfiguration) {
                return 'Audio connection failed. Please retry the call.';
            }

            return 'Audio connection failed. TURN server configuration is likely required for this network.';
        },

        async blobToBase64(blob) {
            const buffer = await blob.arrayBuffer();
            const bytes = new Uint8Array(buffer);
            let binary = '';
            const chunkSize = 0x8000;

            for (let i = 0; i < bytes.length; i += chunkSize) {
                binary += String.fromCharCode(...bytes.subarray(i, i + chunkSize));
            }

            return btoa(binary);
        },

        base64ToBlob(base64, mimeType = 'audio/webm') {
            const binary = atob(base64);
            const bytes = new Uint8Array(binary.length);

            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }

            return new Blob([bytes], { type: mimeType });
        },

        stopPeerConnection() {
            if (this.peerConnection) {
                try {
                    this.peerConnection.close();
                } catch (_) {}
            }

            this.peerConnection = null;
            this.pendingIceCandidates = [];
            this.hasInitiatedWebRTC = false;
        },

        cancelCompatibilityAudioFallback() {
            if (this.compatibilityFallbackTimer) {
                clearTimeout(this.compatibilityFallbackTimer);
                this.compatibilityFallbackTimer = null;
            }
        },

        scheduleCompatibilityAudioFallback() {
            this.cancelCompatibilityAudioFallback();

            if (
                this.callState !== 'connected' ||
                this.compatibilityAudioActive ||
                this.mediaConnectionState === 'connected' ||
                typeof window.MediaRecorder === 'undefined'
            ) {
                return;
            }

            this.compatibilityFallbackTimer = setTimeout(() => {
                if (
                    this.callState !== 'connected' ||
                    this.compatibilityAudioActive ||
                    this.mediaConnectionState === 'connected'
                ) {
                    return;
                }

                this.startCompatibilityAudioMode().catch((error) => {
                    console.error('Failed to start compatibility audio mode', error);
                });
            }, COMPATIBILITY_AUDIO_START_DELAY_MS);
        },

        async startCompatibilityAudioMode() {
            if (
                this.compatibilityAudioActive ||
                this.callState !== 'connected' ||
                typeof window.MediaRecorder === 'undefined'
            ) {
                return;
            }

            await this.getLocalStream();

            this.compatibilityAudioActive = true;
            this.compatibilityChunkSequence = 0;
            this.setMediaConnectionState('connecting', 'Switching to compatibility audio...');
            this.cancelCompatibilityAudioFallback();
            this.stopPeerConnection();
            const remoteAudioElement = this.getRemoteAudioElement();
            if (remoteAudioElement) {
                remoteAudioElement.pause();
                remoteAudioElement.srcObject = null;
            }
            this.startCompatibilityChunkLoop();
        },

        stopCompatibilityAudioTransport() {
            this.cancelCompatibilityAudioFallback();

            if (this.compatibilityRecorderStopTimer) {
                clearTimeout(this.compatibilityRecorderStopTimer);
                this.compatibilityRecorderStopTimer = null;
            }

            const recorder = this.compatibilityRecorder;
            this.compatibilityRecorder = null;
            this.compatibilityAudioActive = false;

            if (recorder && recorder.state !== 'inactive') {
                try {
                    recorder.stop();
                } catch (_) {}
            }
        },

        startCompatibilityChunkLoop() {
            if (!this.localStream || this.callState !== 'connected') {
                return;
            }

            const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                ? 'audio/webm;codecs=opus'
                : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '');

            const startSegment = () => {
                if (!this.compatibilityAudioActive || !this.localStream || this.callState !== 'connected') {
                    return;
                }

                const recorder = new MediaRecorder(this.localStream, mimeType ? { mimeType } : {});
                const parts = [];
                this.compatibilityRecorder = recorder;

                recorder.addEventListener('dataavailable', (event) => {
                    if (event.data && event.data.size > 0) {
                        parts.push(event.data);
                    }
                });

                recorder.addEventListener('stop', async () => {
                    if (this.compatibilityRecorder === recorder) {
                        this.compatibilityRecorder = null;
                    }

                    if (parts.length && this.callId && this.callState === 'connected') {
                        try {
                            const blob = new Blob(parts, { type: recorder.mimeType || mimeType || 'audio/webm' });
                            const chunk = await this.blobToBase64(blob);
                            this.compatibilityChunkSequence += 1;

                            await this.sendSignal('audio_chunk', {
                                chunk,
                                mime_type: recorder.mimeType || mimeType || 'audio/webm',
                                sequence: this.compatibilityChunkSequence,
                            });
                        } catch (error) {
                            console.error('Compatibility audio chunk send failed', error);
                        }
                    }

                    if (this.compatibilityAudioActive && this.callState === 'connected') {
                        startSegment();
                    }
                });

                recorder.start();
                this.compatibilityRecorderStopTimer = setTimeout(() => {
                    if (recorder.state !== 'inactive') {
                        try {
                            recorder.stop();
                        } catch (_) {}
                    }
                }, COMPATIBILITY_AUDIO_SEGMENT_MS);
            };

            startSegment();
        },

        handleCompatibilityAudioChunk(data) {
            if (this.callState !== 'connected' || !data?.chunk) {
                return;
            }

            this.cancelCompatibilityAudioFallback();
            this.compatibilityAudioActive = true;

            try {
                const blob = this.base64ToBlob(data.chunk, data.mime_type || 'audio/webm');
                this.compatibilityChunkQueue.push(blob);

                if (this.compatibilityChunkQueue.length > COMPATIBILITY_AUDIO_MAX_QUEUE) {
                    this.compatibilityChunkQueue = this.compatibilityChunkQueue.slice(-COMPATIBILITY_AUDIO_MAX_QUEUE);
                }

                this.playNextCompatibilityChunk();
            } catch (error) {
                console.error('Compatibility audio chunk decode failed', error);
            }
        },

        playNextCompatibilityChunk() {
            if (this.compatibilityAudioPlaying || this.compatibilityChunkQueue.length === 0) {
                return;
            }

            const audioElement = this.getCompatibilityAudioElement();
            if (!audioElement) {
                return;
            }

            const blob = this.compatibilityChunkQueue.shift();
            if (!blob) {
                return;
            }

            if (this.compatibilityCurrentChunkUrl) {
                URL.revokeObjectURL(this.compatibilityCurrentChunkUrl);
                this.compatibilityCurrentChunkUrl = null;
            }

            const cleanup = () => {
                if (this.compatibilityCurrentChunkUrl) {
                    URL.revokeObjectURL(this.compatibilityCurrentChunkUrl);
                    this.compatibilityCurrentChunkUrl = null;
                }

                audioElement.onended = null;
                audioElement.onerror = null;
                audioElement.removeAttribute('src');
                audioElement.load();
                this.compatibilityAudioPlaying = false;

                if (this.callState === 'connected') {
                    this.playNextCompatibilityChunk();
                }
            };

            this.compatibilityCurrentChunkUrl = URL.createObjectURL(blob);
            this.compatibilityAudioPlaying = true;
            audioElement.srcObject = null;
            audioElement.src = this.compatibilityCurrentChunkUrl;
            audioElement.currentTime = 0;
            audioElement.onended = cleanup;
            audioElement.onerror = cleanup;
            this.setMediaConnectionState('connected', '');
            this.ensureCompatibilityAudioPlayback();
        },

        resetCompatibilityAudioPlayback() {
            this.compatibilityChunkQueue = [];
            this.compatibilityAudioPlaying = false;
            this.compatibilityChunkSequence = 0;

            if (this.compatibilityCurrentChunkUrl) {
                URL.revokeObjectURL(this.compatibilityCurrentChunkUrl);
                this.compatibilityCurrentChunkUrl = null;
            }

            const audioElement = this.getCompatibilityAudioElement();
            if (audioElement) {
                audioElement.pause();
                audioElement.onended = null;
                audioElement.onerror = null;
                audioElement.removeAttribute('src');
                audioElement.load();
            }
        },

        // --- Call Control Actions ---

        async initiateCall(userId) {
            if (this.callState !== 'idle') return;

            try {
                await this.getLocalStream();

                const response = await axios.post('/calls/initiate', { callee_uuid: userId });
                this.callId = response.data.call_id;
                this.remoteUser = response.data.callee;
                this.changeState('calling');
                this.startCallStatusPoll();
                this.startSignalPoll();
            } catch (error) {
                console.error('Failed to initiate call', error);
                alert(error.response?.data?.error || 'Failed to initiate call');
                this.resetCall();
            }
        },

        async acceptCall() {
            try {
                await this.getLocalStream();
                await axios.post(`/calls/${this.callId}/accept`);
                this.changeState('connected');
                this.startCallStatusPoll();
                this.startSignalPoll();
                // Caller will initiate WebRTC offer upon receiving CallAccepted event
            } catch (error) {
                console.error('Failed to accept call', error);
                this.resetCall();
            }
        },

        async rejectCall(overrideCallId = null, isBusyFallback = false) {
            const idToReject = overrideCallId || this.callId;
            if (!idToReject) return;

            try {
                await axios.post(`/calls/${idToReject}/reject`);
                if (!isBusyFallback) {
                    this.resetCall();
                }
            } catch (error) {
                console.error('Failed to reject call', error);
            }
        },

        async cancelCall() {
            try {
                await axios.post(`/calls/${this.callId}/cancel`);
                this.resetCall();
            } catch (error) {
                console.error('Failed to cancel call', error);
            }
        },

        async endCall() {
            try {
                await axios.post(`/calls/${this.callId}/end`);
                this.changeState('ended');
                setTimeout(() => this.resetCall(), 2000);
            } catch (error) {
                console.error('Failed to end call', error);
                this.resetCall();
            }
        },

        startIncomingPoll() {
            this.stopIncomingPoll();
            this.checkIncomingCall();
            this.incomingPollInterval = setInterval(() => {
                this.checkIncomingCall();
            }, 1500);
        },

        stopIncomingPoll() {
            if (this.incomingPollInterval) {
                clearInterval(this.incomingPollInterval);
                this.incomingPollInterval = null;
            }
        },

        startCallStatusPoll() {
            this.stopCallStatusPoll();

            if (!this.callId) {
                return;
            }

            this.syncCallStatus();
            this.callStatusPollInterval = setInterval(() => {
                this.syncCallStatus();
            }, 1500);
        },

        stopCallStatusPoll() {
            if (this.callStatusPollInterval) {
                clearInterval(this.callStatusPollInterval);
                this.callStatusPollInterval = null;
            }
        },

        startSignalPoll() {
            this.stopSignalPoll();

            if (!this.callId) {
                return;
            }

            this.pollSignals();
            this.signalPollInterval = setInterval(() => {
                this.pollSignals();
            }, 1000);
        },

        stopSignalPoll() {
            if (this.signalPollInterval) {
                clearInterval(this.signalPollInterval);
                this.signalPollInterval = null;
            }
        },

        async checkIncomingCall() {
            if (this.callState !== 'idle') return;

            try {
                const response = await axios.get('/calls/incoming');
                const incomingCall = response.data;

                if (!incomingCall?.call_id) {
                    return;
                }

                this.callId = incomingCall.call_id;
                this.remoteUser = incomingCall.caller;
                this.changeState('incoming');
                this.startCallStatusPoll();
                this.startSignalPoll();
            } catch (error) {
                console.error('Incoming call fallback check failed', error);
            }
        },

        async syncCallStatus() {
            if (!this.callId || this.callState === 'idle') return;

            try {
                const response = await axios.get(`/calls/${this.callId}/status`);
                const status = response.data;

                if (status?.other_user) {
                    this.remoteUser = status.other_user;
                }

                if (status.status === 'ringing') {
                    return;
                }

                if (status.status === 'in_progress') {
                    this.changeState('connected');

                    if (status.is_caller) {
                        this.initiateWebRTCOnce();
                    }

                    return;
                }

                if (['completed', 'missed', 'cancelled', 'rejected'].includes(status.status)) {
                    this.changeState('ended');
                    setTimeout(() => this.resetCall(), 2000);
                }
            } catch (error) {
                console.error('Call status sync failed', error);
            }
        },

        async pollSignals() {
            if (!this.callId || this.callState === 'idle') return;

            try {
                const response = await axios.get(`/calls/${this.callId}/signals`);

                for (const signal of response.data || []) {
                    await this.handleWebRtcSignal(signal);
                }
            } catch (error) {
                console.error('Signal poll failed', error);
            }
        },

        async handleWebRtcSignal(signal) {
            if (this.callId !== signal.call_id) return;

            if (signal.signal_id && this.processedSignalIds.includes(signal.signal_id)) {
                return;
            }

            if (signal.signal_id) {
                this.processedSignalIds.push(signal.signal_id);
                if (this.processedSignalIds.length > 200) {
                    this.processedSignalIds = this.processedSignalIds.slice(-100);
                }
            }

            if (signal.type === 'audio_chunk') {
                if (!this.compatibilityAudioActive) {
                    try {
                        await this.startCompatibilityAudioMode();
                    } catch (error) {
                        console.error('Compatibility audio activation from remote chunk failed', error);
                    }
                }

                this.handleCompatibilityAudioChunk(signal.data);
                return;
            }

            if (this.compatibilityAudioActive) {
                return;
            }

            if (!this.peerConnection) {
                await this.setupPeerConnection();
            }

            if (signal.type === 'offer') {
                await this.peerConnection.setRemoteDescription(new RTCSessionDescription(signal.data));
                await this.flushPendingIceCandidates();
                const answer = await this.peerConnection.createAnswer();
                await this.peerConnection.setLocalDescription(answer);
                await this.waitForIceGatheringComplete();
                this.sendSignal('answer', this.serializeSessionDescription(this.peerConnection.localDescription));
            } else if (signal.type === 'answer') {
                await this.peerConnection.setRemoteDescription(new RTCSessionDescription(signal.data));
                await this.flushPendingIceCandidates();
            } else if (signal.type === 'candidate') {
                await this.addRemoteIceCandidate(signal.data);
            }
        },

        // --- WebRTC Logic ---

        async getLocalStream() {
            if (this.localStream) return;
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    sampleRate: 48000,
                    channelCount: 1,
                },
                video: false,
            });
        },

        async setupPeerConnection() {
            if (!this.localStream) {
                await this.getLocalStream();
            }

            this.peerConnection = new RTCPeerConnection(this.iceServers);
            this.remoteStream = this.remoteStream || new MediaStream();
            this.setMediaConnectionState('connecting', 'Connecting audio...');

            this.localStream.getTracks().forEach(track => {
                this.peerConnection.addTrack(track, this.localStream);
            });

            this.peerConnection.ontrack = (event) => {
                if (!this.remoteStream) {
                    this.remoteStream = new MediaStream();
                }

                if (event.track && !this.remoteStream.getTracks().some(track => track.id === event.track.id)) {
                    this.remoteStream.addTrack(event.track);
                }

                event.track.onunmute = () => {
                    this.attachRemoteStream();
                };

                this.attachRemoteStream();
            };

            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendSignal('candidate', this.serializeIceCandidate(event.candidate));
                }
            };

            // Reconnection logic
            this.peerConnection.oniceconnectionstatechange = () => {
                const state = this.peerConnection?.iceConnectionState;
                console.log('ICE connection state:', state);

                if (state === 'checking') {
                    this.setMediaConnectionState('connecting', 'Connecting audio...');
                } else if (state === 'disconnected') {
                    this.setMediaConnectionState('connecting', 'Reconnecting audio...');
                    this.startCompatibilityAudioMode().catch((error) => {
                        console.error('Compatibility audio activation after disconnect failed', error);
                    });
                    // Give it 3s to self-recover before attempting restart
                    setTimeout(() => {
                        if (this.peerConnection?.iceConnectionState === 'disconnected') {
                            this.attemptReconnect();
                        }
                    }, 3000);
                } else if (state === 'failed') {
                    this.setMediaConnectionState('failed', this.buildMediaFailureMessage());
                    this.startCompatibilityAudioMode().catch((error) => {
                        console.error('Compatibility audio activation after failure failed', error);
                    });
                    this.attemptReconnect();
                } else if (state === 'connected' || state === 'completed') {
                    this.reconnectAttempts = 0;
                    this.cancelCompatibilityAudioFallback();
                    this.setMediaConnectionState('connected', '');
                } else if (state === 'closed') {
                    this.setMediaConnectionState('idle', '');
                }
            };
        },

        async attemptReconnect() {
            if (!this.peerConnection || !this.callId || this.compatibilityAudioActive) return;

            if (this.reconnectAttempts >= this.maxReconnectAttempts) {
                console.warn('Max reconnect attempts reached, ending call');
                this.endCall();
                return;
            }

            this.reconnectAttempts++;
            console.log(`ICE restart attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts}`);

            try {
                const offer = await this.peerConnection.createOffer({ iceRestart: true });
                await this.peerConnection.setLocalDescription(offer);
                this.sendSignal('offer', offer);
            } catch (e) {
                console.error('ICE restart failed', e);
            }
        },

        async initiateWebRTC() {
            if (this.compatibilityAudioActive) {
                return;
            }

            await this.setupPeerConnection();
            const offer = await this.peerConnection.createOffer();
            await this.peerConnection.setLocalDescription(offer);
            await this.waitForIceGatheringComplete();
            this.sendSignal('offer', this.serializeSessionDescription(this.peerConnection.localDescription));
        },

        async initiateWebRTCOnce() {
            if (this.hasInitiatedWebRTC || this.compatibilityAudioActive) return;

            this.hasInitiatedWebRTC = true;

            try {
                await this.initiateWebRTC();
            } catch (error) {
                this.hasInitiatedWebRTC = false;
                console.error('Failed to start WebRTC offer', error);
            }
        },

        sendSignal(type, data) {
            return axios.post(`/calls/${this.callId}/signal`, {
                type: type,
                data: data
            }).catch(err => console.error('Signaling error:', err));
        },

        // --- UI & State Management ---

        changeState(newState) {
            if (this.callState === newState) {
                return;
            }

            this.callState = newState;

            this.ringtoneAudio.stop();
            this.outgoingRingtoneAudio.stop();

            if (newState === 'incoming') {
                this.setMediaConnectionState('idle', '');
                this.ringtoneAudio.play();
            } else if (newState === 'calling') {
                this.setMediaConnectionState('idle', '');
                this.outgoingRingtoneAudio.play();
            } else if (newState === 'connected') {
                if (this.mediaConnectionState === 'idle') {
                    this.setMediaConnectionState('connecting', 'Connecting audio...');
                }
                this.startTimer();
                this.ensureRemoteAudioPlayback();
                this.scheduleCompatibilityAudioFallback();
            } else if (newState === 'ended') {
                this.stopCompatibilityAudioTransport();
                this.setMediaConnectionState('idle', '');
                this.stopTimer();
            }
        },

        toggleMute() {
            if (this.localStream) {
                this.isMuted = !this.isMuted;
                this.localStream.getAudioTracks().forEach(track => {
                    track.enabled = !this.isMuted;
                });
            }
        },

        startTimer() {
            this.duration = 0;
            this.formatDuration();
            this.timerInterval = setInterval(() => {
                this.duration++;
                this.formatDuration();
            }, 1000);
        },

        stopTimer() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        },

        formatDuration() {
            const minutes = Math.floor(this.duration / 60);
            const seconds = this.duration % 60;
            this.durationFormatted = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        },

        resetCall() {
            this.changeState('idle');
            this.remoteUser = null;
            this.callId = null;
            this.isMuted = false;
            this.duration = 0;
            this.durationFormatted = '00:00';
            this.setMediaConnectionState('idle', '');
            this.reconnectAttempts = 0;
            this.hasInitiatedWebRTC = false;
            this.processedSignalIds = [];
            this.pendingIceCandidates = [];
            this.stopCompatibilityAudioTransport();
            this.resetCompatibilityAudioPlayback();
            this.stopCallStatusPoll();
            this.stopSignalPoll();
            this.unbindAudioPlaybackUnlock();
            this.stopPeerConnection();
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => track.stop());
                this.localStream = null;
            }
            this.remoteStream = null;

            const audioElement = this.getRemoteAudioElement();
            if (audioElement) {
                audioElement.pause();
                audioElement.srcObject = null;
            }
        }
    };
}
