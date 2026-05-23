<?php

return [
    'two_factor' => [
        'enabled' => env('TWO_FACTOR_ENABLED', false),
        'required' => env('REQUIRE_TWO_FACTOR', false),
    ],
];
