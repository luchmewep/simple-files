<?php

namespace Luchavez\SimpleFiles\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Mail\Attachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailables\Attachment;
use Luchavez\SimpleFiles\Traits\HasFileFactoryTrait;
use Luchavez\StarterKit\Traits\ModelOwnedTrait;
use Luchavez\StarterKit\Traits\UsesUUIDTrait;

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
    use UsesUUIDTrait;
    use ModelOwnedTrait;
    use HasFileFactoryTrait;

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
        $disk = simpleFiles()->getDisk(is_public: $this->is_public, read_only: true);

        return Attachment::fromStorageDisk(disk: $disk, path: $this->path);
    }

    /***** FILESYSTEM RELATED *****/

    /**
     * @param  bool  $read_only
     * @return Filesystem
     */
    protected function getFilesystemAdapter(bool $read_only = false): Filesystem
    {
        return simpleFiles()->getFilesystemAdapter(is_public: $this->is_public, read_only: $read_only);
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
