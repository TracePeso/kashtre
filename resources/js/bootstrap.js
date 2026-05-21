/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const configuredReverbHost = import.meta.env.VITE_REVERB_HOST;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const localReverbHosts = ['localhost', '127.0.0.1', '0.0.0.0'];
const reverbHost = !configuredReverbHost
    ? window.location.hostname
    : (localReverbHosts.includes(configuredReverbHost) && !localReverbHosts.includes(window.location.hostname)
        ? window.location.hostname
        : configuredReverbHost);
const reverbAppKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbAppKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbAppKey,
        authEndpoint: '/reverb/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        },
        wsHost: reverbHost,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        withCredentials: true,
        enabledTransports: ['ws', 'wss'],
    });
} else {
    console.warn('Reverb disabled: missing VITE_REVERB_APP_KEY during frontend build.');
}
