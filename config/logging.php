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
            'driver' => 'daily',
            'days' => 14,
            'path' => (env('LOG_PATH') ?: storage_path('logs/laravel.log')),
            'level' => env('LOG_LEVEL', 'debug'),
        ],
        'audit' => [
            'driver' => 'daily',
            'days' => 90,
            'path' => env('AUDIT_LOG_PATH') ?: (env('LOG_PATH') ? dirname(env('LOG_PATH')).'/audit.log' : storage_path('logs/audit.log')),
            'level' => 'info',
        ],
        'mail_import' => [
            'driver' => 'daily',
            'days' => 14,
            'path' => env('MAIL_IMPORT_LOG_PATH')
                ?: (env('LOG_PATH') ? dirname(env('LOG_PATH')).'/mail-import.log' : storage_path('logs/mail-import.log')),
            'level' => env('LOG_LEVEL', 'debug'),
        ],
    ],
];
