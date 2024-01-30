<?php

namespace Luchavez\SimpleFiles\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Mail\Attachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Storage;
use Luchavez\SimpleFiles\Traits\HasFileFactoryTrait;
use Luchavez\StarterKit\Traits\ModelOwnedTrait;
use Luchavez\StarterKit\Traits\UsesUUIDTrait;
use Spatie\Tags\HasTags;

/**
 * Class File
 *
 * @method static Builder image(bool $bool = true) Get image files.
 * @method static Builder extension(string $extension) Get files with specific extension.
 * @method static Builder public(bool $bool = true) Get public files.
 * @method static Builder private(bool $bool = true) Get private files.
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class File extends Model implements Attachable
{
    use HasFileFactoryTrait;
    use HasTags;
    use ModelOwnedTrait;
    use UsesUUIDTrait;

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $casts = [
        'is_public' => 'boolean',
        'url_expires_at' => 'datetime',
    ];

    /***** SCOPES *****/

    /**
     * @param  Builder  $builder
     * @param  bool  $bool
     * @return Builder
     */
    public function scopeImage(Builder $builder, bool $bool = true): Builder
    {
        return $builder->where('mime_type', ($bool ? '' : 'NOT ').'LIKE', 'image%');
    }

    /**
     * @param  Builder  $builder
     * @param  string  $extension
     * @return Builder
     */
    public function scopeExtension(Builder $builder, string $extension): Builder
    {
        return $builder->where('extension', $extension);
    }

    /**
     * @param  Builder  $builder
     * @param  bool  $bool
     * @return Builder
     */
    public function scopePublic(Builder $builder, bool $bool = true): Builder
    {
        return $builder->where('is_public', $bool);
    }

    /**
     * @param  Builder  $builder
     * @param  bool  $bool
     * @return Builder
     */
    public function scopePrivate(Builder $builder, bool $bool = true): Builder
    {
        return $builder->where('is_public', ! $bool);
    }

    /***** ACCESSORS & MUTATORS *****/

    /**
     * @param $value
     * @return void
     */
    public function setPathAttribute($value): void
    {
        $this->attributes['path'] = trim($value, '/ ');
    }

    /***** MAIL RELATED *****/

    /**
     * Get an attachment instance for this entity.
     *
     * @return \Illuminate\Mail\Attachment
     */
    public function toMailAttachment(): \Illuminate\Mail\Attachment
    {
        $disk = simpleFiles()->getDiskName(is_public: $this->is_public, read_only: true);

        return Attachment::fromStorageDisk(disk: $disk, path: $this->path);
    }

    /***** FILESYSTEM RELATED *****/

    /**
     * @param  bool  $read_only
     * @return Filesystem
     */
    protected function getFilesystemAdapter(bool $read_only = false): Filesystem
    {
        return simpleFiles()->disk(is_public: $this->is_public, read_only: $read_only);
    }

    /**
     * @return string|null
     */
    public function getContents(): ?string
    {
        return $this->getFilesystemAdapter()->get($this->path);
    }

    /**
     * @return resource|null
     */
    public function getStreamedContents()
    {
        return $this->getFilesystemAdapter()->readStream($this->path);
    }

    /**
     * @return UploadedFile|null
     */
    public function toUploadedFile(): ?UploadedFile
    {
        $local_disk = Storage::disk('local');
        $tmp_path = 'simple-files/'.($this->is_public ? 'public' : 'private').'/'.$this->path;
        $exists = $local_disk->exists($tmp_path);

        // Check if already exists on simple-files folder on local disk
        if (! $exists) {
            $exists = $local_disk->put($tmp_path, $this->getStreamedContents());
        }

        // Check if exists
        if ($exists) {
            return new UploadedFile(path: $local_disk->path($tmp_path), originalName: $this->name, mimeType: $this->mime_type);
        }

        return null;
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->getFilesystemAdapter(true)->exists($this->path);
    }

    /**
     * @return $this
     */
    public function generateUrl(): static
    {
        $exists = $this->exists();

        // Run these if file exists
        if ($exists) {
            $url_expires_at = simpleFiles()->getExpireAfter();
            $this->url = simpleFiles()->url($this->is_public, $this->path, $url_expires_at);
            $this->url_expires_at = $this->is_public ? null : (string) $url_expires_at;
        } else {
            $this->url = null;
            $this->url_expires_at = null;
        }

        return $this;
    }
}
