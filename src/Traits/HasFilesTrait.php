<?php

namespace Luchavez\SimpleFiles\Traits;

use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Luchavez\SimpleFiles\Exceptions\FileUploadFailedException;
use Luchavez\SimpleFiles\Models\File;
use Luchavez\SimpleFiles\Models\Fileable;

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
        return $this->morphToMany(File::class, 'fileable')->withTimestamps()->using(Fileable::class)->latest('updated_at');
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
     * @param  bool  $is_public
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  string[]  $tags
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function attachFiles(bool $is_public, \Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, ?User $user = null, bool $preserve_name = false, array $tags = []): void
    {
        $this->syncFiles(is_public: $is_public, file: $file, user: $user, detaching: false, preserve_name: $preserve_name, tags: $tags);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  string[]  $tags
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function attachPublicFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, ?User $user = null, bool $preserve_name = false, array $tags = []): void
    {
        $this->attachFiles(is_public: true, file: $file, user: $user, preserve_name: $preserve_name, tags: $tags);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  string[]  $tags
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function attachPrivateFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, ?User $user = null, bool $preserve_name = false, array $tags = []): void
    {
        $this->attachFiles(is_public: false, file: $file, user: $user, preserve_name: $preserve_name, tags: $tags);
    }

    /**
     * @param  bool  $is_public
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  string[]  $tags
     * @param  bool  $detaching
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function syncFiles(bool $is_public, \Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, ?User $user = null, bool $detaching = true, bool $preserve_name = false, array $tags = []): void
    {
        $file = $this->collectFiles($file);

        if ($file->count()) {
            if ($file->first() instanceof File) {
                $this->files()->sync($file->pluck('id'), $detaching);
                $this->load('files');
            } elseif ($files = simpleFiles()->store(is_public: $is_public, file: $file, user: $user, preserve_name: $preserve_name, tags: $tags)) {
                $this->syncFiles(is_public: $is_public, file: $files, user: $user, preserve_name: $preserve_name, tags: $tags);
            }
        }
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  string[]  $tags
     * @param  bool  $detaching
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function syncPublicFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, ?User $user = null, bool $detaching = true, bool $preserve_name = false, array $tags = []): void
    {
        $this->syncFiles(is_public: true, file: $file, user: $user, detaching: $detaching, preserve_name: $preserve_name, tags: $tags);
    }

    /**
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  string[]  $tags
     * @param  bool  $detaching
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function syncPrivateFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, ?User $user = null, bool $detaching = true, bool $preserve_name = false, array $tags = []): void
    {
        $this->syncFiles(is_public: true, file: $file, user: $user, detaching: $detaching, preserve_name: $preserve_name, tags: $tags);
    }

    /**
     * @param  bool  $is_public
     * @param  \Illuminate\Http\File|File|Collection|UploadedFile|array|string  $file
     * @param  User|null  $user
     * @param  bool  $touch
     * @param  bool  $preserve_name
     * @return void
     *
     * @throws FileUploadFailedException
     */
    public function detachFiles(bool $is_public, \Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, ?User $user = null, bool $touch = true, bool $preserve_name = false): void
    {
        $file = $this->collectFiles($file);

        if ($file->count()) {
            if ($file->first() instanceof File) {
                $this->files()->detach($file->pluck('id'), $touch);
                $this->load('files.tags');
            } elseif ($files = simpleFiles()->store(is_public: $is_public, file: $file, user: $user, preserve_name: $preserve_name)) {
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
    public function detachPublicFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, ?User $user = null, bool $touch = true, bool $preserve_name = false): void
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
    public function detachPrivateFiles(\Illuminate\Http\File|File|Collection|UploadedFile|array|string $file, ?User $user = null, bool $touch = true, bool $preserve_name = false): void
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
