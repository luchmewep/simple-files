<?php

namespace Luchavez\SimpleFiles\Services;

use Closure;
use Luchavez\SimpleFiles\DataFactories\FileDataFactory;
use Luchavez\SimpleFiles\Exceptions\FileUploadFailedException;
use Luchavez\SimpleFiles\Models\File;
use Luchavez\SimpleFiles\Models\Fileable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Class SimpleFiles
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 *
 * @since 2022-05-25
 */
class SimpleFiles
{
    /**
     * @var null|Closure(File):bool
     */
    protected Closure|null $isFilePublicGuesser;

    /**
     * @var Filesystem|FilesystemAdapter
     */
    protected FilesystemAdapter|Filesystem $filesystemAdapter;

    /**
     * Constructor
     */
    public function __construct()
    {
        if ($this->getExpireAfter()->greaterThan(now()->addDays(7))) {
            throw new RuntimeException('File expiration must not be more than 7 days.');
        }

        // Set the adapter
        $this->filesystemAdapter = Storage::disk($this->getFilesystemDriver());
    }

    /**
     * @return string
     */
    public function getFilesystemDriver(): string
    {
        return config('simple-files.default_driver');
    }

    /**
     * @param bool|null $is_public
     * @param bool $read_only
     * @return Filesystem
     */
    public function getFilesystemAdapter(bool|null $is_public = null, bool $read_only = false): Filesystem
    {
        if (is_null($is_public)) {
            $disk = $this->getFilesystemDriver();
        }
        else {
            // Example: sf-public-ro
            $disk = collect(['sf', $is_public ? 'public' : 'private', $read_only ? 'ro' : null])->filter()->join('-');
        }

        return Storage::disk($disk);
    }

    /**
     * @param bool|null $is_public
     * @param bool $read_only
     * @return Filesystem
     */
    public function getAdapter(bool|null $is_public = null, bool $read_only = false): Filesystem
    {
        return $this->getFilesystemAdapter($is_public, $read_only);
    }

    /**
     * @param bool $read_only
     * @return Filesystem
     */
    public function getPublicFilesystemAdapter(bool $read_only = false): Filesystem
    {
        return $this->getFilesystemAdapter(true, $read_only);
    }

    /**
     * @param bool $read_only
     * @return Filesystem
     */
    public function getPrivateFilesystemAdapter(bool $read_only = false): Filesystem
    {
        return $this->getFilesystemAdapter(false, $read_only);
    }

    /**
     * @return string
     */
    public function getExpireAfterUnit(): string
    {
        return config('simple-files.expire_after.time_unit');
    }

    /**
     * @return string
     */
    public function getExpireAfterValue(): string
    {
        return config('simple-files.expire_after.time_value');
    }

    /**
     * @return Carbon
     */
    public function getExpireAfter(): Carbon
    {
        return now()->add($this->getExpireAfterValue().' '.$this->getExpireAfterUnit());
    }

    /**
     * @return string
     */
    public function getPublicDirectory(): string
    {
        return trim(config('simple-files.directory.public'), '/');
    }

    /**
     * @return string
     */
    public function getPrivateDirectory(): string
    {
        return trim(config('simple-files.directory.private'), '/');
    }

    /**
     * @return bool
     */
    public function shouldOverwriteOnExists(): bool
    {
        return config('simple-files.overwrite_on_exists');
    }

    /***** STORE FILES *****/

    /**
     * If the user is not supplied, this will attempt to get user from auth helper.
     * If you choose to preserve original file name, this will check if file already exists.
     *
     * @param  Collection|UploadedFile[]|\Illuminate\Http\File[]|string[]|UploadedFile|\Illuminate\Http\File|string  $file
     * @param  User|null  $user
     * @param  bool  $is_public
     * @param  bool  $preserve_name
     * @param  bool  $return_as_model
     * @param  array  $options
     * @return File|Builder|array|null
     *
     * @throws FileUploadFailedException
     */
    public function store(
        Collection|UploadedFile|\Illuminate\Http\File|array|string $file,
        User $user = null,
        bool $is_public = true,
        bool $preserve_name = false,
        bool $return_as_model = true,
        array $options = []
    ): File|Builder|Collection|null {
        if ($file instanceof Collection || is_array($file)) {
            return collect($file)->map(fn ($f) => $this->store($f, $user, $is_public, $preserve_name, $return_as_model, $options));
        }

        // If no user is provided, get the auth()->user()
        $user ??= auth()->user();

        // Prepare data factory
        $factory = new FileDataFactory();

        $factory->user_id = $user?->id ?? null;
        $folder = collect([$is_public ? $this->getPublicDirectory() : $this->getPrivateDirectory(), $factory->user_id])->filter()->implode('/');
        $factory->name = Str::random(40);

        // Upload UploadedFile to Storage
        if ($file instanceof UploadedFile || $file instanceof \Illuminate\Http\File) {
            $factory->mime_type = $file->getMimeType();
            $factory->extension = $file->extension();

            if ($preserve_name) {
                $filename = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
                $factory->name = Str::of($filename)->before('.')->jsonSerialize();
            }

            $factory->name = trim($factory->name.'.'.$factory->extension, '/.');
            $folder .= '/'.$factory->mime_type;

            if ($preserve_name && ! $this->shouldOverwriteOnExists() && ($path = implode('/', [$folder, $factory->name])) && $this->exists($path)) {
                $factory->path = $path;
            } elseif ($path = $this->getFilesystemAdapter()->putFileAs($folder, $file, $factory->name, $options)) {
                $factory->path = $path;
            } else {
                throw new FileUploadFailedException();
            }
        }

        // Upload file from valid URL and Base64 string to Storage
        elseif ($contents = ($this->getContentsFromURL($file) ?? $this->getContentsFromBase64($file))) {
            // Get mime type
            $factory->mime_type = finfo_buffer(finfo_open(), $contents, FILEINFO_MIME_TYPE);

            // Guess extension
            if (($ext = finfo_buffer(finfo_open(), $contents, FILEINFO_EXTENSION)) && $ext != '???') {
                $factory->extension = $ext;
            } else {
                $factory->extension = MimeTypes::getDefault()->getExtensions($factory->mime_type)[0];
            }

            // Get name
            $factory->name = trim($factory->name.'.'.$factory->extension, '.');
            $path = implode('/', [$folder, $factory->mime_type, $factory->name]);

            if (! $this->getFilesystemAdapter()->put($path, $contents, $options)) {
                throw new FileUploadFailedException();
            }

            $factory->path = $path;
        }

        if (isset($factory->path)) {
            $factory->size = $this->getFilesystemAdapter()->size($factory->path);

            return $return_as_model ? $factory->updateOrCreate() : $factory->toArray();
        }

        return null;
    }

    /**
     * @param  Collection|UploadedFile[]|\Illuminate\Http\File[]|string[]|UploadedFile|\Illuminate\Http\File|string  $file
     * @param  User|null  $user
     * @param  bool  $preserve_name
     * @param  bool  $return_as_model
     * @param  array  $options
     * @return File|Builder|Collection|null
     *
     * @throws FileUploadFailedException
     */
    public function storePublicly(
        Collection|UploadedFile|\Illuminate\Http\File|array|string $file,
        User $user = null,
        bool $preserve_name = false,
        bool $return_as_model = true,
        array $options = []
    ): File|Builder|Collection|null {
        return $this->store($file, $user, true, $preserve_name, $return_as_model, $options);
    }

    /**
     * @param  Collection|UploadedFile[]|\Illuminate\Http\File[]|string[]|UploadedFile|\Illuminate\Http\File|string  $file
     * @param  User|null  $user
     * @param  bool  $preserve_name
     * @param  bool  $return_as_model
     * @param  array  $options
     * @return File|Builder|Collection|null
     *
     * @throws FileUploadFailedException
     */
    public function storePrivately(
        Collection|UploadedFile|\Illuminate\Http\File|array|string $file,
        User $user = null,
        bool $preserve_name = false,
        bool $return_as_model = true,
        array $options = []
    ): File|Builder|Collection|null {
        return $this->store($file, $user, false, $preserve_name, $return_as_model, $options);
    }

    /**
     * @param  string  $file
     * @return string|null
     */
    public function getContentsFromURL(string $file): ?string
    {
        if (is_valid_url($file) && make_request($file)->executeHead(null)->ok() && $contents = file_get_contents($file)) {
            return $contents;
        }

        return null;
    }

    /**
     * @param  string  $file
     * @return string|null
     */
    public function getContentsFromBase64(string $file): ?string
    {
        if (is_valid_base64($file = Str::of($file)->after(',')->jsonSerialize()) && $contents = base64_decode($file)) {
            return $contents;
        }

        return null;
    }

    /***** GET FILES *****/

    /**
     * @param string|null $path
     * @param bool|null $is_public
     * @param bool $recursive
     * @return array
     */
    public function getFiles(string|null $path = null, bool|null $is_public = null, bool $recursive = false): array
    {
        return $this->getFilesystemAdapter($is_public)->files(directory: $path, recursive: $recursive);
    }

    /**
     * @param string|null $path
     * @param bool $recursive
     * @return array
     */
    public function getPublicFiles(string|null $path = null, bool $recursive = false): array
    {
        return $this->getFiles(path: $path, is_public: true, recursive: $recursive);
    }

    /**
     * @param string|null $path
     * @param bool $recursive
     * @return array
     */
    public function getPrivateFiles(string|null $path = null, bool $recursive = false): array
    {
        return $this->getFiles(path: $path, is_public: false, recursive: $recursive);
    }

    /***** GET FILE *****/

    /**
     * @param string $path
     * @param bool|null $is_public
     * @return string|null
     */
    public function getFile(string $path, bool|null $is_public = null): ?string
    {
        return $this->getFilesystemAdapter($is_public)->get($path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function getFilePublicly(string $path): ?string
    {
        return $this->getFile(path: $path, is_public: true);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function getFilePrivately(string $path): ?string
    {
        return $this->getFile(path: $path, is_public: false);
    }

    /***** GET FILE *****/

    /**
     * @param string $path
     * @param string $contents
     * @param mixed $options
     * @param bool|null $is_public
     * @return string|null
     */
    public function putFile(string $path, string $contents, mixed $options = [], bool|null $is_public = null): ?string
    {
        return $this->getFilesystemAdapter($is_public)->put($path, $contents, $options);
    }

    /**
     * @param string $path
     * @param string $contents
     * @param mixed $options
     * @return string|null
     */
    public function putFilePublicly(string $path, string $contents, mixed $options = []): ?string
    {
        return $this->putFile(path: $path, contents: $contents, options: $options, is_public: true);
    }

    /**
     * @param string $path
     * @param string $contents
     * @param mixed $options
     * @return string|null
     */
    public function putFilePrivately(string $path, string $contents, mixed $options = []): ?string
    {
        return $this->putFile(path: $path, contents: $contents, options: $options, is_public: false);
    }

    /***** DELETE FILES *****/

    /**
     * @param string|string[] $path
     * @param bool|null $is_public
     * @return bool
     */
    public function delete(string|array $path, bool|null $is_public = null): bool
    {
        return $this->getFilesystemAdapter($is_public)->delete($path);
    }

    /**
     * @param  string|string[]  $path
     * @return bool
     */
    public function deletePublicly(string|array $path): bool
    {
        return $this->delete(path: $path, is_public: true);
    }

    /**
     * @param  string|string[]  $path
     * @return bool
     */
    public function deletePrivately(string|array $path): bool
    {
        return $this->delete(path: $path, is_public: false);
    }

    /***** GET DIRECTORIES *****/

    /**
     * @param string|null $path
     * @param bool|null $is_public
     * @param bool $recursive
     * @return array
     */
    public function getDirectories(string|null $path = null, bool|null $is_public = null, bool $recursive = false): array
    {
        return $this->getFilesystemAdapter($is_public)->directories(directory: $path, recursive: $recursive);
    }

    /**
     * @param string|null $path
     * @param bool $recursive
     * @return array
     */
    public function getPublicDirectories(string|null $path = null, bool $recursive = false): array
    {
        return $this->getFilesystemAdapter(true)->directories(directory: $path, recursive: $recursive);
    }

    /**
     * @param string|null $path
     * @param bool $recursive
     * @return array
     */
    public function getPrivateDirectories(string|null $path = null, bool $recursive = false): array
    {
        return $this->getFilesystemAdapter(false)->directories(directory: $path, recursive: $recursive);
    }

    /***** CLEAN DIRECTORY *****/

    /**
     * @param string $path
     * @param bool $is_public
     * @return bool
     */
    public function deleteFiles(string $path = '', bool|null $is_public = null): bool
    {
        return $this->getFilesystemAdapter($is_public)->deleteDirectory($path);
    }

    /**
     * @param string $path
     * @return bool
     */
    public function deletePublicFiles(string $path = ''): bool
    {
        return $this->deleteFiles(path: $path, is_public: true);
    }

    /**
     * @param string $path
     * @return bool
     */
    public function deletePrivateFiles(string $path = ''): bool
    {
        return $this->deleteFiles(path: $path, is_public: false);
    }

    /***** OTHER FUNCTIONS *****/

    /**
     * @param string $path
     * @param bool|null $is_public
     * @return bool
     */
    public function exists(string $path, bool|null $is_public = null): bool
    {
        return $this->getFilesystemAdapter($is_public)->exists($path);
    }

    /**
     * @param  string  $path
     * @return bool
     */
    public function existsPublicly(string $path): bool
    {
        return $this->exists(path: $path, is_public: true);
    }

    /**
     * @param  string  $path
     * @return bool
     */
    public function existsPrivately(string $path): bool
    {
        return $this->exists(path: $path, is_public: false);
    }


    /***** DYNAMIC RELATIONSHIP RESOLVING *****/

    /**
     * @param  string  $model_class
     * @param  string|null  $relationship_name
     * @return void
     */
    public function relateFileModelTo(string $model_class, string $relationship_name = null): void
    {
        if ($model_class && ($model_class = trim($model_class)) && is_eloquent_model($model_class)) {
            if (! $relationship_name || ! ($relationship_name = trim($relationship_name))) {
                $relationship_name = (new $model_class())->getTable();
            }

            if (method_exists($model_class, $relationship_name)) {
                throw new RuntimeException('Method or relationship already exists: '.$relationship_name);
            }

            File::resolveRelationUsing($relationship_name, function (Model $file) use ($model_class) {
                return $file->morphedByMany($model_class, 'fileable')->withPivot('description')->withTimestamps()->using(Fileable::class);
            });
        }
    }

    /***** FILE VISIBILITY GUESSER *****/

    /**
     * @param  Closure(File):bool  $isFilePublicGuesser
     */
    public function setIsFilePublicGuesser(Closure $isFilePublicGuesser): void
    {
        $this->isFilePublicGuesser = $isFilePublicGuesser;
    }

    /**
     * @param  File  $file
     * @return bool
     */
    public function isFilePublic(File $file): bool
    {
        if (isset($this->isFilePublicGuesser)) {
            return ($this->isFilePublicGuesser)($file);
        }

        return str_starts_with($file->path, $this->getPublicDirectory());
    }

    /**
     * @param  File  $file
     * @return void
     */
    public function generateUrl(File $file): void
    {
        $adapter = $this->getFilesystemAdapter();

        // If the file is new, check file existence
        // If the file is old but url is not null, check file existence
        // If file does not exist, set url and url_expires_at to null
        // If file exists and is public and url is not yet set, generate url
        // If file exists and is private but url_expires_at is not yet set, generate url
        // If file exists and is private and url_expires_at is set but url_expires_at is <= to now(), generate url

        $exists = false;

        if ((! isset($file->created_at) || isset($file->url)) && ! ($exists = $adapter->exists($file->path))) {
            $file->url = null;
            $file->url_expires_at = null;
        }

        // Run these if file exists
        if ($exists) {
            if (($is_public = $file->isPublic()) && ! isset($file->url)) { // if public file...
                $file->url = $adapter->url($file->path);
            } elseif (! $is_public && (! isset($file->url_expires_at) || $file->url_expires_at <= now())) { // if private file...
                $url_expires_at = simpleFiles()->getExpireAfter();
                if ($url = $adapter->temporaryUrl($file->path, $url_expires_at)) {
                    $file->url = $url;
                    $file->url_expires_at = (string) $url_expires_at;
                }
            }
        }
    }
}
