<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function setPrefix(bool $is_public, ?string $prefix): void
{
    config(['simple-files.'.($is_public ? 'public' : 'private').'.prefix' => $prefix]);
}

function setupFakeStorages(): void
{
    foreach ([true, false] as $is_public) {
        Storage::fake(simpleFiles()->getDiskName($is_public));
        Storage::fake(simpleFiles()->getDiskName($is_public, true));
    }
}

function createFakeImage(string $file_name): File
{
    return UploadedFile::fake()->image($file_name, 1000, 1000);
}
