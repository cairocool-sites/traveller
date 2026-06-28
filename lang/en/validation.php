<?php

return [
    'custom' => [
        'currency' => [
            'base_must_be_active' => 'The base currency must remain active.',
            'only_base_currency' => 'You cannot disable the only active base currency.',
        ],
        'exchange_rate' => [
            'distinct_pair' => 'Base and quote currencies must be different.',
            'positive_rate' => 'The exchange rate must be greater than zero.',
        ],
        'locale' => [
            'supported' => 'The locale must be Arabic or English.',
        ],
    ],
];
