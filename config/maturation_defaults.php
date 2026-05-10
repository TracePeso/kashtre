<?php

/** Fallback when `maturation_system_defaults` has no row for a payment method yet. */
return [
    'entity' => [
        'insurance' => 30,
        'credit_arrangement' => 7,
        'mobile_money' => 0,
        'v_card' => 3,
        'p_card' => 5,
        'bank_transfer' => 2,
        'cash' => 0,
    ],

    'service_charge' => [
        'insurance' => 30,
        'credit_arrangement' => 7,
        'mobile_money' => 0,
        'v_card' => 3,
        'p_card' => 5,
        'bank_transfer' => 2,
        'cash' => 0,
    ],
    
];
