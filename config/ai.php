<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_BOOKING_MODEL', 'gpt-5.4-mini'),
        'summary_model' => env('OPENAI_SUMMARY_MODEL', env('OPENAI_BOOKING_MODEL', 'gpt-5.4-mini')),
        'timeout' => (int) env('OPENAI_TIMEOUT', 90),
    ],
];
