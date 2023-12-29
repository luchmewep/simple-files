<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Luchavez\SimpleFiles\Models\File;

use function Pest\Laravel\artisan;

beforeEach(fn () => setupFakeStorages());

it('can sync files', function (bool $is_public, ?string $prefix = null) {
    setPrefix($is_public, $prefix);

    $disk = Storage::disk(simpleFiles()->getDiskName(is_public: $is_public, read_only: true));
    $prefix = simpleFiles()->getPrefix($is_public);
    $file_name = Str::random(10).'.jpg';
    $file = createFakeImage($file_name);

    $query = File::public($is_public);

    // Check initial state
    expect($query->count())->toBe(0);
    $disk->assertDirectoryEmpty($prefix);

    // Seed the fake storage and sync to DB
    $path = $disk->put($file_name, $file);
    artisan('sf:sync');

    // Check final state
    expect($query->count())->toBe(1)->and($query->where('path', $path)->exists())->toBeTrue();
    $disk->assertExists($path);
})
    ->with('public-private')
    ->with('prefix');
