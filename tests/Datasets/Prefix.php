<?php

use Illuminate\Support\Str;

dataset('prefix', function () {
    return [
        'with prefix' => Str::random(10),
        'without prefix' => null,
    ];
});
