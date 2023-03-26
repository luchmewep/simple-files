<?php

namespace Luchavez\SimpleFiles\Traits;

use Illuminate\Contracts\Auth\Authenticatable as User;
use Luchavez\SimpleFiles\Exceptions\FileUploadFailedException;
use Luchavez\SimpleFiles\Models\File;
use Luchavez\SimpleFiles\Models\Fileable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Trait HasFilesTrait
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
trait HasFilesTrait
{
    /***** RELATIONSHIPS *****/

    /**
     * Gets related files.
     *
     * @return MorphToMany
     */
    public function files(): MorphToMany
    {
        return $this->morphToMany(File::class, 'fileable')->withPivot('description')->withTimestamps()->using(Fileable::class)->latest('updated_at');
    }

    /**
     * Gets related image files.
     *
     * @return MorphToMany
     */
    public function images(): MorphToMany
    {
        return $this->files()->scopes(['image']);
    }

    /**
     * Gets related non-image files.
     *
     * @return MorphToMany
     */
    public function nonImages(): MorphToMany
    {
        return $this->files()->scopes(['image' => false]);
    }

    /**
     * Gets related files.
     *
     * @return MorphMany
     */
    public function fileables(): MorphMany
    {
        return $this->morphMany(Fileable::class, 'fileable')->with('file')->latest('updated_at');
    }

    /**
     * @return MorphOne
     */
    public function fileable(): MorphOne
    {
        return $this->morphOne(Fileable::class, 'fileable')->with('file')->latest('updated_at');
    }

    /**
     * Gets related files.
     *
     * @return MorphMany
     */
    public function imageables(): MorphMany
    {
        $closure = fn ($builder) => $builder->image();

        return $this->morphMany(Fileable::class, 'fileable')->whereHas('file', $closure)->with('file', $closure)->latest('updated_at');
    }

    /**
     * @return MorphOne
     */
    public function imageable(): MorphOne
    {
        $closure = fn ($builder) => $builder->image();

        return $this->morphOne(Fileable::class, 'fileable')->whereHas('file', $closure)->with('file', $closure)->latest('updated_at');
    }

    /**
     * Gets related files.
     *
     * @return MorphMany
     */
    public function nonImageables(): MorphMany
    {
        $closure = fn ($builder) => $builder->image(false);

        return $this->morphMany(Fileable::class, 'fileable')->whereHas('file', $closure)->with('file', $closure)->latest('updated_at');
    }

    /**
     * Gets related files.
     *
     * @return MorphOne
     */
    public function nonImageable(): MorphOne
    {
        $closure = fn ($builder) => $builder->image(false);

        return $this->morphOne(Fileable::class, 'fileable')->whereHas('file', $closure)->with('file', $closure)->latest('updated_at');
    }

    /***** OTHER FUNCTIONS *****/

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  array|null  $description
     * @param  bool  $is_public
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function attachFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, User $user = null, ?array $description = null, bool $is_public = true, bool $preserve_name = false): void
    {
        $this->syncFiles($file, $user, $description, false, $is_public, $preserve_name);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  array|null  $description
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function attachPublicFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, User $user = null, ?array $description = null, bool $preserve_name = false): void
    {
        $this->attachFiles($file, $user, $description, true, $preserve_name);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  array|null  $description
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function attachPrivateFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, User $user = null, ?array $description = null, bool $preserve_name = false): void
    {
        $this->attachFiles($file, $user, $description, false, $preserve_name);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  array|null  $description
     * @param  bool  $detaching
     * @param  bool  $is_public
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function syncFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, User $user = null, ?array $description = null, bool $detaching = true, bool $is_public = true, bool $preserve_name = false): void
    {
        $file = $this->collectFiles($file);

        if ($file->count()) {
            if ($file->first() instanceof File) {
                $ids = $file->pluck('id')->mapWithKeys(fn ($id) => [$id => ['description' => $description]]);
                $this->files()->sync($ids, $detaching);
                $this->load('files');
            } elseif ($files = simpleFiles()->store($file, $user, $is_public, $preserve_name)) {
                $this->syncFiles($files, $user, $description, $is_public, $preserve_name);
            }
        }
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  array|null  $description
     * @param  bool  $detaching
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function syncPublicFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, User $user = null, ?array $description = null, bool $detaching = true, bool $preserve_name = false): void
    {
        $this->syncFiles($file, $user, $description, $detaching, true, $preserve_name);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  array|null  $description
     * @param  bool  $detaching
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function syncPrivateFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, User $user = null, ?array $description = null, bool $detaching = true, bool $preserve_name = false): void
    {
        $this->syncFiles($file, $user, $description, $detaching, false, $preserve_name);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  bool  $touch
     * @param  bool  $is_public
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function detachFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, User $user = null, bool $touch = true, bool $is_public = true, bool $preserve_name = false): void
    {
        $file = $this->collectFiles($file);

        if ($file->count()) {
            if ($file->first() instanceof File) {
                $this->files()->detach($file->pluck('id'), $touch);
                $this->load('files');
            } elseif ($files = simpleFiles()->store($file, $user, $is_public, $preserve_name)) {
                $this->detachFiles($files, $user, $is_public, $preserve_name);
            }
        }
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  bool  $touch
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function detachPublicFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, User $user = null, bool $touch = true, bool $preserve_name = false): void
    {
        $this->detachFiles($file, $user, $touch, true, $preserve_name);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  bool  $touch
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function detachPrivateFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, User $user = null, bool $touch = true, bool $preserve_name = false): void
    {
        $this->detachFiles($file, $user, $touch, false, $preserve_name);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @return Collection
     */
    protected function collectFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file): Collection
    {
        // If not a collection nor an array, convert to array
        if (! ($file instanceof Collection || is_array($file))) {
            $file = [$file];
        }

        return collect($file)->filter(); // remove null and empty strings
    }
}
