<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
    <meta name="user-id" content="{{ Auth::id() }}">
    <meta name="business-id" content="{{ Auth::user()->business_id }}">
    <meta name="user-p2p-ringtone" content="{{ Auth::user()->p2p_ringtone ?? 'default' }}">
    @endauth

    <title>{{ config('app.name', 'Kashtre') }}</title>
    <title>{{ env('APP_NAME', 'Kashtre') }} – Smart Payments and Collections Platform</title>
    <meta name="title" content="Kashtre – Smart Payments and Collections Platform">
    <meta name="description"
        content="Kashtre is a powerful platform for managing digital transactions, collections, and payouts with ease. Trusted by businesses across Africa.">
    <meta name="keywords"
        content="Kashtre, payments, digital wallet, collections, payouts, mobile money, financial platform, business payments, bulk payments, Uganda fintech">
    <meta name="author" content="Kashtre Ltd">
    <meta name="robots" content="index, follow">
    <meta name="language" content="en">
    <meta name="theme-color" content="#011478" />
    <meta property="og:title" content="Kashtre – Smart Payments and Collections Platform" />
    <meta property="og:description"
        content="Kashtre enables businesses to send and receive payments securely through mobile money and bank integrations." />
    <meta property="og:image" content="{{ asset('images/logo.png') }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Kashtre – Smart Payments and Collections Platform">
    <meta name="twitter:description"
        content="Kashtre simplifies business payments and collections for growing organizations.">
    <meta name="twitter:image" content="{{ asset('images/logo.png') }}">


    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @filamentStyles
    <!-- Styles -->
    @livewireStyles


</head>

@php
    $showEmergencyAmbient = $callingModuleEnabled
        && !request()->routeIs('calling.*')
        && !request()->routeIs('pa.console');
@endphp

<body class="font-inter antialiased bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400"
    :class="{ 'sidebar-expanded': sidebarExpanded }" x-data="{ sidebarOpen: false, sidebarExpanded: localStorage.getItem('sidebar-expanded') == null ? true : localStorage.getItem('sidebar-expanded') == 'true' }" x-init="$watch('sidebarExpanded', value => localStorage.setItem('sidebar-expanded', value))">

    <script>
        const expanded = localStorage.getItem('sidebar-expanded');
        if (expanded == null) {
            localStorage.setItem('sidebar-expanded', 'true');
            document.querySelector('body').classList.add('sidebar-expanded');
        } else if (expanded === 'true') {
            document.querySelector('body').classList.add('sidebar-expanded');
        } else {
            document.querySelector('body').classList.remove('sidebar-expanded');
        }
    </script>

    <x-app.emergency-ambient
        :enabled="$showEmergencyAmbient"
        :active-alert="$activeEmergencyAlert"
        :display-duration="$callingModuleConfig->emergency_display_duration ?? 0"
        :flash-on="$callingModuleConfig->emergency_flash_on ?? 3"
        :flash-off="$callingModuleConfig->emergency_flash_off ?? 1"
        :poll="false"
    />

    <!-- Page wrapper with global WebRTC Calling State -->
    <div x-data="callingSystem" @initiate-call.window="initiateCall($event.detail.uuid)" class="flex h-[100dvh] overflow-hidden">

        <!-- P2P Call Overlays -->
        <x-calling.incoming-call-modal />
        <x-calling.outgoing-call-modal />
        <x-calling.active-call-overlay />


        <x-app.sidebar :variant="$attributes['sidebarVariant']" />

        <!-- Content area -->
        <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden @if ($attributes['background']) {{ $attributes['background'] }} @endif"
            x-ref="contentarea">

            <x-app.header :variant="$attributes['headerVariant']" />

            <main class="grow">
                {{ $slot }}
            </main>
            <!-- Footer -->
            <footer class="w-full bg-gray-100 text-gray-600 py-2 border-t border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <p class="text-sm text-gray-500">© Copyright {{ date('Y') }} Kashtre. All Rights Reserved</p>
                    <p class="text-sm text-gray-500">Kashtre is a product of Kashtre Ltd</p>
                </div>
            </footer>

        </div>

    </div>
    @livewire('notifications')
    @filamentScripts
    @livewireScriptConfig
</body>

<div class="w-full bg-black text-white text-sm overflow-hidden fixed top-0 z-50">

</div>

@if($callingModuleEnabled)
<script>
(function () {
    let globalEmergencyActive = {{ $globalActiveEmergency ? 'true' : 'false' }};

    function fireGlobalEmergency(message, displayMessage, buttonIndex) {
        fetch('{{ route('emergency.trigger.global') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ message: message || '', display_message: displayMessage || '', button_index: buttonIndex || 1 })
        }).then(async r => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok || !data.success) {
                throw new Error(data.error || 'Failed to trigger emergency alert.');
            }

            globalEmergencyActive = true;
            pollEmergencyStatus();

            if (window.Swal) {
                Swal.fire({
                    icon: 'success',
                    title: 'Emergency Started',
                    text: data.message || 'Emergency alert sent successfully.',
                    timer: 2200,
                    timerProgressBar: true,
                    showConfirmButton: false
                });
            }

            return data;
        }).catch(error => {
            if (window.Swal) {
                Swal.fire('Error', error.message || 'Failed to trigger emergency alert.', 'error');
            }
        });
    }

    window.fireEmergencyButton = function(message, displayMessage, buttonIndex) {
        fireGlobalEmergency(message, displayMessage, buttonIndex);
    };

    function resolveGlobalEmergency() {
        fetch('{{ route('emergency.resolve.global') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({})
        }).then(async r => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok || !data.success) {
                throw new Error(data.error || 'Failed to clear emergency alert.');
            }

            globalEmergencyActive = false;
            activeEmergencyId = null;
            hideEmBanner();

            return data;
        }).catch(error => {
            if (window.Swal) {
                Swal.fire('Error', error.message || 'Failed to clear emergency alert.', 'error');
            }
        });
    }

    @if($callingModuleConfig)
    @php
        $eb1Msg  = $callingModuleConfig->emergency_button_1_message ?? '';
        $eb1Disp = $callingModuleConfig->emergency_button_1_display_message ?? '';
        $eb1Name = $callingModuleConfig->emergency_button_1_name ?? '';
        $eb2Msg  = $callingModuleConfig->emergency_button_2_message ?? '';
        $eb2Disp = $callingModuleConfig->emergency_button_2_display_message ?? '';
        $eb2Name = $callingModuleConfig->emergency_button_2_name ?? '';
    @endphp
    var emergencyBtn1 = @json(['name' => $eb1Name, 'message' => $eb1Msg, 'display_message' => $eb1Disp]);
    var emergencyBtn2 = @json(['name' => $eb2Name, 'message' => $eb2Msg, 'display_message' => $eb2Disp]);
    @endif

    // Per-key cooldown (ms) — prevents accidental re-trigger or accidental resolve on rapid presses.
    // F9 resolve is also gated: must wait the full cooldown after the last trigger before it resolves.
    var EMERGENCY_KEY_COOLDOWN = {{ ($callingModuleConfig->emergency_key_cooldown ?? 60) * 1000 }};
    var emergencyKeyLastFired = {};

    function emergencyKeyCooledDown(key) {
        var last = emergencyKeyLastFired[key] || 0;
        return (Date.now() - last) >= EMERGENCY_KEY_COOLDOWN;
    }
    function markEmergencyKeyFired(key) {
        emergencyKeyLastFired[key] = Date.now();
    }

    // ── Emergency overlay ──────────────────────────────────────
    var emBanner      = document.getElementById('emergency-banner');
    var emBannerInner = document.getElementById('emergency-banner-inner');
    var emBannerText  = document.getElementById('emergency-banner-text');
    var emDismissTimer  = null;
    var emFlashTimer    = null;
    var activeEmergencyId = null;

    var EM_COLOR_MAP = {
        red:    '#dc2626',
        green:  '#16a34a',
        yellow: '#ca8a04',
        amber:  '#d97706',
        blue:   '#2563eb'
    };

    function startEmFlash(onMs, offMs) {
        stopEmFlash();
        if (!emBannerInner) return;
        var total  = onMs + offMs;
        var onPct  = ((onMs / total) * 100).toFixed(3);
        var styleEl = document.getElementById('em-flash-style');
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = 'em-flash-style';
            document.head.appendChild(styleEl);
        }
        styleEl.textContent =
            '@keyframes em-flash-dyn {' +
            '  0% { opacity: 1; }' +
            '  ' + onPct + '% { opacity: 1; }' +
            '  ' + onPct + '% { opacity: 0; }' +
            '  100% { opacity: 0; }' +
            '}';
        emBannerInner.style.animation = 'em-flash-dyn ' + total + 'ms linear infinite';
    }

    function stopEmFlash() {
        if (emBannerInner) {
            emBannerInner.style.animation = 'none';
            emBannerInner.style.opacity   = '1';
        }
    }

    function showEmBanner(message, activatedAt, displayDuration, color, flashOn, flashOff) {
        if (window.KashtreEmergencyAmbient) {
            window.KashtreEmergencyAmbient.show(color, activatedAt, displayDuration, activeEmergencyId);
        }
        if (!emBanner) return;
        var bg   = EM_COLOR_MAP[color] || '#dc2626';
        var onMs  = (flashOn  || 3) * 1000;
        var offMs = (flashOff || 1) * 1000;
        emBannerText.textContent = message;
        if (emBannerInner) emBannerInner.style.background = bg;
        emBanner.style.display = 'flex';
        startEmFlash(onMs, offMs);

        // No client-side dismiss timer — the banner stays on until the
        // backend resolves the alert (poll returns active = false).
        clearTimeout(emDismissTimer);
    }

    function hideEmBanner() {
        if (window.KashtreEmergencyAmbient) {
            window.KashtreEmergencyAmbient.hide();
        }
        if (!emBanner) return;
        stopEmFlash();
        emBanner.style.display = 'none';
        clearTimeout(emDismissTimer);
    }

    function pollEmergencyStatus() {
        fetch('{{ route('emergency.status') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(r => r.json()).then(data => {
            globalEmergencyActive = data.active;
            if (data.active) {
                if (data.id !== activeEmergencyId) {
                    activeEmergencyId = data.id;
                    showEmBanner(data.message, data.activated_at ?? data.triggered_at, data.display_duration, data.color, data.flash_on, data.flash_off);
                }
            } else {
                activeEmergencyId = null;
                hideEmBanner();
            }
        }).catch(() => {});
    }

    // Seed overlay from server-side state on load
    @if($activeEmergencyAlert)
    activeEmergencyId = {{ $activeEmergencyAlert->id }};
    showEmBanner(
        @json($activeEmergencyAlert->display_message ?: $activeEmergencyAlert->message),
        {{ ($activeEmergencyAlert->activated_at ?? $activeEmergencyAlert->triggered_at)->timestamp }},
        {{ $callingModuleConfig->emergency_display_duration ?? 0 }},
        @json($activeEmergencyAlert->color ?? 'red'),
        {{ $callingModuleConfig->emergency_flash_on  ?? 3 }},
        {{ $callingModuleConfig->emergency_flash_off ?? 1 }}
    );
    @endif

    setInterval(pollEmergencyStatus, 5000);
    // ─────────────────────────────────────────────────────────

    document.addEventListener('keydown', function (e) {
        if (e.key === 'F9') {
            e.preventDefault();
            if (!emergencyKeyCooledDown('F9')) return; // ignore rapid presses
            markEmergencyKeyFired('F9');
            fireGlobalEmergency();
        }
        @if($callingModuleConfig)
        if (e.key === 'F10' && emergencyBtn1.name && emergencyBtn1.message) {
            e.preventDefault();
            if (!emergencyKeyCooledDown('F10')) return;
            markEmergencyKeyFired('F10');
            fireGlobalEmergency(emergencyBtn1.message, emergencyBtn1.display_message, 1);
        }
        if (e.key === 'F11' && emergencyBtn2.name && emergencyBtn2.message) {
            e.preventDefault();
            if (!emergencyKeyCooledDown('F11')) return;
            markEmergencyKeyFired('F11');
            fireGlobalEmergency(emergencyBtn2.message, emergencyBtn2.display_message, 2);
        }
        @endif
    });
})();
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script type="text/javascript">
    {{-- Success Message --}}
    @if (Session::has('success'))
        Swal.fire({
            icon: 'success',
            title: 'Done',
            text: '{{ Session::get('success') }}',
            confirmButtonColor: "#3a57e8"
        });
    @endif
    {{-- Warning Message -- Show in all environments --}}
    @if (Session::has('warning'))
        Swal.fire({
            icon: 'warning',
            title: 'Security Required',
            text: '{{ Session::get('warning') }}',
            confirmButtonText: 'Setup 2FA',
            confirmButtonColor: "#f59e0b",
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCancelButton: false
        });
    @endif
    {{-- Errors Message --}}
    @if (Session::has('error'))
        Swal.fire({
            icon: 'error',
            title: 'Opps!!!',
            text: '{{ Session::get('error') }}',
            confirmButtonColor: "#3a57e8"
        });
    @endif
    @if (Session::has('errors') || (isset($errors) && is_array($errors) && $errors->any()))
        Swal.fire({
            icon: 'error',
            title: 'Opps!!!',
            text: '{{ Session::get('errors')->first() }}',
            confirmButtonColor: "#3a57e8"
        });
    @endif
</script>

</html>
