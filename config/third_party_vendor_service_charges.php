<?php

/**
 * Default tier templates for global third-party vendor service charges (per clinic business).
 * Bounds are invoice amounts in local currency (same semantics as entity service charges).
 * Leave upper_bound null on the last tier for “no upper limit”.
 */
return [
    'default_tiers' => [
        [
            'lower_bound' => 0,
            'upper_bound' => 100000,
            'amount' => 2,
            'type' => 'percentage',
        ],
        [
            'lower_bound' => 100000,
            'upper_bound' => 500000,
            'amount' => 1.5,
            'type' => 'percentage',
        ],
        [
            'lower_bound' => 500000,
            'upper_bound' => null,
            'amount' => 1,
            'type' => 'percentage',
        ],
    ],
];
