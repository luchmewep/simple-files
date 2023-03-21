<?php

return [
    'default_driver' => env('SF_FILESYSTEM_DRIVER', config('filesystems.default')),
    'expire_after' => [
        'time_unit' => env('SF_EXPIRE_AFTER_UNIT', 'days'),
        'time_value' => env('SF_EXPIRE_AFTER_VALUE', 1),
    ],
    'directory' => [
        'public' => env('SF_PUBLIC_DIRECTORY', 'public'),
        'private' => env('SF_PRIVATE_DIRECTORY', 'private'),
    ],
    'overwrite_on_exists' => env('SF_OVERWRITE_ON_EXISTS', false),
    'routes_enabled' => env('SF_ROUTES_ENABLED', true),
];
