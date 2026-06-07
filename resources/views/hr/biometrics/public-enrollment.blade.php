<x-guest-layout>
    @php
        $networkBlocked = ($networkAccess['enabled'] ?? false) && ! ($networkAccess['allowed'] ?? true);
        $geofenceSetupBlocked = ($geofenceAccess['enabled'] ?? false) && ! ($geofenceAccess['configured'] ?? false);
        $canConfirm = $enrollmentSession->isPendingCode();
        $canCapture = $enrollmentSession->isAuthorized();
        $enrollmentWindowDeadline = $enrollmentSession->capture_deadline_at?->toIso8601String();
        $isCompleted = $enrollmentSession->completed_at !== null;
        $isInactive = $enrollmentSession->invalidated_at !== null;
    @endphp

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-brand-blue">Biometric Enrollment</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $staffAssignment->staff_name }}</h1>
            <p class="mt-2 text-sm text-gray-600">
                Complete fingerprint and face capture on this phone after entering the 6-digit code sent to
                <span class="font-medium text-gray-900">{{ $enrollmentSession->recipient_email }}</span>.
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Please review the enrollment details.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($networkBlocked)
            <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">
                {{ $networkAccess['message'] }}
            </div>
        @endif

        @if ($geofenceSetupBlocked)
            <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">
                {{ $geofenceAccess['message'] }}
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Secure Device Authorization</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Enter the emailed code on the phone that will register the fingerprint and capture the face photo.
                    </p>
                </div>
                <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    <p class="font-medium text-gray-900">{{ ucfirst($enrollmentSession->purpose === 're-enrollment' ? 'Re-enrollment' : 'Enrollment') }}</p>
                    <p class="mt-1">Code expires at {{ optional($enrollmentSession->secret_code_expires_at)->format('M j, Y H:i') }}</p>
                </div>
            </div>

            <div class="mt-5 rounded-md border border-dashed border-gray-200 bg-gray-50 p-4">
                <p class="text-sm font-semibold text-gray-900">Authorized Staff Member</p>
                <p class="mt-1 text-sm text-gray-600">{{ $enrollmentSession->staff_name }}</p>
                <p class="mt-1 text-xs text-gray-500">Email recipient: {{ $enrollmentSession->recipient_email }}</p>
            </div>

            @if ($canConfirm)
                <form method="POST" action="{{ $confirmUrl }}" class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="secret_code">Secret Code</label>
                        <input id="secret_code" name="secret_code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue" placeholder="Enter 6-digit code">
                    </div>
                    <div class="md:self-end">
                        <p class="text-xs text-gray-500">Once the code is confirmed, the 2-minute capture window starts immediately.</p>
                    </div>
                    <div class="md:self-end">
                        <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                            Confirm Code
                        </button>
                    </div>
                </form>
            @elseif ($canCapture)
                <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-semibold">2-minute capture window</p>
                    <p class="mt-1 text-xs" data-enrollment-deadline="{{ $enrollmentWindowDeadline }}">
                        Ends at {{ $enrollmentSession->capture_deadline_at?->format('M j, Y H:i:s') }}
                    </p>
                    <p class="mt-1 text-xs font-medium" id="enrollment_window_status">Capture both face and fingerprint before the timer runs out.</p>
                </div>
            @elseif ($isCompleted)
                <div class="mt-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    This biometric enrollment has already been completed.
                </div>
            @elseif ($isInactive)
                <div class="mt-6 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
                    This biometric enrollment link is no longer active. Request a new code from HR to continue.
                </div>
            @endif
        </div>

        @if ($canCapture)
            <form method="POST" action="{{ $completeUrl }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm" data-biometric-form data-secure-enrollment-form>
                @csrf
                <input type="hidden" name="enrollment_session_uuid" value="{{ $enrollmentSession->uuid }}">
                <input type="hidden" name="staff_assignment_id" value="{{ $enrollmentSession->staff_assignment_id }}">
                <input type="hidden" name="fingerprint_credential" id="fingerprint_credential">
                <input type="hidden" name="face_descriptor" id="enroll_face_descriptor">
                <input type="hidden" name="face_sample" id="enroll_face_sample">
                <input type="hidden" name="face_photo" id="enroll_face_photo">
                <input type="hidden" name="quality_score" id="enroll_face_quality_score">
                <input type="hidden" name="face_protocol_version" id="enroll_face_protocol_version">
                <input type="hidden" name="face_liveness_passed" id="enroll_face_liveness_passed">
                <input type="hidden" name="face_liveness_challenge" id="enroll_face_liveness_challenge">
                <input type="hidden" name="face_sample_count" id="enroll_face_sample_count">
                <input type="hidden" name="face_detection_status" id="enroll_face_detection_status">
                <input type="hidden" name="face_quality_min" id="enroll_face_quality_min">
                <input type="hidden" name="face_quality_average" id="enroll_face_quality_average">
                <input type="hidden" name="capture_source" value="browser_camera">
                <input type="hidden" name="geo_latitude" data-geo-latitude>
                <input type="hidden" name="geo_longitude" data-geo-longitude>
                <input type="hidden" name="geo_accuracy" data-geo-accuracy>

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Fingerprint</h2>
                            <p class="mt-1 text-sm text-gray-500">Use the same phone to approve the fingerprint prompt and bind this device to your biometric profile.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="fingerprint_label">Label</label>
                                <input id="fingerprint_label" name="fingerprint_label" value="{{ old('fingerprint_label', 'Fingerprint') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="fingerprint_device_id">Device ID</label>
                                <input id="fingerprint_device_id" name="fingerprint_device_id" value="{{ old('fingerprint_device_id') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="fingerprint_threshold">Threshold</label>
                            <input id="fingerprint_threshold" name="fingerprint_verification_threshold" type="number" min="0" max="1" step="0.0001" value="{{ old('fingerprint_verification_threshold', '0.98') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                        </div>

                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <p class="text-sm font-semibold text-gray-900">Fingerprint registration</p>
                            <p class="mt-1 text-sm text-gray-500">The fingerprint prompt uses the secure biometric controls already available on this phone.</p>
                            <p id="fingerprint_status" class="mt-2 text-xs text-gray-500">No fingerprint registered in this session yet.</p>
                            <button type="button" data-mobile-fingerprint-register class="mt-3 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                Register Fingerprint
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Face Capture</h2>
                            <p class="mt-1 text-sm text-gray-500">Complete the guided live face capture on this same device before submitting the enrollment.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="face_label">Label</label>
                                <input id="face_label" name="face_label" value="{{ old('face_label', 'Primary face') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="face_threshold">Threshold</label>
                                <input id="face_threshold" name="face_verification_threshold" type="number" min="0" max="1" step="0.0001" value="{{ old('face_verification_threshold', '0.86') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="face_provider">Provider</label>
                                <input id="face_provider" name="face_provider" value="{{ old('face_provider', 'browser-camera') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="face_device_id">Device ID</label>
                                <input id="face_device_id" name="face_device_id" value="{{ old('face_device_id') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue">
                            </div>
                        </div>

                        <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                            <video data-face-video="enroll" class="h-56 w-full rounded-md bg-gray-900 object-cover" autoplay muted playsinline></video>
                            <canvas data-face-canvas="enroll" class="hidden"></canvas>
                            <img data-face-preview="enroll" alt="Captured face preview" class="mt-3 hidden h-40 w-40 rounded-md border border-gray-200 object-cover">
                            <p data-face-status="enroll" class="mt-2 text-xs text-gray-500">Camera not started.</p>
                            <p data-face-quality="enroll" class="mt-1 text-xs text-gray-500">Quality score and captured photo preview will appear after capture.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" data-face-start="enroll" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Start Camera
                                </button>
                                <button type="button" data-face-capture="enroll" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                    Capture Face
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 rounded-md border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm font-medium text-gray-900">Location check</p>
                    <p data-geofence-settings-status class="mt-1 text-xs text-gray-500">
                        The system will confirm your current location before it starts the fingerprint prompt or saves the enrollment.
                    </p>
                </div>

                <div class="mt-6">
                    <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                        Complete Secure Enrollment
                    </button>
                </div>
            </form>
        @endif
    </div>

    <script>
        (() => {
            const streams = {};
            const mobileFingerprintOptionsUrl = @json($mobileFingerprintOptionsUrl);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const geofenceEnabled = @json($geofenceAccess['enabled'] ?? false);
            const secureEnrollmentDeadline = document.querySelector('[data-enrollment-deadline]')?.dataset.enrollmentDeadline || null;

            function bufferToBase64Url(buffer) {
                const bytes = new Uint8Array(buffer);
                let binary = '';
                bytes.forEach((byte) => binary += String.fromCharCode(byte));
                return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
            }

            function base64UrlToBuffer(value) {
                const padded = value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4);
                const binary = atob(padded);
                const bytes = new Uint8Array(binary.length);

                for (let index = 0; index < binary.length; index++) {
                    bytes[index] = binary.charCodeAt(index);
                }

                return bytes.buffer;
            }

            function preparePublicKeyOptions(options) {
                options.challenge = base64UrlToBuffer(options.challenge);

                if (options.user?.id) {
                    options.user.id = base64UrlToBuffer(options.user.id);
                }

                if (Array.isArray(options.excludeCredentials)) {
                    options.excludeCredentials = options.excludeCredentials.map((credential) => ({
                        ...credential,
                        id: base64UrlToBuffer(credential.id),
                    }));
                }

                return options;
            }

            function setGeofenceSettingsStatus(message, ok = false) {
                const el = document.querySelector('[data-geofence-settings-status]');

                if (!el) {
                    return;
                }

                const lowerMessage = message.toLowerCase();
                const isError = lowerMessage.includes('failed')
                    || lowerMessage.includes('denied')
                    || lowerMessage.includes('not available')
                    || lowerMessage.includes('outside')
                    || lowerMessage.includes('require');

                el.textContent = message;
                el.classList.toggle('text-green-700', ok);
                el.classList.toggle('text-red-700', !ok && isError);
                el.classList.toggle('text-gray-500', !ok && !isError);
            }

            function browserPosition() {
                if (!navigator.geolocation) {
                    throw new Error('Location access is not available in this browser.');
                }

                return new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 12000,
                        maximumAge: 0,
                    });
                });
            }

            function fillGeolocationFields(form, position) {
                const latitude = position.coords.latitude.toFixed(7);
                const longitude = position.coords.longitude.toFixed(7);
                const accuracy = Math.round(position.coords.accuracy);

                form.querySelectorAll('[name="geo_latitude"]').forEach((input) => input.value = latitude);
                form.querySelectorAll('[name="geo_longitude"]').forEach((input) => input.value = longitude);
                form.querySelectorAll('[name="geo_accuracy"]').forEach((input) => input.value = accuracy);

                return { latitude, longitude, accuracy };
            }

            async function collectGeolocation(form) {
                if (!geofenceEnabled) {
                    return null;
                }

                setGeofenceSettingsStatus('Confirming current location...');
                const position = await browserPosition();
                const location = fillGeolocationFields(form, position);
                setGeofenceSettingsStatus(`Location captured with ${location.accuracy}m accuracy.`, true);

                return location;
            }

            async function mobileFingerprintOptions(form) {
                await collectGeolocation(form);

                const response = await fetch(mobileFingerprintOptionsUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        geo_latitude: form.querySelector('[name="geo_latitude"]')?.value || null,
                        geo_longitude: form.querySelector('[name="geo_longitude"]')?.value || null,
                        geo_accuracy: form.querySelector('[name="geo_accuracy"]')?.value || null,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                    throw new Error(firstError || 'The fingerprint prompt could not be started.');
                }

                return preparePublicKeyOptions(payload.publicKey);
            }

            function mobileFingerprintStatus(message, ok = false) {
                const el = document.getElementById('fingerprint_status');

                if (!el) {
                    return;
                }

                const lowerMessage = message.toLowerCase();
                const isError = lowerMessage.includes('failed')
                    || lowerMessage.includes('require')
                    || lowerMessage.includes('not allowed')
                    || lowerMessage.includes('location');

                el.textContent = message;
                el.classList.toggle('text-green-600', ok);
                el.classList.toggle('text-red-600', !ok && isError);
                el.classList.toggle('text-gray-500', !ok && !isError);
            }

            function updateEnrollmentWindowStatus() {
                const status = document.getElementById('enrollment_window_status');
                const secureForm = document.querySelector('[data-secure-enrollment-form]');

                if (!status || !secureEnrollmentDeadline) {
                    return;
                }

                const deadline = new Date(secureEnrollmentDeadline);
                const remainingMs = deadline.getTime() - Date.now();

                if (remainingMs <= 0) {
                    status.textContent = 'The 2-minute enrollment window expired. Request a new code from HR to restart.';
                    status.classList.add('text-red-700');
                    secureForm?.querySelectorAll('button, input, select').forEach((element) => {
                        if (element.name !== '_token') {
                            element.disabled = true;
                        }
                    });
                    return;
                }

                const remainingSeconds = Math.floor(remainingMs / 1000);
                const minutes = Math.floor(remainingSeconds / 60);
                const seconds = remainingSeconds % 60;
                status.textContent = `Time remaining: ${minutes}:${String(seconds).padStart(2, '0')}. Capture both face and fingerprint before it ends.`;
            }

            function credentialToJson(credential) {
                return {
                    id: credential.id,
                    type: credential.type,
                    rawId: bufferToBase64Url(credential.rawId),
                    response: {
                        clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                        attestationObject: credential.response.attestationObject
                            ? bufferToBase64Url(credential.response.attestationObject)
                            : undefined,
                        transports: typeof credential.response.getTransports === 'function'
                            ? credential.response.getTransports()
                            : [],
                    },
                };
            }

            function setStatus(target, message, ok = false) {
                const el = document.querySelector(`[data-face-status="${target}"]`);

                if (!el) {
                    return;
                }

                el.textContent = message;
                el.classList.toggle('text-green-600', ok);
                el.classList.toggle('text-gray-500', !ok);
            }

            async function startCamera(target) {
                const video = document.querySelector(`[data-face-video="${target}"]`);

                if (!video || !navigator.mediaDevices?.getUserMedia) {
                    setStatus(target, 'Camera access is not available in this browser.');
                    return null;
                }

                if (!streams[target]) {
                    streams[target] = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 720 } },
                        audio: false,
                    });
                }

                video.srcObject = streams[target];
                await video.play();
                setStatus(target, 'Camera ready.');

                return video;
            }

            const faceCaptureProtocolVersion = 'face-capture-v2';
            const faceChallenge = [
                { key: 'center', label: 'Step 1/3: look straight at the camera.', minCenter: 0.38, maxCenter: 0.62 },
                { key: 'left', label: 'Step 2/3: move your face slightly to the left.', minCenter: 0, maxCenter: 0.50 },
                { key: 'right', label: 'Step 3/3: move your face slightly to the right.', minCenter: 0.50, maxCenter: 1 },
            ];

            function delay(ms) {
                return new Promise((resolve) => window.setTimeout(resolve, ms));
            }

            async function faceCrop(sourceCanvas) {
                const fallbackSize = Math.min(sourceCanvas.width, sourceCanvas.height) * 0.72;
                let crop = {
                    x: (sourceCanvas.width - fallbackSize) / 2,
                    y: (sourceCanvas.height - fallbackSize) / 2,
                    width: fallbackSize,
                    height: fallbackSize,
                    detected: 'FaceDetector' in window ? false : null,
                };

                if ('FaceDetector' in window) {
                    try {
                        const detector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
                        const faces = await detector.detect(sourceCanvas);

                        if (faces.length > 0) {
                            const box = faces[0].boundingBox;
                            const pad = Math.max(box.width, box.height) * 0.18;
                            crop = {
                                x: Math.max(0, box.x - pad),
                                y: Math.max(0, box.y - pad),
                                width: Math.min(sourceCanvas.width, box.width + pad * 2),
                                height: Math.min(sourceCanvas.height, box.height + pad * 2),
                                detected: true,
                            };
                        }
                    } catch (error) {
                        // Keep the center crop fallback when native face detection is unavailable.
                    }
                }

                return crop;
            }

            function faceCenterRatio(sourceCanvas, crop) {
                return (crop.x + crop.width / 2) / Math.max(1, sourceCanvas.width);
            }

            function faceDetectionStatus(crop) {
                if (crop.detected === true) {
                    return 'detected';
                }

                if (crop.detected === false) {
                    return 'not_detected';
                }

                return 'unsupported';
            }

            function descriptorFromCanvas(sourceCanvas, crop) {
                const size = 16;
                const canvas = document.createElement('canvas');
                canvas.width = size;
                canvas.height = size;
                const context = canvas.getContext('2d', { willReadFrequently: true });
                context.drawImage(sourceCanvas, crop.x, crop.y, crop.width, crop.height, 0, 0, size, size);

                const pixels = context.getImageData(0, 0, size, size).data;
                const values = [];

                for (let index = 0; index < pixels.length; index += 4) {
                    values.push((pixels[index] * 0.299 + pixels[index + 1] * 0.587 + pixels[index + 2] * 0.114) / 255);
                }

                const mean = values.reduce((sum, value) => sum + value, 0) / values.length;
                const variance = values.reduce((sum, value) => sum + Math.pow(value - mean, 2), 0) / values.length;
                const deviation = Math.sqrt(variance) || 1;

                return values.map((value) => Number(((value - mean) / deviation).toFixed(6)));
            }

            function faceQualityFromCanvas(sourceCanvas, crop) {
                const size = 48;
                const canvas = document.createElement('canvas');
                canvas.width = size;
                canvas.height = size;
                const context = canvas.getContext('2d', { willReadFrequently: true });
                context.drawImage(sourceCanvas, crop.x, crop.y, crop.width, crop.height, 0, 0, size, size);

                const pixels = context.getImageData(0, 0, size, size).data;
                const values = [];

                for (let index = 0; index < pixels.length; index += 4) {
                    values.push(pixels[index] * 0.299 + pixels[index + 1] * 0.587 + pixels[index + 2] * 0.114);
                }

                const mean = values.reduce((sum, value) => sum + value, 0) / values.length;
                const variance = values.reduce((sum, value) => sum + Math.pow(value - mean, 2), 0) / values.length;
                const deviation = Math.sqrt(variance);
                let edgeTotal = 0;
                let edgeCount = 0;

                for (let y = 0; y < size; y++) {
                    for (let x = 0; x < size; x++) {
                        const current = values[y * size + x];

                        if (x + 1 < size) {
                            edgeTotal += Math.abs(current - values[y * size + x + 1]);
                            edgeCount++;
                        }

                        if (y + 1 < size) {
                            edgeTotal += Math.abs(current - values[(y + 1) * size + x]);
                            edgeCount++;
                        }
                    }
                }

                const brightnessScore = Math.max(0, 1 - Math.abs(mean - 135) / 105);
                const contrastScore = Math.min(1, deviation / 55);
                const sharpnessScore = Math.min(1, (edgeTotal / Math.max(1, edgeCount)) / 22);
                const coverageScore = Math.min(1, ((crop.width * crop.height) / (sourceCanvas.width * sourceCanvas.height)) * 2.2);
                const detectionScore = crop.detected === false ? 0.75 : 1;

                return Math.max(0, Math.min(100, Math.round(100 * detectionScore * (
                    brightnessScore * 0.30
                    + contrastScore * 0.25
                    + sharpnessScore * 0.25
                    + coverageScore * 0.20
                ))));
            }

            function setFaceQuality(target, score, minimum = 70) {
                const el = document.querySelector(`[data-face-quality="${target}"]`);

                if (!el) {
                    return;
                }

                const passed = score >= minimum;
                el.textContent = passed
                    ? `Quality score: ${score}/100. Capture accepted.`
                    : `Quality score: ${score}/100. Retake with better light and a steadier camera.`;
                el.classList.toggle('text-green-600', passed);
                el.classList.toggle('text-red-600', !passed);
                el.classList.toggle('text-gray-500', false);
            }

            async function captureFaceSample(target, video, step) {
                const frame = document.querySelector(`[data-face-canvas="${target}"]`);
                frame.width = video.videoWidth;
                frame.height = video.videoHeight;
                frame.getContext('2d').drawImage(video, 0, 0, frame.width, frame.height);

                const crop = await faceCrop(frame);
                const detectionStatus = faceDetectionStatus(crop);

                if (detectionStatus === 'not_detected') {
                    throw new Error('No face was detected. Reposition and try again.');
                }

                const centerRatio = faceCenterRatio(frame, crop);

                if (detectionStatus === 'detected' && (centerRatio < step.minCenter || centerRatio > step.maxCenter)) {
                    throw new Error('Face position did not match the live challenge. Try again.');
                }

                return {
                    descriptor: descriptorFromCanvas(frame, crop),
                    qualityScore: faceQualityFromCanvas(frame, crop),
                    detectionStatus,
                    challenge: step.key,
                    centerRatio: Number(centerRatio.toFixed(4)),
                    photoDataUrl: facePhotoDataUrl(frame, crop),
                    capturedAt: new Date().toISOString(),
                };
            }

            function averagedDescriptor(samples) {
                const length = samples[0]?.descriptor?.length || 0;
                const average = [];

                for (let index = 0; index < length; index++) {
                    const sum = samples.reduce((total, sample) => total + sample.descriptor[index], 0);
                    average.push(Number((sum / samples.length).toFixed(6)));
                }

                return average;
            }

            function fillFaceInput(target, suffix, value) {
                const input = document.getElementById(`${target}_${suffix}`);

                if (input) {
                    input.value = value;
                }
            }

            function setFacePreview(target, dataUrl) {
                const preview = document.querySelector(`[data-face-preview="${target}"]`);

                if (!preview) {
                    return;
                }

                if (dataUrl) {
                    preview.src = dataUrl;
                    preview.classList.remove('hidden');
                    return;
                }

                preview.removeAttribute('src');
                preview.classList.add('hidden');
            }

            function resetFaceInputs(target) {
                [
                    'face_descriptor',
                    'face_sample',
                    'face_photo',
                    'face_quality_score',
                    'face_protocol_version',
                    'face_liveness_passed',
                    'face_liveness_challenge',
                    'face_sample_count',
                    'face_detection_status',
                    'face_quality_min',
                    'face_quality_average',
                ].forEach((suffix) => fillFaceInput(target, suffix, ''));

                setFacePreview(target, null);
            }

            function facePhotoDataUrl(frame, crop) {
                const output = document.createElement('canvas');
                output.width = Math.max(1, Math.round(crop.width));
                output.height = Math.max(1, Math.round(crop.height));
                output.getContext('2d').drawImage(
                    frame,
                    crop.x,
                    crop.y,
                    crop.width,
                    crop.height,
                    0,
                    0,
                    output.width,
                    output.height
                );

                return output.toDataURL('image/jpeg', 0.92);
            }

            function storeFaceCapture(target, samples) {
                const descriptor = averagedDescriptor(samples);
                const qualityScores = samples.map((sample) => sample.qualityScore);
                const qualityMin = Math.min(...qualityScores);
                const qualityAverage = Math.round(qualityScores.reduce((sum, score) => sum + score, 0) / qualityScores.length);
                const detectionStatuses = [...new Set(samples.map((sample) => sample.detectionStatus))];
                const detectionStatus = detectionStatuses.includes('detected')
                    ? 'detected'
                    : detectionStatuses[0] || 'unsupported';
                const protocolSample = {
                    protocol: faceCaptureProtocolVersion,
                    liveness_passed: true,
                    challenge: samples.map((sample) => ({
                        step: sample.challenge,
                        quality: sample.qualityScore,
                        detection: sample.detectionStatus,
                        center: sample.centerRatio,
                        captured_at: sample.capturedAt,
                    })),
                };
                const previewPhoto = samples[samples.length - 1]?.photoDataUrl || '';

                fillFaceInput(target, 'face_descriptor', JSON.stringify(descriptor));
                fillFaceInput(target, 'face_sample', JSON.stringify(protocolSample));
                fillFaceInput(target, 'face_photo', previewPhoto);
                fillFaceInput(target, 'face_quality_score', qualityAverage);
                fillFaceInput(target, 'face_protocol_version', faceCaptureProtocolVersion);
                fillFaceInput(target, 'face_liveness_passed', '1');
                fillFaceInput(target, 'face_liveness_challenge', samples.map((sample) => sample.challenge).join(','));
                fillFaceInput(target, 'face_sample_count', samples.length);
                fillFaceInput(target, 'face_detection_status', detectionStatus);
                fillFaceInput(target, 'face_quality_min', qualityMin);
                fillFaceInput(target, 'face_quality_average', qualityAverage);
                setFacePreview(target, previewPhoto);

                return { qualityAverage };
            }

            async function captureFace(target) {
                resetFaceInputs(target);
                const video = await startCamera(target);

                if (!video || !video.videoWidth || !video.videoHeight) {
                    setStatus(target, 'Camera is still warming up. Try capture again.');
                    return;
                }

                const samples = [];

                for (const step of faceChallenge) {
                    setStatus(target, step.label);
                    await delay(900);

                    const sample = await captureFaceSample(target, video, step);

                    if (sample.qualityScore < 70) {
                        setFaceQuality(target, sample.qualityScore, 70);
                        throw new Error('Face quality was below the required level. Retake with better light and a steadier camera.');
                    }

                    samples.push(sample);
                    setFaceQuality(target, sample.qualityScore, 70);
                }

                const result = storeFaceCapture(target, samples);
                setFaceQuality(target, result.qualityAverage, 70);
                setStatus(target, 'Live face capture accepted.', true);
            }

            document.querySelectorAll('[data-face-start]').forEach((button) => {
                button.addEventListener('click', async () => {
                    try {
                        await startCamera(button.dataset.faceStart);
                    } catch (error) {
                        setStatus(button.dataset.faceStart, 'Camera permission was denied or unavailable.');
                    }
                });
            });

            document.querySelectorAll('[data-face-capture]').forEach((button) => {
                button.addEventListener('click', async () => {
                    try {
                        await captureFace(button.dataset.faceCapture);
                    } catch (error) {
                        resetFaceInputs(button.dataset.faceCapture);
                        setStatus(button.dataset.faceCapture, error.message || 'Face capture failed. Check camera permissions.');
                    }
                });
            });

            document.querySelectorAll('[data-biometric-form]').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    if (!geofenceEnabled || form.dataset.geolocationReady === '1') {
                        return;
                    }

                    event.preventDefault();

                    try {
                        await collectGeolocation(form);
                        form.dataset.geolocationReady = '1';
                        form.requestSubmit();
                    } catch (error) {
                        setGeofenceSettingsStatus(error.message || 'Location capture failed.');
                        mobileFingerprintStatus(error.message || 'Location capture failed.');
                    }
                });
            });

            document.querySelector('[data-mobile-fingerprint-register]')?.addEventListener('click', async (event) => {
                const form = event.currentTarget.closest('form');

                if (!window.PublicKeyCredential || !navigator.credentials?.create) {
                    mobileFingerprintStatus('Fingerprint registration is not available in this browser.');
                    return;
                }

                try {
                    mobileFingerprintStatus('Waiting for the fingerprint prompt...');
                    const publicKey = await mobileFingerprintOptions(form);
                    const credential = await navigator.credentials.create({ publicKey });
                    document.getElementById('fingerprint_credential').value = JSON.stringify(credentialToJson(credential));
                    form.dataset.geolocationReady = '1';
                    mobileFingerprintStatus('Fingerprint captured. Save the profile to finish.', true);
                } catch (error) {
                    mobileFingerprintStatus(error.message || 'Fingerprint registration failed.');
                }
            });

            if (secureEnrollmentDeadline) {
                updateEnrollmentWindowStatus();
                window.setInterval(updateEnrollmentWindowStatus, 1000);
            }
        })();
    </script>
</x-guest-layout>
