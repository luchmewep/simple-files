<?php

namespace Luchavez\SimpleFiles\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Luchavez\SimpleFiles\DataFactories\FileDataFactory;
use Luchavez\SimpleFiles\Exceptions\FileUploadFailedException;
use Luchavez\SimpleFiles\Models\File;
use Luchavez\SimpleFiles\Models\Fileable;
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
     * Constructor
     */
    public function __construct()
    {
        if ($this->getExpireAfter()->greaterThan(now()->addDays(7))) {
            throw new RuntimeException('File expiration must not be more than 7 days.');
        }
    }

    /***** CONFIG RELATED *****/

    /**
     * @return string
     */
    public function getFilesystemDisk(): string
    {
        return config('simple-files.default_disk');
    }

    /**
     * @return string
     */
    public function getDisk(): string
    {
        return $this->getFilesystemDisk();
    }

    /**
     * @return Carbon
     */
    public function getExpireAfter(): Carbon
    {
        return Carbon::parse(config('simple-files.expire_after'));
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

    /**
     * @return bool
     */
    public function shouldSkipUploadOnExists(): bool
    {
        return config('simple-files.skip_upload_on_exists');
    }

    /***** FILESYSTEM ADAPTER *****/

    /**
     * @param  bool|null  $is_public
     * @param  bool  $read_only
     * @return Filesystem
     */
    public function getFilesystemAdapter(bool $is_public = null, bool $read_only = false): Filesystem
    {
        if (is_null($is_public)) {
            $disk = $this->getDisk();
        } else {
            // Example: sf-public-ro
            $disk = collect(['sf', $is_public ? 'public' : 'private', $read_only ? 'ro' : null])->filter()->join('-');
        }

        return Storage::disk($disk);
    }

    /**
     * @param  bool|null  $is_public
     * @param  bool  $read_only
     * @return Filesystem
     */
    public function getAdapter(bool $is_public = null, bool $read_only = false): Filesystem
    {
        return $this->getFilesystemAdapter(is_public: $is_public, read_only: $read_only);
    }

    /**
     * @param  bool  $read_only
     * @return Filesystem
     */
    public function getPublicAdapter(bool $read_only = false): Filesystem
    {
        return $this->getFilesystemAdapter(is_public: true, read_only: $read_only);
    }

    /**
     * @param  bool  $read_only
     * @return Filesystem
     */
    public function getPrivateAdapter(bool $read_only = false): Filesystem
    {
        return $this->getFilesystemAdapter(is_public: false, read_only: $read_only);
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
        Authenticatable $user = null,
        bool $is_public = true,
        bool $preserve_name = false,
        bool $return_as_model = true,
        array $options = []
    ): File|Builder|Collection|null {
        // If no user is provided, get the auth()->user()
        $user ??= auth()->user();

        if ($file instanceof Collection || is_array($file)) {
            return collect($file)->map(fn ($f) => $this->store($f, $user, $is_public, $preserve_name, $return_as_model, $options));
        }

        // Prepare data factory
        $factory = new FileDataFactory();

        $factory->is_public = $is_public;
        $factory->user_id = $user?->id;
        $folder = $factory->user_id;
        $factory->name = Str::random(40);

        // Upload File and UploadedFile instances to Storage
        if ($file instanceof UploadedFile || $file instanceof \Illuminate\Http\File) {
            $factory->mime_type = $file->getMimeType();
            $factory->extension = $file->extension();

            if ($preserve_name) {
                $filename = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
                $factory->name = Str::of($filename)->before('.')->jsonSerialize();
            }

            $folder = collect([$folder, $factory->mime_type])->filter()->implode('/');
            $path = sprintf('%s/%s.%s', $folder, $factory->name, $factory->extension);

            // If file already exists, check the file size and the
            $skip_upload = false;
            if ($preserve_name && $this->exists(path: $path, is_public: $is_public)) {
                if (! $this->shouldOverwriteOnExists()) {
                    if ($this->shouldSkipUploadOnExists()) {
                        $skip_upload = true;
                        $factory->path = $path;
                    } else {
                        $factory->name .= sprintf('_%s', time());
                    }
                }
            }

            // Append the extension to the name before upload
            $factory->name .= sprintf('.%s', $factory->extension);

            if (! $skip_upload) {
                if ($path = $this->putFileAs(
                    path: $folder,
                    file: $file,
                    name: $factory->name,
                    options: $options,
                    is_public: $is_public
                )) {
                    $factory->path = $path;
                } else {
                    throw new FileUploadFailedException();
                }
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
            $path = collect([$folder, $factory->mime_type, $factory->name])->filter()->implode('/');

            if (! $this->putFile(path: $path, contents: $contents, options: $options, is_public: $is_public)) {
                throw new FileUploadFailedException();
            }

            $factory->path = $path;
        }

        if (isset($factory->path)) {
            $factory->size = $this->getFilesystemAdapter(is_public: $is_public)->size($factory->path);

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
        return $this->store(
            file: $file,
            user: $user,
            is_public: true,
            preserve_name: $preserve_name,
            return_as_model: $return_as_model,
            options: $options
        );
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
        return $this->store(
            file: $file,
            user: $user,
            is_public: false,
            preserve_name: $preserve_name,
            return_as_model: $return_as_model,
            options: $options
        );
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
     * @param  string|null  $path
     * @param  bool|null  $is_public
     * @param  bool  $recursive
     * @return array
     */
    public function getFiles(string $path = null, bool $is_public = null, bool $recursive = false): array
    {
        return $this->getFilesystemAdapter($is_public)->files(directory: $path, recursive: $recursive);
    }

    /**
     * @param  string|null  $path
     * @param  bool  $recursive
     * @return array
     */
    public function getPublicFiles(string $path = null, bool $recursive = false): array
    {
        return $this->getFiles(path: $path, is_public: true, recursive: $recursive);
    }

    /**
     * @param  string|null  $path
     * @param  bool  $recursive
     * @return array
     */
    public function getPrivateFiles(string $path = null, bool $recursive = false): array
    {
        return $this->getFiles(path: $path, is_public: false, recursive: $recursive);
    }

    /***** GET FILE *****/

    /**
     * @param  string  $path
     * @param  bool|null  $is_public
     * @return string|null
     */
    public function getFile(string $path, bool $is_public = null): ?string
    {
        return $this->getFilesystemAdapter($is_public)->get($path);
    }

    /**
     * @param  string  $path
     * @return string|null
     */
    public function getFilePublicly(string $path): ?string
    {
        return $this->getFile(path: $path, is_public: true);
    }

    /**
     * @param  string  $path
     * @return string|null
     */
    public function getFilePrivately(string $path): ?string
    {
        return $this->getFile(path: $path, is_public: false);
    }

    /***** PUT FILE *****/

    /**
     * @param  string  $path
     * @param  string|resource  $contents
     * @param  mixed  $options
     * @param  bool|null  $is_public
     * @return string|null
     */
    public function putFile(string $path, $contents, mixed $options = [], bool $is_public = null): ?string
    {
        return $this->getFilesystemAdapter($is_public)->put($path, $contents, $options);
    }

    /**
     * @param  string  $path
     * @param  string|resource  $contents
     * @param  mixed  $options
     * @return string|null
     */
    public function putFilePublicly(string $path, $contents, mixed $options = []): ?string
    {
        return $this->putFile(path: $path, contents: $contents, options: $options, is_public: true);
    }

    /**
     * @param  string  $path
     * @param  string|resource  $contents
     * @param  mixed  $options
     * @return string|null
     */
    public function putFilePrivately(string $path, $contents, mixed $options = []): ?string
    {
        return $this->putFile(path: $path, contents: $contents, options: $options, is_public: false);
    }

    /***** PUT FILE AS *****/

    /**
     * @param  string  $path
     * @param  \Illuminate\Http\File|UploadedFile  $file
     * @param  string  $name
     * @param  mixed  $options
     * @param  bool|null  $is_public
     * @return string|bool
     */
    public function putFileAs(
        string $path,
        \Illuminate\Http\File|UploadedFile $file,
        string $name,
        mixed $options = [],
        bool $is_public = null
    ): string|bool {
        return $this->getFilesystemAdapter($is_public)->putFileAs($path, $file, $name, $options);
    }

    /**
     * @param  string  $path
     * @param  \Illuminate\Http\File|UploadedFile  $file
     * @param  string  $name
     * @param  mixed  $options
     * @return string|bool
     */
    public function putFilePubliclyAs(string $path, \Illuminate\Http\File|UploadedFile $file, string $name, mixed $options = []): string|bool
    {
        return $this->putFileAs(path: $path, file: $file, name: $name, options: $options, is_public: true);
    }

    /**
     * @param  string  $path
     * @param  \Illuminate\Http\File|UploadedFile  $file
     * @param  string  $name
     * @param  mixed  $options
     * @return string|bool
     */
    public function putFilePrivatelyAs(string $path, \Illuminate\Http\File|UploadedFile $file, string $name, mixed $options = []): string|bool
    {
        return $this->putFileAs(path: $path, file: $file, name: $name, options: $options, is_public: false);
    }

    /***** FILE EXISTENCE *****/

    /**
     * @param  string  $path
     * @param  bool|null  $is_public
     * @return bool
     */
    public function exists(string $path, bool $is_public = null): bool
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

    /***** DELETE FILES *****/

    /**
     * @param  string|string[]  $path
     * @param  bool|null  $is_public
     * @return bool
     */
    public function delete(string|array $path, bool $is_public = null): bool
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
     * @param  string|null  $path
     * @param  bool|null  $is_public
     * @param  bool  $recursive
     * @return array
     */
    public function getDirectories(string $path = null, bool $is_public = null, bool $recursive = false): array
    {
        return $this->getFilesystemAdapter($is_public)->directories(directory: $path, recursive: $recursive);
    }

    /**
     * @param  string|null  $path
     * @param  bool  $recursive
     * @return array
     */
    public function getPublicDirectories(string $path = null, bool $recursive = false): array
    {
        return $this->getFilesystemAdapter(true)->directories(directory: $path, recursive: $recursive);
    }

    /**
     * @param  string|null  $path
     * @param  bool  $recursive
     * @return array
     */
    public function getPrivateDirectories(string $path = null, bool $recursive = false): array
    {
        return $this->getFilesystemAdapter(false)->directories(directory: $path, recursive: $recursive);
    }

    /***** CLEAN DIRECTORY *****/

    /**
     * @param  string  $path
     * @param  bool  $is_public
     * @return bool
     */
    public function deleteFiles(string $path = '', bool $is_public = null): bool
    {
        return $this->getFilesystemAdapter($is_public)->deleteDirectory($path);
    }

    /**
     * @param  string  $path
     * @return bool
     */
    public function deletePublicFiles(string $path = ''): bool
    {
        return $this->deleteFiles(path: $path, is_public: true);
    }

    /**
     * @param  string  $path
     * @return bool
     */
    public function deletePrivateFiles(string $path = ''): bool
    {
        return $this->deleteFiles(path: $path, is_public: false);
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

    /**
     * @param  File  $file
     * @return void
     */
    public function generateUrl(File $file): void
    {
        $adapter = $this->getFilesystemAdapter($file->is_public);

        // If the file is new, check file existence
        // If the file is old but url is not null, check file existence
        // If file does not exist, set url and url_expires_at to null
        // If file exists and is public and url is not yet set, generate url
        // If file exists and is private but url_expires_at is not yet set, generate url
        // If file exists and is private and url_expires_at is set but url_expires_at is <= to now(), generate url

        $exists = false;

        if ((! isset($file->created_at) || isset($file->url)) &&
            ! ($exists = $adapter->exists(path: $file->path))
        ) {
            $file->url = null;
            $file->url_expires_at = null;
        }

        // Run these if file exists
        if ($exists) {
            // if file is public
            if ($file->is_public && ! isset($file->url)) {
                $file->url = $adapter->url($file->path);
            }
            // if file is private
            elseif (! isset($file->url_expires_at) || $file->url_expires_at <= now()) {
                $url_expires_at = simpleFiles()->getExpireAfter();
                if ($url = $adapter->temporaryUrl($file->path, $url_expires_at)) {
                    $file->url = $url;
                    $file->url_expires_at = (string) $url_expires_at;
                }
            }
        }
    }
}
