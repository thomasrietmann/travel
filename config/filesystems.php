<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => env('FILESYSTEM_LOCAL_ROOT', storage_path('app/private')),
            'serve' => true,
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => env('FILESYSTEM_PUBLIC_ROOT', storage_path('app/public')),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
    ],
    'links' => [
        public_path('storage') => env('FILESYSTEM_PUBLIC_ROOT', storage_path('app/public')),
    ],
    'private_fallback_roots' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('FILESYSTEM_PRIVATE_FALLBACK_ROOTS', ''))
    ))),
    'public_fallback_roots' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('FILESYSTEM_PUBLIC_FALLBACK_ROOTS', ''))
    ))),
];
