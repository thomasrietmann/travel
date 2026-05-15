<?php

return [
    'paths' => array_filter(explode(PATH_SEPARATOR, env('VIEW_PATH', resource_path('views')))),
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
            ?: storage_path('framework/views')
    ),
];
