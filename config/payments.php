<?php

return [
    'yo_username' => env('YO_PAYMENTS_USERNAME'),
    'yo_password' => env('YO_PAYMENTS_PASSWORD'),
    'webhook_url' => env('YO_PAYMENTS_WEBHOOK_URL', env('APP_URL') . '/api/webhooks/yo-payments'),

    // Real Yo ac_deposit_funds (handset prompts). Default false = simulate on all non-local envs until enabled.
    'yo_live_deposits_enabled' => filter_var(env('YO_LIVE_DEPOSITS_ENABLED', false), FILTER_VALIDATE_BOOL),
];

