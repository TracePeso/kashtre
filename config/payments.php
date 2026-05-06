<?php

return [
    'yo_username' => env('YO_PAYMENTS_USERNAME'),
    'yo_password' => env('YO_PAYMENTS_PASSWORD'),
    'webhook_url' => env('YO_PAYMENTS_WEBHOOK_URL', env('APP_URL') . '/api/webhooks/yo-payments'),
];

