<?php

return [
    'public' => [
        'disk' => env('SF_PUBLIC_DISK', config('filesystems.default')),
        'prefix' => env('SF_PUBLIC_PREFIX'),
    ],
    'private' => [
        'disk' => env('SF_PRIVATE_DISK', config('filesystems.default')),
        'prefix' => env('SF_PRIVATE_PREFIX'),
        'expire_after' => env('SF_EXPIRE_AFTER', '1 day'),
    ],
    'overwrite_on_exists' => env('SF_OVERWRITE_ON_EXISTS', false),
    'skip_upload_on_exists' => env('SF_SKIP_UPLOAD_ON_EXISTS', true),
];
