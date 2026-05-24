@props([
    'enabled' => false,
    'activeAlert' => null,
    'displayDuration' => 0,
    'flashOn' => 3,
    'flashOff' => 1,
    'statusUrl' => null,
    'poll' => true,
    'pollInterval' => 5000,
])

@if($enabled)
    @php
        $initialAlert = $activeAlert ? [
            'id' => $activeAlert->id,
            'color' => $activeAlert->color ?? 'red',
            'activated_at' => ($activeAlert->activated_at ?? $activeAlert->triggered_at)?->timestamp,
            'display_duration' => (int) $displayDuration,
            'flash_on' => (int) $flashOn,
            'flash_off' => (int) $flashOff,
        ] : null;
    @endphp

    <div
        id="emergency-ambient"
        style="position:fixed; inset:0; z-index:20; pointer-events:none; opacity:0; transition:opacity 220ms ease, background 220ms ease, box-shadow 220ms ease;"
        aria-hidden="true"
    ></div>

    <script>
    (function () {
        if (!window.KashtreEmergencyAmbient) {
            window.KashtreEmergencyAmbient = (function () {
                var el = null;
                var dismissTimer = null;
                var pollTimer = null;
                var activeEmergencyId = null;
                var flashStyleEl = null;

                var EM_COLOR_RGB_MAP = {
                    red: '220, 38, 38',
                    green: '22, 163, 74',
                    yellow: '202, 138, 4',
                    amber: '217, 119, 6',
                    blue: '37, 99, 235'
                };

                function clearDismissTimer() {
                    if (dismissTimer) {
                        clearTimeout(dismissTimer);
                        dismissTimer = null;
                    }
                }

                function stopFlash() {
                    if (!el) return;
                    el.style.animation = 'none';
                    el.style.opacity = '1';
                }

                function startFlash(onSeconds, offSeconds) {
                    if (!el) return;

                    var onMs = Math.max(1, Number(onSeconds || 3)) * 1000;
                    var offMs = Math.max(0, Number(offSeconds || 1)) * 1000;

                    if (offMs === 0) {
                        stopFlash();
                        return;
                    }

                    var total = onMs + offMs;
                    var onPct = ((onMs / total) * 100).toFixed(3);

                    if (!flashStyleEl) {
                        flashStyleEl = document.createElement('style');
                        flashStyleEl.id = 'emergency-ambient-flash-style';
                        document.head.appendChild(flashStyleEl);
                    }

                    flashStyleEl.textContent =
                        '@keyframes emergency-ambient-flash {' +
                        '0% { opacity: 1; }' +
                        onPct + '% { opacity: 1; }' +
                        onPct + '% { opacity: 0; }' +
                        '100% { opacity: 0; }' +
                        '}';

                    el.style.animation = 'emergency-ambient-flash ' + total + 'ms linear infinite';
                }

                function renderAmbient(color) {
                    if (!el) return;
                    var rgb = EM_COLOR_RGB_MAP[color] || EM_COLOR_RGB_MAP.red;
                    el.style.background =
                        'linear-gradient(180deg, rgba(' + rgb + ',0.28) 0%, rgba(' + rgb + ',0.18) 24%, rgba(' + rgb + ',0.10) 58%, rgba(' + rgb + ',0.06) 100%)';
                    el.style.boxShadow =
                        'inset 0 0 0 1px rgba(' + rgb + ',0.14), inset 0 0 220px rgba(' + rgb + ',0.12)';
                    el.style.opacity = '1';
                }

                function hide() {
                    if (!el) return;
                    clearDismissTimer();
                    stopFlash();
                    el.style.opacity = '0';
                }

                function show(color, activatedAt, displayDuration, id, flashOn, flashOff) {
                    if (!el) return;
                    activeEmergencyId = id ?? activeEmergencyId;
                    renderAmbient(color);
                    clearDismissTimer();
                    startFlash(flashOn, flashOff);

                    if (displayDuration > 0 && activatedAt) {
                        var elapsed = Math.floor(Date.now() / 1000) - Number(activatedAt);
                        var remaining = (Number(displayDuration) - elapsed) * 1000;
                        if (remaining <= 0) {
                            activeEmergencyId = null;
                            hide();
                            return;
                        }
                        dismissTimer = setTimeout(function () {
                            activeEmergencyId = null;
                            hide();
                        }, remaining);
                    }
                }

                function sync(data) {
                    if (data && data.active) {
                        if (data.id !== activeEmergencyId || !el || el.style.opacity !== '1') {
                            show(
                                data.color,
                                data.activated_at ?? data.triggered_at,
                                data.display_duration,
                                data.id,
                                data.flash_on,
                                data.flash_off
                            );
                        }
                        return;
                    }

                    activeEmergencyId = null;
                    hide();
                }

                function pollOnce(statusUrl) {
                    if (!statusUrl) return;
                    fetch(statusUrl, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function (response) {
                        return response.json();
                    }).then(function (data) {
                        sync(data);
                    }).catch(function () {});
                }

                function mount(config) {
                    el = document.getElementById(config.elementId || 'emergency-ambient');
                    if (!el) return;

                    clearDismissTimer();
                    if (pollTimer) {
                        clearInterval(pollTimer);
                        pollTimer = null;
                    }

                    activeEmergencyId = null;
                    hide();

                    if (config.initialAlert) {
                        show(
                            config.initialAlert.color,
                            config.initialAlert.activated_at,
                            config.initialAlert.display_duration,
                            config.initialAlert.id,
                            config.initialAlert.flash_on,
                            config.initialAlert.flash_off
                        );
                    }

                    if (config.autoPoll && config.statusUrl) {
                        pollTimer = setInterval(function () {
                            pollOnce(config.statusUrl);
                        }, config.pollInterval || 5000);
                    }
                }

                return {
                    mount: mount,
                    show: show,
                    hide: hide,
                    sync: sync,
                    pollOnce: pollOnce,
                };
            })();
        }

        window.KashtreEmergencyAmbient.mount({
            elementId: 'emergency-ambient',
            statusUrl: @json($statusUrl ?? route('emergency.status')),
            autoPoll: {{ $poll ? 'true' : 'false' }},
            pollInterval: {{ (int) $pollInterval }},
            initialAlert: @json($initialAlert),
        });
    })();
    </script>
@endif
