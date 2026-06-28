<?php

return [
    'custom' => [
        'currency' => [
            'base_must_be_active' => 'يجب أن تظل العملة الأساسية نشطة.',
            'only_base_currency' => 'لا يمكن تعطيل العملة الأساسية النشطة الوحيدة.',
        ],
        'exchange_rate' => [
            'distinct_pair' => 'يجب أن تختلف عملة الأساس عن عملة التحويل.',
            'positive_rate' => 'يجب أن يكون سعر الصرف أكبر من صفر.',
        ],
        'locale' => [
            'supported' => 'يجب أن تكون اللغة العربية أو الإنجليزية.',
        ],
    ],
];
