<?php

return [
    'enabled' => (bool) env('MAIL_IMPORT_ENABLED', false),
    'recipient' => env('MAIL_IMPORT_RECIPIENT', 'travel@aufbollen.ch'),
    'max_messages' => (int) env('MAIL_IMPORT_MAX_MESSAGES', 10),
    'mark_seen' => (bool) env('MAIL_IMPORT_MARK_SEEN', true),
    'log_path' => env('MAIL_IMPORT_LOG_PATH')
        ?: (env('LOG_PATH') ? dirname(env('LOG_PATH')).'/mail-import.log' : storage_path('logs/mail-import.log')),
    'imap' => [
        'mailbox' => env('MAIL_IMPORT_IMAP_MAILBOX'),
        'username' => env('MAIL_IMPORT_IMAP_USERNAME'),
        'password' => env('MAIL_IMPORT_IMAP_PASSWORD'),
        'search' => env('MAIL_IMPORT_IMAP_SEARCH', 'UNSEEN'),
    ],
];
