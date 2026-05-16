<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],
        'single' => [
            'driver' => 'single',
            'path' => env('LOG_PATH', storage_path('logs/laravel.log')),
            'level' => env('LOG_LEVEL', 'debug'),
        ],
        'mail_import' => [
            'driver' => 'single',
            'path' => storage_path('logs/mail-import.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],
    ],
];
