<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Luchavez\SimpleFiles\DataFactories\FileDataFactory;
use Luchavez\SimpleFiles\Exceptions\FileUploadFailedException;
use Luchavez\SimpleFiles\Models\File;

beforeEach(fn () => setupFakeStorages());

it('should return a File model instance', function (bool $is_public, ?string $prefix = null) {
    setPrefix($is_public, $prefix);

    $file_name = Str::random(10).'.jpg';
    $file = createFakeImage($file_name);
    try {
        $result_1 = simpleFiles()->store(is_public: $is_public, file: $file);
        $result_2 = simpleFiles()->store(is_public: $is_public, file: $file);

        expect($result_1)->and($result_2)->toBeInstanceOf(File::class)
            ->and($result_1->name != $result_2->name)->toBeTrue();

        $result_3 = simpleFiles()->store(is_public: $is_public, file: [$file]);

        expect($result_3)->toBeInstanceOf(Collection::class)->not->toBeEmpty()->toHaveCount(1)
            ->and($result_3->first())->toBeInstanceOf(File::class);
    } catch (FileUploadFailedException) {
    }
})
    ->with('public-private')
    ->with('prefix');

it('should return a FileDataFactory instance', function (bool $is_public, ?string $prefix = null) {
    setPrefix($is_public, $prefix);

    $file_name = Str::random(10).'.jpg';
    $file = createFakeImage($file_name);
    try {
        $result_1 = simpleFiles()->store(is_public: $is_public, file: $file, return_as_model: false);
        $result_2 = simpleFiles()->store(is_public: $is_public, file: $file, return_as_model: false);

        expect($result_1)->and($result_2)->toBeInstanceOf(FileDataFactory::class)
            ->and($result_1->name != $result_2->name)->toBeTrue();

        $result_3 = simpleFiles()->store(is_public: $is_public, file: [$file], return_as_model: false);

        expect($result_3)->toBeInstanceOf(Collection::class)->not->toBeEmpty()->toHaveCount(1)
            ->and($result_3->first())->toBeInstanceOf(FileDataFactory::class);
    } catch (FileUploadFailedException) {
    }
})
    ->with('public-private')
    ->with('prefix');

test('anonymous user can upload a file', function (bool $is_public, ?string $prefix = null) {
    setPrefix($is_public, $prefix);

    $file_name = Str::random(10).'.jpg';
    $file = createFakeImage($file_name);
    try {
        $result = simpleFiles()->store(is_public: $is_public, file: $file);
        expect($result)->toBeInstanceOf(File::class)->and($result->owner()->count())->toBe(0);
    } catch (FileUploadFailedException) {
    }
})
    ->with('public-private')
    ->with('prefix');

test('a user can upload a file', function (bool $is_public, ?string $prefix = null) {
    setPrefix($is_public, $prefix);

    $file_name = Str::random(10).'.jpg';
    $file = createFakeImage($file_name);
    $user = User::factory()->create();
    try {
        $result = simpleFiles()->store(is_public: $is_public, file: $file, user: $user);
        expect($result)->toBeInstanceOf(File::class)
            ->and($result->owner()->count())->toBe(1)
            ->and($result->owner()->is($user))->toBeTrue();
    } catch (FileUploadFailedException) {
    }
})
    ->with('public-private')
    ->with('prefix');

test('an authenticated user can upload a file', function (bool $is_public, ?string $prefix = null) {
    setPrefix($is_public, $prefix);

    $user = User::factory()->create();
    auth()->setUser($user);

    $file_name = Str::random(10).'.jpg';
    $file = createFakeImage($file_name);

    try {
        $result = simpleFiles()->store(is_public: $is_public, file: $file);
        expect($result)->toBeInstanceOf(File::class)
            ->and($result->owner()->count())->toBe(1)
            ->and($result->owner()->is($user))->toBeTrue();
    } catch (FileUploadFailedException) {
    }
})
    ->with('public-private')
    ->with('prefix');

it('preserves file name on upload', function (bool $is_public, ?string $prefix = null) {
    setPrefix($is_public, $prefix);

    $file_name = Str::random(10).'.jpg';
    $file = createFakeImage($file_name);
    try {
        $result_1 = simpleFiles()->store(is_public: $is_public, file: $file, preserve_name: true);
        $result_2 = simpleFiles()->store(is_public: $is_public, file: $file, preserve_name: true);

        expect($result_1)->and($result_2)->toBeInstanceOf(File::class)
            ->and($result_1->getKey())->toBe($result_2->getKey())
            ->and($result_1->name)->and($result_2->name)->toBe($file_name);
    } catch (FileUploadFailedException) {
    }
})
    ->with('public-private')
    ->with('prefix');

it('appends random string when preserve file name is true and skipping on exists is false', function (bool $is_public, ?string $prefix = null) {
    // Disable skip upload when file already exists
    setPrefix($is_public, $prefix);
    config(['simple-files.skip_upload_on_exists' => false]);

    $random = Str::random(10);
    $extension = 'jpg';
    $file = UploadedFile::fake()->image($random.'.'.$extension, 1000, 1000);

    try {
        $result_1 = simpleFiles()->store(is_public: $is_public, file: $file, preserve_name: true);
        $result_2 = simpleFiles()->store(is_public: $is_public, file: $file, preserve_name: true);

        expect($result_1)->and($result_2)->toBeInstanceOf(File::class)
            ->and($result_1->getKey())->not->toBe($result_2->getKey())
            ->and($result_1->name)->and($result_2->name)->toStartWith($random)->toEndWith($extension)
            ->and($result_2->name)->not->toBe($random.'.'.$extension);
    } catch (FileUploadFailedException) {
    }
})
    ->with('public-private')
    ->with('prefix');

//test('can upload files with tags');
