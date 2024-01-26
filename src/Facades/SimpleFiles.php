<?php

namespace Luchavez\SimpleFiles\Facades;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Luchavez\SimpleFiles\DataFactories\FileDataFactory;
use Luchavez\SimpleFiles\Models\File;

/**
 * @method static Carbon getExpireAfter()
 * @method static string|null getPrefix(bool $is_public)
 * @method static bool shouldOverwriteOnExists()
 * @method static bool shouldSkipUploadOnExists()
 * @method static string getDriver(bool $is_public)
 * @method static string getDiskName(bool $is_public, bool $read_only = false)
 * @method static Filesystem disk(bool|null $is_public, bool $read_only = false)
 * @method static File|Builder|FileDataFactory|Collection|null store(bool $is_public, Collection|UploadedFile[]|\Illuminate\Http\File[]|string[]|UploadedFile|\Illuminate\Http\File|string $file, \Illuminate\Contracts\Auth\Authenticatable|null $user = null, bool $preserve_name = false, bool $return_as_model = true, string[] $tags = [], array $options = [])
 * @method static File|Builder|FileDataFactory|Collection|null storePublicly(Collection|UploadedFile[]|\Illuminate\Http\File[]|string[]|UploadedFile|\Illuminate\Http\File|string $file, \Illuminate\Contracts\Auth\Authenticatable|null $user = null, bool $preserve_name = false, bool $return_as_model = true, string[] $tags = [], array $options = [])
 * @method static File|Builder|FileDataFactory|Collection|null storePrivately(Collection|UploadedFile[]|\Illuminate\Http\File[]|string[]|UploadedFile|\Illuminate\Http\File|string $file, \Illuminate\Contracts\Auth\Authenticatable|null $user = null, bool $preserve_name = false, bool $return_as_model = true, string[] $tags = [], array $options = [])
 * @method static string|null getContentsFromURL(string $file)
 * @method static string|null getContentsFromBase64(string $file)
 * @method static array getFiles(bool|null $is_public, string|null $path = null, bool $recursive = false)
 * @method static array getPublicFiles(string|null $path = null, bool $recursive = false)
 * @method static array getPrivateFiles(string|null $path = null, bool $recursive = false)
 * @method static string|null getFile(bool|null $is_public, string $path)
 * @method static string|null getFilePublicly(string $path)
 * @method static string|null getFilePrivately(string $path)
 * @method static string|null putFile(bool $is_public, string $path, string|resource $contents, mixed $options = [])
 * @method static string|null putFilePublicly(string $path, string|resource $contents, mixed $options = [])
 * @method static string|null putFilePrivately(string $path, string|resource $contents, mixed $options = [])
 * @method static string|bool putFileAs(bool|null $is_public, string $path, \Illuminate\Http\File|UploadedFile $file, string $name, mixed $options = [])
 * @method static string|bool putFilePubliclyAs(string $path, \Illuminate\Http\File|UploadedFile $file, string $name, mixed $options = [])
 * @method static string|bool putFilePrivatelyAs(string $path, \Illuminate\Http\File|UploadedFile $file, string $name, mixed $options = [])
 * @method static bool exists(bool $is_public, string $path)
 * @method static bool existsPublicly(string $path)
 * @method static bool existsPrivately(string $path)
 * @method static bool delete(bool $is_public, string|string[] $path)
 * @method static bool deletePublicly(string|string[] $path)
 * @method static bool deletePrivately(string|string[] $path)
 * @method static array getDirectories(bool $is_public, string|null $path = null, bool $recursive = false)
 * @method static array getPublicDirectories(string|null $path = null, bool $recursive = false)
 * @method static array getPrivateDirectories(string|null $path = null, bool $recursive = false)
 * @method static bool deleteFiles(bool $is_public, string $path = '')
 * @method static bool deletePublicFiles(string $path = '')
 * @method static bool deletePrivateFiles(string $path = '')
 * @method static void relateFileModelTo(string $model_class, string|null $relationship_name = null)
 * @method static void generateUrl(File $file)
 * @method static string url(bool $is_public, string $path, DateTimeInterface|null $expiration = null)
 *
 * @see \Luchavez\SimpleFiles\Services\SimpleFiles
 */
class SimpleFiles extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'simple-files';
    }
}
