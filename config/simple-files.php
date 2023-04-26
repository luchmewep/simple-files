<?php

return [
    'default_disk' => env('SF_FILESYSTEM_DISK', config('filesystems.default')),
    'expire_after' => env('SF_EXPIRE_AFTER', '1 day'),
    'directory' => [
        'public' => env('SF_PUBLIC_DIRECTORY', 'public'),
        'private' => env('SF_PRIVATE_DIRECTORY', 'private'),
    ],
    'overwrite_on_exists' => env('SF_OVERWRITE_ON_EXISTS', false),
    'routes_enabled' => env('SF_ROUTES_ENABLED', true),
];
